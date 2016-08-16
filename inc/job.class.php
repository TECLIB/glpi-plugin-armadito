<?php

/**
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

/**
 * Class dealing with Jobs
 **/
class PluginArmaditoJob extends CommonDBTM {
      protected $id;
      protected $obj;
      protected $jobj;
      protected $type;
      protected $priority;
      protected $agentid;
      protected $antivirus_name;
      protected $antivirus_version;

      function __construct() {
         $this->type = -1;
         $this->priority = -1;
         $this->id = -1;
         $this->obj = "";
         $this->agentid = -1;
         $this->antivirus_name = "";
         $this->antivirus_version = "";
      }

      function initFromForm($key, $type, $POST) {
         $this->agentid = PluginArmaditoToolbox::validateInt($key);
         $this->type = $type;
         $this->setPriority($POST["job_priority"]);
         $this->setAntivirusFromDB();

         // init Scan Obj for example or an other job_type
         $this->initObjFromForm($key, $type, $POST);
      }

      function initFromDB($data) {
         $this->id = $data["id"];
         $this->agentid = $data["plugin_armadito_agents_id"];
         $this->type = $data["job_type"];
         $this->antivirus_name = $data["antivirus_name"];
         $this->antivirus_version = $data["antivirus_version"];
         $this->priority = $data["job_priority"];
         $this->status = $data["job_status"];

         // init Scan Obj for example or an other job_type
         $this->initObjFromDB();
      }

      function initFromJson($jobj) {
          $this->agentid = PluginArmaditoToolbox::validateInt($jobj->agent_id);
          $this->id = PluginArmaditoToolbox::validateInt($jobj->task->obj->{"job_id"});
          $this->jobj = $jobj;
      }

      function getAntivirusName(){
         return $this->antivirus_name;
      }

      function getAntivirusVersion(){
         return $this->antivirus_version;
      }

      function getId(){
         return $this->id;
      }

      function setId($id_){
         $this->id = $id_;
      }

      function getAgentId(){
         return $this->agentid;
      }

      /**
      * Get name of this type
      *
      * @return text name of this type by language of the user connected
      *
      **/
      static function getTypeName($nb=0) {
         return __('Job', 'armadito');
      }

      static function getDefaultDisplayPreferences(){
          $prefs = "";
          $nb_columns = 8;
          for( $i = 1; $i <= $nb_columns; $i++){
               $prefs .= "(NULL, 'PluginArmaditoJob', '".$i."', '".$i."', '0'),";
          }
          return $prefs;
      }

      static function canCreate() {
         if (isset($_SESSION["glpi_plugin_armadito_profile"])) {
            return ($_SESSION["glpi_plugin_armadito_profile"]['armadito'] == 'w');
         }
         return false;
      }

      static function canView() {

         if (isset($_SESSION["glpi_plugin_armadito_profile"])) {
            return ($_SESSION["glpi_plugin_armadito_profile"]['armadito'] == 'w'
                    || $_SESSION["glpi_plugin_armadito_profile"]['armadito'] == 'r');
         }
         return false;
      }

      function initObjFromForm ($key, $type, $POST){
         switch($this->type){
            case "Scan":
               $this->obj = new PluginArmaditoScan();
               $this->obj->initFromForm($this, $POST);
               break;
            default:
               $this->obj = "unknown";
               break;
         }
      }

      function initObjFromDB (){
         switch($this->type){
            case "Scan":
               $this->obj = new PluginArmaditoScan();
               $this->obj->initFromDB($this->id);
               break;
            default:
               $this->obj = "unknown";
               break;
         }
      }

      function getPriorityValue (){
         switch($this->priority){
            case "0 - low":
               return 0;
            case "1 - medium":
               return 1;
            case "2 - high":
               return 2;
            case "3 - urgent":
               return 3;
            default:
               return 0;
         }
      }

      function setPriority ($id){
         PluginArmaditoToolbox::validateInt($id);
         switch($id){
            case 0:
               $this->priority = "0 - low";
               break;
            case 1:
               $this->priority = "1 - medium";
               break;
            case 2:
               $this->priority = "2 - high";
               break;
            case 3:
               $this->priority = "3 - urgent";
               break;
            default:
               $this->priority = "0 - low";
               break;
         }
      }

      static function getAvailableStatuses () {
         return array("queued" => "#dedede",
                      "downloaded" => "#aee7ed",
                      "successful" => "#52d46a",
                      "failed" => "#ff3333",
                      "cancelled" => "#ffc425");
      }

      function updateStatus ($status){
         if($this->getFromDB($this->getId())){
            $input = array();
            $input['id'] = $this->getId();
            $input['job_status'] = $status;
            if(!$this->update($input)){
               PluginArmaditoToolbox::logE("Error when updating job n°".$this->getId()." status in DB.");
               return false;
            }
            return true;
         }
         return false;
      }

