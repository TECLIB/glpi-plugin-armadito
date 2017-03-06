<?php

/*
Copyright (C) 2016 Teclib'

This file is part of Armadito Plugin for GLPI.

Armadito Plugin for GLPI is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

Armadito Plugin for GLPI is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with Armadito Plugin for GLPI. If not, see <http://www.gnu.org/licenses/>.

**/

include_once("toolbox.class.php");

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginArmaditoStateAVDetail extends PluginArmaditoEAVCommonDBTM
{
    protected $id;
    protected $agentid;
    protected $agent;
    protected $entries;
    protected $antivirus;

    static function getTypeName($nb = 0)
    {
        return __('State AV Details', 'armadito');
    }

    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() == 'PluginArmaditoStateAVDetail') {
            return __('Antiviruses\' configurations', 'armadito');
        }
        return '';
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == 'PluginArmaditoStateAVDetail') {
            $paAVConfig = new self();
            $paAVConfig->showForm($item->fields["plugin_armadito_agents_id"]);
        }

        return TRUE;
    }

    function getAgent()
    {
        return $this->agent;
    }

    function getId()
    {
        return $this->id;
    }

    function initFromJson($jobj)
    {
        $this->setAgentFromJson($jobj);
        $this->antivirus   = $this->agent->getAntivirus();
        $this->entries     = $jobj->task->obj->avdetails;
    }

    function run()
    {
        $i = 0;
        foreach ($this->entries as $entry)
        {
            $i++;

            PluginArmaditoLog::Verbose("[".$i."] ".$entry->{'attr'}."=".$entry->{'value'});

            $is_agentrow_indb       = $this->isValueForAgentInDB($entry->{'attr'}, $this->agentid);
            $is_baserow_indb        = $this->isValueForAgentInDB($entry->{'attr'}, 0);
            $is_baserow_equal       = $this->isValueEqualForAgentInDB($entry->{'attr'}, $entry->{'value'}, 0);

            if($is_baserow_equal) {
                $this->rmValueFromDB($entry->{'attr'}, $entry->{'value'}, $this->agentid);
                continue;
            }

            if($is_agentrow_indb) {
                $this->updateValueInDB($entry->{'attr'}, $entry->{'value'}, $this->agentid);
                continue;
            }

            if ($is_baserow_indb) {
                $this->addOrUpdateValueForAgent($entry->{'attr'}, $entry->{'value'}, $this->agentid);
            } else {
                $this->insertValueInDB($entry->{'attr'}, $entry->{'value'}, 0);
            }
        }

        $this->addOrUpdateValueForAgent("hasAVConfig", 1, $this->agentid);

        $this->id = PluginArmaditoDbToolbox::getLastInsertedId();
        PluginArmaditoToolbox::validateInt($this->id);
    }

    function showForm($id, $options = array())
    {
        PluginArmaditoToolbox::validateInt($id);

        if(!$this->getFromDB($id)){
            PluginArmaditoLog::Error("Unable to getFromDB AVdetails for ID : ".$id);
        }

        $agent_id     = $this->fields["plugin_armadito_agents_id"];
        $antivirus_id = $this->fields["plugin_armadito_antiviruses_id"];

        $this->showEAVForm($agent_id, $antivirus_id);
    }

    function showErrorMessage()
    {
        echo "<div style='text-align: center;'><br><b>No id provided.</b><br></div>";
    }

}
?>