      function setAntivirusFromDB(){
         global $DB;
         $query = "SELECT `antivirus_name`, `antivirus_version` FROM `glpi_plugin_armadito_agents`
                 WHERE `id`='".$this->agentid."'";

         $ret = $DB->query($query);

         if(!$ret){
            throw new Exception(sprintf('Error setAntivirusFromDB : %s', $DB->error()));
         }

         if($DB->numrows($ret) > 0){
            $data = $DB->fetch_assoc($ret);
            $this->antivirus_name = $data["antivirus_name"];
            $this->antivirus_version = $data["antivirus_version"];
         }
      }

      function getSearchOptions() {

         $tab = array();
         $tab['common'] = __('Scan', 'armadito');

         $i = 1;

         $tab[$i]['table']     = $this->getTable();
         $tab[$i]['field']     = 'id';
         $tab[$i]['name']      = __('Job Id', 'armadito');
         $tab[$i]['datatype']  = 'itemlink';
         $tab[$i]['itemlink_type'] = 'PluginArmaditoJob';
         $tab[$i]['massiveaction'] = FALSE;

         $i++;

         $tab[$i]['table']     = 'glpi_plugin_armadito_agents';
         $tab[$i]['field']     = 'id';
         $tab[$i]['name']      = __('Agent Id', 'armadito');
         $tab[$i]['datatype']  = 'itemlink';
         $tab[$i]['itemlink_type'] = 'PluginArmaditoAgent';
         $tab[$i]['massiveaction'] = FALSE;

         $i++;

         $tab[$i]['table']     = $this->getTable();
         $tab[$i]['field']     = 'job_type';
         $tab[$i]['name']      = __('Job Type', 'armadito');
         $tab[$i]['datatype']  = 'text';
         $tab[$i]['massiveaction'] = FALSE;

         $i++;

         $tab[$i]['table']     = $this->getTable();
         $tab[$i]['field']     = 'job_priority';
         $tab[$i]['name']      = __('Job Priority', 'armadito');
         $tab[$i]['datatype']  = 'text';
         $tab[$i]['massiveaction'] = FALSE;

         $i++;

         $tab[$i]['table']     = $this->getTable();
         $tab[$i]['field']     = 'job_status';
         $tab[$i]['name']      = __('Job Status', 'armadito');
         $tab[$i]['datatype']  = 'text';
         $tab[$i]['massiveaction'] = FALSE;

         $i++;

         $tab[$i]['table']     = $this->getTable();
         $tab[$i]['field']     = 'antivirus_name';
         $tab[$i]['name']      = __('Antivirus Name', 'armadito');
         $tab[$i]['datatype']  = 'text';
         $tab[$i]['massiveaction'] = FALSE;

         $i++;

         $tab[$i]['table']     = $this->getTable();
         $tab[$i]['field']     = 'antivirus_version';
         $tab[$i]['name']      = __('Antivirus Version', 'armadito');
         $tab[$i]['datatype']  = 'text';
         $tab[$i]['massiveaction'] = FALSE;

         return $tab;
      }

      function toJson() {
         return '{"job_id": '.$this->id.',
                  "job_type": "'.$this->type.'",
                  "job_priority": "'.$this->getPriorityValue().'",
                  "antivirus_name": "'.$this->antivirus_name.'",
                  "obj": '.$this->obj->toJson().'
                 }';
      }

      function setJobId($id) {
         $this->id = $id;
      }

      function getJobId(){
         return $this->id;
      }

      function insertInJobs() {
         global $DB;

         $error = new PluginArmaditoError();
         $query = "INSERT INTO `glpi_plugin_armadito_jobs` (`plugin_armadito_agents_id`, `job_type`, `job_priority`, `job_status`, `antivirus_name`, `antivirus_version`) VALUES (?,?,?,?,?,?)";
         $stmt = $DB->prepare($query);

         if(!$stmt) {
            $error->setMessage(1, 'Job insert preparation failed.');
            $error->log();
            return $error;
         }

         if(!$stmt->bind_param('isssss', $agent_id, $job_type, $job_priority, $job_status, $antivirus_name, $antivirus_version)) {
               $error->setMessage(1, 'Job insert bin_param failed (' . $stmt->errno . ') ' . $stmt->error);
               $error->log();
               $stmt->close();
               return $error;
         }

         $agent_id = $this->agentid;
         $job_type = $this->type;
         $job_priority = $this->priority;
         $job_status = "queued"; # Step 1
         $antivirus_name = $this->antivirus_name;
         $antivirus_version = $this->antivirus_version;

         if(!$stmt->execute()){
            $error->setMessage(1, 'Job insert execution failed (' . $stmt->errno . ') ' . $stmt->error);
            $error->log();
            $stmt->close();
            return $error;
         }

         $stmt->close();

         // We get job_id
         $result = $DB->query("SELECT LAST_INSERT_ID()");
         if($result){
            $data = $DB->fetch_array($result);
            $this->setJobId($data[0]);
         }
         else {
            $error->setMessage(1, 'Enrollment get agent_id failed.');
            $error->log();
            return $error;
         }

         $error->setMessage(0, 'Job insertion successful.');
         return $error;
      }

      function insertInJobsAgents() {
         global $DB;

         $error = new PluginArmaditoError();
         $query = "INSERT INTO `glpi_plugin_armadito_jobs_agents` (`job_id`, `agent_id`) VALUES (?,?)";
         $stmt = $DB->prepare($query);

         if(!$stmt) {
            $error->setMessage(1, 'Job insert preparation failed.');
            $error->log();
            return $error;
         }

         if(!$stmt->bind_param('ii', $job_id, $agent_id)) {
               $error->setMessage(1, 'Job insert bin_param failed (' . $stmt->errno . ') ' . $stmt->error);
               $error->log();
               $stmt->close();
               return $error;
         }

         $job_id = $this->id;
         $agent_id = $this->agentid;

         if(!$stmt->execute()){
            $error->setMessage(1, 'Job insert execution failed (' . $stmt->errno . ') ' . $stmt->error);
            $error->log();
            $stmt->close();
            return $error;
         }

         $error->setMessage(0, 'Job insertion successful.');
         $stmt->close();
         return $error;
      }

      function addJob() {
         $error = new PluginArmaditoError();
         $error = $this->insertInJobs();
         if($error->getCode() != 0){
            $error->log();
            return false;
         }

         $error = $this->insertInJobsAgents();
         if($error->getCode() != 0){
            $error->log();
            return false;
         }

         $error = $this->obj->addObj($this->id);
         if($error->getCode() != 0){
            $error->log();
            return false;
         }
         return true;
      }

      function cancelJob() {
         if($this->getFromDB($this->id)){
            if($this->fields["job_status"] == "queued"){
               return $this->updateStatus("cancelled");
            }
            else if($this->fields["job_status"] == "cancelled"){
               return true;
            }
         }
         return false;
      }

      /**
       * Massive action ()
       */
      function getSpecificMassiveActions($checkitem=NULL) {

         $actions = array();
         if (Session::haveRight("plugin_armadito_jobs", UPDATE)) {
            $actions[__CLASS__.MassiveAction::CLASS_ACTION_SEPARATOR.'canceljob'] = __('Cancel job(s)', 'armadito');
         }

         return $actions;
      }

      /**
       * @since version 0.85
       *
       * @see CommonDBTM::showMassiveActionsSubForm()
      **/
      static function showMassiveActionsSubForm(MassiveAction $ma) {

         switch ($ma->getAction()) {
            case 'canceljob' :
               PluginArmaditoJob::showCancelForm();
               return true;
         }

         return parent::showMassiveActionsSubForm($ma);
      }

      static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item,
                                                          array $ids) {

         $job = new self();

         switch ($ma->getAction()) {

            case 'canceljob' :
               foreach ($ids as $job_id) {
                  $job->setId($job_id);
                  if ($job->cancelJob()){
                     $ma->itemDone($item->getType(), $job_id, MassiveAction::ACTION_OK);
                  } else {
                     $ma->itemDone($item->getType(), $job_id, MassiveAction::ACTION_KO);
                  }
               }
            return;
         }

         return;
      }

      static function showCancelForm(){
         echo "<b>Only queued jobs can be cancelled.</b><br>";
         echo "Do you want to continue anyway ?<br>";
         echo "<br><br>".Html::submit(__('Post'),
                                      array('name' => 'massiveaction'));
      }


      function defineTabs($options=array()){

         $ong = array();
         $this->addDefaultFormTab($ong);
         $this->addStandardTab('Log', $ong, $options);

         return $ong;
      }

      /**
      * Display form
      *
      * @param $agent_id integer ID of the agent
      * @param $options array
      *
      * @return bool TRUE if form is ok
      *
      **/
      function showForm($table_id, $options=array()) {

         // Protect against injections
         PluginArmaditoToolbox::validateInt($table_id);

         // Init Form
         $this->initForm($table_id, $options);
         $this->showFormHeader($options);

         echo "<tr class='tab_bg_1'>";
         echo "<td>".__('Name')." :</td>";
         echo "<td align='center'>";
         Html::autocompletionTextField($this,'name', array('size' => 40));
         echo "</td>";
         echo "<td>".__('Agent Id', 'armadito')."&nbsp;:</td>";
         echo "<td align='center'>";
         echo "<b>".htmlspecialchars($this->fields["plugin_armadito_agents_id"])."</b>";
         echo "</td>";
         echo "</tr>";
      }
}
?>
