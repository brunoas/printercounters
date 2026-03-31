<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 printercounters plugin for GLPI
 Copyright (C) 2014-2022 by the printercounters Development Team.

 https://github.com/InfotelGLPI/printercounters
 -------------------------------------------------------------------------

 LICENSE

 This file is part of printercounters.

 printercounters is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 printercounters is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with printercounters. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginPrintercountersItem_Recordmodel
 *
 * This class allows to add and manage record models on the items
 *
 * @package    Printercounters
 * @author     Ludovic Dupont
 */
class PluginPrintercountersItem_Recordmodel extends CommonDBTM {

   static $types = ['Printer'];
   static $rightname = 'plugin_printercounters';

   public $dohistory = true;

   var $rand = 0;
   var $massiveaction;
   var $fixedDisplay;

   protected $itemtype;
   protected $items_id;


   /**
    * Constructor
    *
    * @param type $itemtype
    * @param type $items_id
    */
   public function __construct($itemtype = 'printer', $items_id = 0) {
      $this->setItemtype($itemtype);
      $this->setItems_id($items_id);
      $this->setRand();

      parent::__construct();
   }

   /**
    * functions mandatory
    * getTypeName(), canCreate(), canView()
    * */
   static function getTypeName($nb = 0) {
      return _n('Linked record model', 'Linked record models', $nb, 'printercounters');
   }

   static function getIcon() {
      return "fas fa-tachometer-alt";
   }

   /**
    * Function sets itemtype id
    *
    * @param string $itemtype
    * @throws Exception
    */
   public function setItemtype($itemtype) {

      if (empty($itemtype)) {
         throw new PluginPrintercountersException(__('Invalid itemtype', 'printercounters'));
      }

      $this->itemtype = $itemtype;
   }

   /**
    * Function sets items id
    *
    * @param string $items_id
    */
   public function setItems_id($items_id) {

      $this->items_id = $items_id;
   }

   /**
    * Function sets rand
    */
   public function setRand() {

      $this->rand = mt_rand();
   }


   /**
    * getFieldName
    *
    * @param type $field
    * @return type
    */
   function getFieldName($field) {

      switch ($field) {
         case 'date': return __('Date');
         case 'entities_id': return __('Entity');
         case 'periodicity': return __('Periodicity', 'printercounters');
         case 'state': return __('State', 'printercounters');
         case 'result': return __('Result', 'printercounters');
         case 'items_id': return __('Entity');
         case 'record_type': return __('Record type', 'printercounters');
      }
   }

   /**
    * Display tab for item
    *
    * @param CommonGLPI $item
    * @param int $withtemplate
    * @return array|string
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if (!$withtemplate) {
         switch ($item->getType()) {
            case 'PluginPrintercountersRecordmodel' :
               if ($_SESSION['glpishow_count_on_tabs']) {
                  $dbu = new DbUtils();
                  return self::createTabEntry(__('Linked items', 'printercounters'),
                                              $dbu->countElementsInTable($this->getTable(),
                                                                         ["plugin_printercounters_recordmodels_id" => $item->getID()]));
               }
               return __('Linked items', 'printercounters');
               break;
            case 'Printer' :
               if ($_SESSION['glpishow_count_on_tabs']) {
                  return self::createTabEntry(PluginPrintercountersRecord::getTypeName(2));
               }
               return PluginPrintercountersRecord::getTypeName(2);
               break;
         }
      }
      return '';
   }

   /**
    * Display tab content
    *
    * @static
    * @param CommonGLPI $item
    * @param int $tabnum
    * @param int $withtemplate
    * @return bool|true
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      switch ($item->getType()) {
         case 'PluginPrintercountersRecordmodel' :
            $item_recordmodel = new self('Printer', $item->getID());
            $item_recordmodel->showForRecordmodel($item);
            break;
         case 'Printer' :
            $item_recordmodel = new self($item->getType(), $item->getID());
            $item_recordmodel->showForItem($item);
            break;
      }
      return true;
   }

   /**
   * showForItem
   *
   * @param type $item
   * @return boolean
   */
   function showForItem($item) {
      $dbu = new DbUtils();
      if ($dbu->countElementsInTable($this->getTable(),
                                     ["items_id" => $item->getID(),
                                      "itemtype" => $item->getType()])) {
         // Record error
         echo "<div class='center' id='error_item'>";
         $errorItem = new PluginPrintercountersErrorItem($item->getType(), $item->getID());
         $errorItem->showErrorItem();
         echo "</div>";

         // Show sub unit data
         $additional_data = new PluginPrintercountersAdditional_data($item->getType(), $item->getID());
         echo "<div class='center' id='additional_datas'>";
         $additional_data->showAdditionalData();
         echo "</div>";

         // Init printercounter JS
         PluginPrintercountersRecord::initPrintercountersActionsJS();

         // Record actions
         $record = new PluginPrintercountersRecord($item->getType(), $item->getID());
         $record->showActions($this->rand);

         // Record config
         $this->showRecordConfigForItem();

         // Record history
         $search = new PluginPrintercountersSearch();
         $search->showSearch($record);

         // Link to a record model
      } else {
         echo "<form name='form' method='post' action='".Toolbox::getItemTypeFormURL($this->getType())."'>";
         echo "<div class='center'>";
         echo "<table border='0' class='tab_cadre_fixe'>";
         echo "<tr class='tab_bg_1'>";
         echo "<td class='center'>";
         echo __('Link the item to a record model', 'printercounters')."&nbsp;";
         Dropdown::show("PluginPrintercountersRecordmodel", ['name'   => 'plugin_printercounters_recordmodels_id',
                                                                  'entity' => $item->fields['entities_id']]);
         echo Html::hidden('itemtype', ['value' => $this->itemtype]);
         echo Html::hidden('items_id', ['value' => $this->items_id]);
         echo Html::submit(_sx('button', 'Post'), ['name' => 'add', 'class' => 'btn btn-primary']);
         echo "</td>";
         echo "</tr>";
         echo "</table>";
         echo '</div>';
         Html::closeForm();
      }
   }

   /**
   * showForRecordmodel
   *
   * @param type $item
   * @return boolean
   */
   function showForRecordmodel($item) {
      global $DB;

      $recordmodel = new PluginPrintercountersRecordmodel();
      $canedit = ($recordmodel->can($item->fields['id'], UPDATE) && $this->canCreate());

      $itemtype = $this->itemtype;

      if (isset($_POST["start"])) {
         $start = $_POST["start"];
      } else {
         $start = 0;
      }
      $data = $this->getItems($item->fields['id'], ['start' => $start, 'addLimit' => true]);
      $rows = count($this->getItems($item->fields['id'], ['addLimit' => false]));
      $dbu  = new DbUtils();

      if ($canedit) {
         echo "<form name='form' method='post' action='".Toolbox::getItemTypeFormURL($this->getType())."'>";

         echo "<div align='center'><table class='tab_cadre_fixe'>";
         echo "<tr><th colspan='6'>".__('Add an item', 'printercounters')."</th></tr>";

         echo "<tr class='tab_bg_1'>";
         // Dropdown item
         echo "<td class='center'>";
         echo $itemtype::getTypeName(2).'&nbsp;';

         $iterator = $DB->request(['SELECT' => 'items_id',
                                     'FROM'  => $this->getTable(),
                                     'WHERE' => [
                                        'itemtype' => $itemtype,
                                     ],
                                  ]);
         $used = [];
         foreach ($iterator as $row) {
            $used[] = $row['items_id'];
         }

         Dropdown::show($itemtype, ['name'        => 'items_id',
                                    'entity'      => $item->fields['entities_id'],
                                    'entity_sons' => true,
                                    'used'        => $used]);
         echo "</td>";
         echo "</tr>";

         echo "<tr>";
         echo "<td class='tab_bg_2 center' colspan='6'>";
         echo Html::hidden('plugin_printercounters_recordmodels_id', ['value' => $item->fields['id']]);
         echo Html::hidden('itemtype', ['value' => $this->itemtype]);
         echo Html::submit(_sx('button', 'Add'), ['name' => 'add', 'class' => 'btn btn-primary']);
         echo "</td>";
         echo "</tr>";
         echo "</table></div>";
         Html::closeForm();
      }

      if (!empty($data)) {
         $this->listItems($data, $canedit, $start, $rows);
      }
   }

    /**
   * listItems
   *
   * @param array $data
   * @param bool $canedit
   * @param int $start
   */
   private function listItems($data, $canedit, $start, $rows) {

      $rand = mt_rand();

      $itemtype = $this->itemtype;

      echo "<div class='left'>";
      if ($canedit) {
         Html::openMassiveActionsForm('mass'.__CLASS__.$rand);
         $massiveactionparams = ['item' => __CLASS__, 'container' => 'mass'.__CLASS__.$rand];
         Html::showMassiveActions($massiveactionparams);
      }

      Html::printAjaxPager($itemtype::getTypeName(2), $start, $rows);
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr class='tab_bg_1'>";
      echo "<th width='10'>";
      if ($canedit) {
         echo Html::getCheckAllAsCheckbox('mass'.__CLASS__.$rand);
      }
      echo "</th>";
      echo "<th>".__('Name')."</th>";
      echo "<th>".__('Entity')."</th>";
      echo "<th>".__('Status')."</th>";
      echo "<th>".__('Type')."</th>";
      echo "<th>".__('Model')."</th>";
      echo "<th>".__('Location')."</th>";
      echo "<th>".__('Last record date', 'printercounters')."</th>";
      echo "<th>".__('Last record type', 'printercounters')."</th>";
      echo "</tr>";

      foreach ($data as $field) {
         echo "<tr class='tab_bg_1'>";
         echo "<td width='10'>";
         if ($canedit) {
            Html::showMassiveActionCheckBox(__CLASS__, $field['id']);
         }
         echo "</td>";
         // Name
         $link = Toolbox::getItemTypeFormURL($this->itemtype).'?id='.$field['items_id'];
         echo "<td><a href='$link' target='_blank'>".$field['items_name']."</a></td>";
         // Entity name
         echo "<td>".$field['entities_name']."</td>";
         // State name
         echo "<td>".$field['states_name']."</td>";
         // Type name
         echo "<td>".$field['printertypes_name']."</td>";
         // Model name
         echo "<td>".$field['models_name']."</td>";
         // Location name
         echo "<td>".$field['locations_name']."</td>";
         // Last record date
         echo "<td>".html::convDateTime($field['last_record_date'])."</td>";
         // Last record type
         echo "<td>".PluginPrintercountersRecord::getRecordType($field['last_record_type'])."</td>";
         echo "</tr>";
      }
      echo "</table>";
      if ($canedit) {
         $massiveactionparams['ontop'] = false;
         Html::showMassiveActions($massiveactionparams);
         Html::closeForm();
      }

      echo "</div>";
   }


   /**
   * Get item billingmodel items
   *
   * @global type $DB
   * @param type $recordmodels_id
   * @param type $options : - bool addLimit : add limit to the search
   *                        - int start     : start line
   *                        - int limit     : number of lines
   *
   * @return type
   */
   function getItems($recordmodels_id = 0, $options = []) {
      global $DB;

      $params['start']    = 0;
      $params['limit']    = $_SESSION['glpilist_limit'];
      $params['addLimit'] = true;

      if (!empty($options)) {
         foreach ($options as $key => $val) {
            $params[$key] = $val;
         }
      }

      $output = [];
      $dbu    = new DbUtils();

      $itemjoin   = $dbu->getTableForItemType($this->itemtype);
      $itemjoin2  = $dbu->getTableForItemType($this->itemtype.'Model');
      $itemjoin3  = $dbu->getTableForItemType('State');
      $itemjoin4  = $dbu->getTableForItemType($this->itemtype.'Type');
      $itemjoin5  = $dbu->getTableForItemType('Location');
      $itemjoin6  = $dbu->getTableForItemType('Entity');
      $itemjoin7  = $dbu->getTableForItemType('PluginPrintercountersItem_Recordmodel');
      $itemjoin8  = $dbu->getTableForItemType('PluginPrintercountersRecord');

      $query = "SELECT `".$itemjoin."`.`name` as items_name,
                       `".$itemjoin."`.`id` as items_id, 
                       `".$itemjoin6."`.`name` as entities_name,
                       `".$itemjoin3."`.`name` as states_name,
                       `".$itemjoin4."`.`name` as printertypes_name,
                       `".$itemjoin2."`.`name` as models_name,
                       `".$itemjoin5."`.`completename` as locations_name,
                       `".$this->getTable()."`.`id`,
                        `".$itemjoin7."`.`plugin_printercounters_recordmodels_id` as recordmodels_id,
                       `glpi_plugin_printercounters_records`.`date` as last_record_date,
                       `glpi_plugin_printercounters_records`.`record_type` as last_record_type
          FROM ".$this->getTable()."
          LEFT JOIN `".$itemjoin."` 
             ON (`".$this->getTable()."`.`items_id` = `".$itemjoin."`.`id` 
                  AND LOWER(`".$itemjoin7."`.`itemtype`) = LOWER('".$this->itemtype."'))
          LEFT JOIN `".$itemjoin2."` 
             ON (`".$itemjoin2."`.`id` = `".$itemjoin."`.`".strtolower($this->itemtype)."models_id`)
          LEFT JOIN `".$itemjoin3."` 
             ON (`".$itemjoin."`.`states_id` = `".$itemjoin3."`.`id`)  
          LEFT JOIN `".$itemjoin4."` 
             ON (`".$itemjoin4."`.`id` = `".$itemjoin."`.`".strtolower($this->itemtype)."types_id`) 
          LEFT JOIN `".$itemjoin5."` 
             ON (`".$itemjoin5."`.`id` = `".$itemjoin."`.`locations_id`) 
          LEFT JOIN `".$itemjoin6."` 
             ON (`".$itemjoin6."`.`id` = `".$itemjoin."`.`entities_id`) 
          LEFT JOIN `$itemjoin8`
             ON (`$itemjoin7`.`id` = `$itemjoin8`.`plugin_printercounters_items_recordmodels_id`
                  AND `$itemjoin8`.`date` = (
                       SELECT max(`$itemjoin8`.`date`) 
                       FROM $itemjoin8 
                       WHERE `$itemjoin8`.`plugin_printercounters_items_recordmodels_id` = `$itemjoin7`.`id`
                     )
                ) 
          WHERE 1";

      if ($recordmodels_id) {
         $query .= " AND`".$this->getTable()."`.`plugin_printercounters_recordmodels_id` = ".$recordmodels_id;
      }

      $query .= " GROUP BY `".$itemjoin."`.`id`  
          ORDER BY `".$itemjoin."`.`name` ASC";

      if ($params['addLimit']) {
         $query .= " LIMIT ".intval($params['start']).",".intval($params['limit']);
      }

      $result = $DB->doQuery($query);
      if ($DB->numrows($result)) {
         while ($data = $DB->fetchAssoc($result)) {
            $output[$data['id']] = $data;
         }
      }

      return $output;
   }

   /**
    *  Function get item recordmodel for an item
    *
    * @global type $DB
    * @return type
    */
   function getItem_RecordmodelForItem() {
      global $DB;

      $output = [];
      $dbu    = new DbUtils();

      $itemjoin  = $dbu->getTableForItemType('PluginPrintercountersRecordmodel');
      $itemjoin2 = $dbu->getTableForItemType($this->itemtype);

      $query = "SELECT `".$this->getTable()."`.`id`, 
                       `".$this->getTable()."`.`plugin_printercounters_snmpauthentications_id`,
                       `".$this->getTable()."`.`enable_automatic_record`,
                       `".$this->getTable()."`.`periodicity`,
                       `".$this->getTable()."`.`max_timeout`,
                       `".$this->getTable()."`.`nb_retries`,
                       `".$itemjoin."`.`id` as plugin_printercounters_recordmodels_id,
                       `".$itemjoin."`.`name` as recordmodels_name,
                       `".$itemjoin."`.`entities_id` as recordmodels_entity,
                       `".$itemjoin."`.`is_recursive` as recordmodels_recursivity,
                       `".$itemjoin."`.`mac_address_conformity`,
                       `".$itemjoin."`.`sysdescr_conformity`,
                       `".$itemjoin."`.`serial_conformity`,
                       `".$itemjoin2."`.`entities_id`,
                       `".$itemjoin2."`.`locations_id`   
          FROM ".$this->getTable()."
          LEFT JOIN `".$itemjoin."` 
             ON(`".$this->getTable()."`.`plugin_printercounters_recordmodels_id` = `".$itemjoin."`.`id`)
          LEFT JOIN `".$itemjoin2."` 
             ON(`".$this->getTable()."`.`items_id` = `".$itemjoin2."`.`id`)
          WHERE `items_id` = ".$this->items_id. " AND `itemtype`='".$this->itemtype."';";

      $result = $DB->doQuery($query);
      if ($DB->numrows($result)) {
         while ($data = $DB->fetchAssoc($result)) {
            $output[$data['id']] = $data;
         }
      }

      return $output;
   }

   /**
    * Function show record configuration for an item
    *
    */
   function showRecordConfigForItem() {

      if (!$this->canCreate()) {
         return false;
      }

      $data = $this->getItem_RecordmodelForItem($this->items_id, $this->itemtype);
      $data = reset($data);

      if (!empty($data)) {
         $width = 150;

         echo "<form name='form' method='post' action='".
               Toolbox::getItemTypeFormURL($this->getType())."'>";
         echo "<div class='center'>";
         echo "<table class='tab_cadre_fixe'>";
         echo "<tr><th colspan='8'>".__('Record configuration', 'printercounters')."</th></tr>";
         echo "<tr class='tab_bg_1'>";

         // Record model link
         echo "<td>".PluginPrintercountersRecordmodel::getTypeName(1)."</td>";
         echo "<td>";
         Dropdown::show('PluginPrintercountersRecordmodel', ['name'      => 'plugin_printercounters_recordmodels_id',
                                                                  'value'     => $data['plugin_printercounters_recordmodels_id'],
                                                                  'entity'    => $data['entities_id'],
                                                                  'width'     => $width,
                                                                  'on_change' => "printercounters_setConfirmation(\"".__('Are you sure to change the recordmodel ?', 'printercounters')."\", ".$data['plugin_printercounters_recordmodels_id'].", this.value, \"printercounters_recordConfig\", \"update_config\");"]);

         echo "</td>";

         // SNMP authentication
         echo "<td>".PluginPrintercountersSnmpauthentication::getTypeName()."</td>";
         echo "<td>";
         Dropdown::show('PluginPrintercountersSnmpauthentication', ['value' => $data['plugin_printercounters_snmpauthentications_id'],
                                                                         'width' => $width]);
         echo "</td>";

         // Enable record
         echo "<td>".__('Enable automatic record', 'printercounters')."</td>";
         echo "<td>";
         Dropdown::showYesNo('enable_automatic_record', $data['enable_automatic_record'], -1, ['width' => $width]);
         echo "</td>";
         echo "</tr>";
         echo "<tr class='tab_bg_1'>";

         // Periodicity
         echo "<td>".__('Periodicity of automatic record', 'printercounters')."</td>";
         echo "<td>";
         Dropdown::showTimeStamp("periodicity", ['min'             => HOUR_TIMESTAMP,
                                                 'max'             => 15 * DAY_TIMESTAMP,
                                                 'step'            => HOUR_TIMESTAMP,
                                                 'value'           => $data['periodicity'],
                                                 'addfirstminutes' => false,
                                                 'inhours'         => false,
                                                 'width'           => $width]);
         echo "</td>";

         // Retries
         echo "<td>".__('Number of retries', 'printercounters')."</td>";
         echo "<td>";
         Dropdown::showNumber('nb_retries', ['value' => $data["nb_retries"],
                                             'min'   => 0,
                                             'max'   => 10,
                                             'step'  => 1],
                              ['width' => $width]);
         echo "</td>";

         // Timeout
         echo "<td>".__('Timeout', 'printercounters')."</td>";
         echo "<td>";
         Dropdown::showNumber("max_timeout", ['min'   => 0,
                                                    'max'   => 60,
                                                    'value' => $data["max_timeout"],
                                                    'unit'  => 'second',
                                                    'width' => $width]);
         echo "</td>";
         echo "</tr>";

         echo "<tr>";
         echo "<td class='tab_bg_2 center' colspan='6'>";
         echo Html::hidden('id', ['value' => $data['id']]);
         echo Html::hidden('_clean_records', ['value' => 1]);
         echo Html::hidden('items_id', ['value' => $this->items_id]);
         echo Html::hidden('itemtype', ['value' => $this->itemtype]);
         echo Html::submit(_sx('button', 'Update'), ['name' => 'update_config', 'class' => 'btn btn-primary']);
         echo "</td>";
         echo "</tr>";
         echo "</table></div>";
         Html::closeForm();
      }
   }

   /**
    * Provides search options configuration. Do not rely directly
    * on this, @see CommonDBTM::searchOptions instead.
    *
    * @since 9.3
    *
    * This should be overloaded in Class
    *
    * @return array a *not indexed* array of search options
    *
    * @see https://glpi-developer-documentation.rtfd.io/en/master/devapi/search.html
    **/
   public function rawSearchOptions() {

      $itemtype = $this->itemtype;
      $dbu      = new DbUtils();
      $item     = $dbu->getItemForItemtype($itemtype);

      $tab[] = [
         'id'                 => '89',
         'table'              => $dbu->getTableForItemType($itemtype),
         'field'              => 'name',
         'name'               => $item::getTypeName(),
         'datatype'           => 'itemlink',
         'massiveaction'      => false,
         'nosearch'           => true,
         'linkfield'          => 'items_id'
      ];

      $tab[] = [
         'id'                 => '91',
         'table'              => 'glpi_entities',
         'field'              => 'name',
         'name'               => $this->getFieldName('entities_id'),
         'massiveaction'      => false,
         'linkfield'          => 'entities_id',
         'datatype'           => 'dropdown',
         'joinparams'         => [
            'beforejoin'         => [
               'table'              => $dbu->getTableForItemType($itemtype),
               'linkfield'          => 'items_id'
            ]
         ]
      ];

      $tab[] = [
         'id'                 => '92',
         'table'              => 'glpi_plugin_printercounters_records',
         'field'              => 'date',
         'name'               => $this->getFieldName('date'),
         'datatype'           => 'datetime',
         'massiveaction'      => false,
         'searchequalsonfield' => true,
         'joinparams'         => [
            'jointype'           => 'child',
            'condition'          => 'AND NEWTABLE.`date` = (SELECT max(`glpi_plugin_printercounters_records`.`date`)
                                                             FROM glpi_plugin_printercounters_records
                                                             WHERE `glpi_plugin_printercounters_records`.`plugin_printercounters_items_recordmodels_id` = REFTABLE.`id`) '
         ]
      ];

      $tab[] = [
         'id'                 => '93',
         'table'              => $this->getTable(),
         'field'              => 'periodicity',
         'name'               => $this->getFieldName('periodicity'),
         'datatype'           => 'timestamp',
         'searchtype'         => ['equals', 'notequals'],
         'min'                => HOUR_TIMESTAMP,
         'max'                => 15 * DAY_TIMESTAMP,
         'step'               => HOUR_TIMESTAMP,
         'addfirstminutes'    => false,
         'inhours'            => false,
         'massiveaction'      => false
      ];

      $tab[] = [
         'id'                 => '94',
         'table'              => 'glpi_plugin_printercounters_records',
         'field'              => 'state',
         'name'               => $this->getFieldName('state'),
         'datatype'           => 'specific',
         'searchtype'         => ['equals', 'notequals'],
         'massiveaction'      => false,
         'joinparams'         => [
            'jointype'           => 'child'
         ],
         'searchequalsonfield' => true
      ];

      $tab[] = [
         'id'                 => '95',
         'table'              => 'glpi_plugin_printercounters_records',
         'field'              => 'result',
         'name'               => $this->getFieldName('result'),
         'datatype'           => 'specific',
         'searchtype'         => ['equals', 'notequals'],
         'massiveaction'      => false,
         'joinparams'         => [
            'jointype'           => 'child'
         ],
         'searchequalsonfield' => true
      ];

      $tab[] = [
         'id'                 => '96',
         'table'              => 'glpi_plugin_printercounters_records',
         'field'              => 'record_type',
         'name'               => $this->getFieldName('record_type'),
         'datatype'           => 'specific',
         'nosearch'           => true,
         'nodisplay'          => true,
         'massiveaction'      => true,
         'joinparams'         => [
            'jointype'           => 'child'
         ],
         'searchequalsonfield' => true
      ];

      $tab[] = [
         'id'                 => '97',
         'table'              => $dbu->getTableForItemType($itemtype),
         'field'              => 'id',
         'name'               => $item::getTypeName(),
         'massiveaction'      => false,
         'nosearch'           => true,
         'nodisplay'          => true,
         'linkfield'          => 'items_id'
      ];

      return $tab;
   }


   /**
   * Add search options for an item
   *
   * @return array
   */
   function getAddSearchOptions() {

      $dbu = new DbUtils();

      $tab[6091]['table']          = 'glpi_plugin_printercounters_records';
      $tab[6091]['field']          = 'date';
      $tab[6091]['name']           = __('Printercounters', 'printercounters').' - '.__('Last record date', 'printercounters');
      $tab[6091]['datatype']       = 'specific';
      $tab[6091]['searchtype']     = 'contains';
      $tab[6091]['forcegroupby']   = true;
      $tab[6091]['massiveaction']  = false;
      $tab[6091]['computation']    = "CONCAT(TABLE.`date`, '||', TABLE.`record_type`)";
      $tab[6091]['joinparams']     = ['jointype'   => 'child',
                                           'condition'  => "AND NEWTABLE.`date` = (SELECT max(`glpi_plugin_printercounters_records`.`date`)
                                                                                   FROM glpi_plugin_printercounters_records
                                                                                   WHERE `glpi_plugin_printercounters_records`.`plugin_printercounters_items_recordmodels_id` = REFTABLE.`id`) ",
                                           'beforejoin'
                                            => ['table'      => 'glpi_plugin_printercounters_items_recordmodels',
                                                     'joinparams' => ['jointype'   => 'itemtype_item']]
                                          ];

      $tab[6092]['table']          = 'glpi_plugin_printercounters_records';
      $tab[6092]['field']          = 'record_type';
      $tab[6092]['name']           = __('Printercounters', 'printercounters').' - '.__('Last record type', 'printercounters');
      $tab[6092]['datatype']       = 'specific';
      $tab[6092]['forcegroupby']   = true;
      $tab[6092]['searchtype']     = 'equals';
      $tab[6092]['searchequalsonfield'] = true;
      $tab[6092]['massiveaction']  = false;
      $tab[6092]['joinparams']     = ['jointype'   => 'child',
                                           'condition'  => "AND NEWTABLE.`date` = (SELECT max(`glpi_plugin_printercounters_records`.`date`) 
                                                                                   FROM glpi_plugin_printercounters_records 
                                                                                   WHERE `glpi_plugin_printercounters_records`.`plugin_printercounters_items_recordmodels_id` = REFTABLE.`id`) ",
                                           'beforejoin'
                                            => ['table'      => 'glpi_plugin_printercounters_items_recordmodels',
                                                     'joinparams' => ['jointype'   => 'itemtype_item']]
                                          ];

      $tab[6093]['table']          = 'glpi_plugin_printercounters_items_recordmodels';
      $tab[6093]['field']          = 'enable_automatic_record';
      $tab[6093]['name']           = __('Printercounters', 'printercounters').' - '.__('Enable automatic record', 'printercounters');
      $tab[6093]['massiveaction']  = false;
      $tab[6093]['datatype']       = 'bool';
      $tab[6093]['joinparams']     = ['jointype'   => 'itemtype_item',
                                          'beforejoin' => ['table' => $dbu->getTableForItemType($this->itemtype)]
                                   ];

      $tab[6094]['table']         = 'glpi_plugin_printercounters_recordmodels';
      $tab[6094]['field']         = 'name';
      $tab[6094]['name']          = __('Printercounters', 'printercounters') . ' - ' . PluginPrintercountersRecordmodel::getTypeName(1);
      $tab[6094]['datatype']      = 'dropdown';
      $tab[6094]['massiveaction'] = false;
      $tab[6094]['joinparams']    = ['beforejoin' => ['table'      => 'glpi_plugin_printercounters_items_recordmodels',
                                                      'joinparams' => ['jointype'   => 'itemtype_item',
                                                                       'beforejoin' => ['table' => $dbu->getTableForItemType($this->itemtype)]]]
                                          ];

      $tab[6095]['table']          = 'glpi_plugin_printercounters_items_recordmodels';
      $tab[6095]['field']          = 'periodicity';
      $tab[6095]['name']           = __('Printercounters', 'printercounters').' - '.__('Periodicity of automatic record', 'printercounters');
      $tab[6095]['datatype']       = 'timestamp';
      $tab[6095]['searchtype']     = 'equals';
      $tab[6095]['massiveaction']  = false;
      $tab[6095]['joinparams']     = ['jointype'   => 'itemtype_item',
         'beforejoin' => ['table' => $dbu->getTableForItemType($this->itemtype)]
      ];

      $tab[6096]['table']          = 'glpi_plugin_printercounters_snmpauthentications';
      $tab[6096]['field']          = 'name';
      $tab[6096]['name']           = __('Printercounters', 'printercounters') . ' - ' . PluginPrintercountersSnmpauthentication::getTypeName(1);
      $tab[6096]['datatype']       = 'dropdown';
      $tab[6096]['massiveaction']  = false;
      $tab[6096]['joinparams']     = ['beforejoin' => ['table'      => 'glpi_plugin_printercounters_items_recordmodels',
                                                      'joinparams' => ['jointype'   => 'itemtype_item',
                                                                       'beforejoin' =>
                                                                          ['table' => $dbu->getTableForItemType($this->itemtype)]]]
      ];

      $tab[6097]['table']          = 'glpi_plugin_printercounters_items_recordmodels';
      $tab[6097]['field']          = 'nb_retries';
      $tab[6097]['name']           = __('Printercounters', 'printercounters').' - '.__('Number of retries', 'printercounters');
      $tab[6097]['massiveaction']  = false;
      $tab[6097]['searchtype']     = 'equals';
      $tab[6097]['datatype']       = 'number';
      $tab[6097]['joinparams']     = ['jointype'   => 'itemtype_item',
                                           'beforejoin' => ['table' => $dbu->getTableForItemType($this->itemtype)]
                                    ];

      $tab[6098]['table']          = $this->getTable();
      $tab[6098]['field']          = 'global_tco';
      $tab[6098]['name']           = __('Printercounters', 'printercounters').' - '.__('Global TCO', 'printercounters');
      $tab[6098]['massiveaction']  = false;
      $tab[6098]['nosearch']       = true;
      $tab[6098]['datatype']       = 'decimal';
      $tab[6098]['joinparams']     = ['jointype'   => 'itemtype_item',
                                     'beforejoin' => ['table' => $dbu->getTableForItemType($this->itemtype)]
                              ];

      $tab[6100]['table']          = 'glpi_plugin_printercounters_items_recordmodels';
      $tab[6100]['field']          = 'max_timeout';
      $tab[6100]['name']           = __('Printercounters', 'printercounters').' - '.__('Timeout', 'printercounters');
      $tab[6100]['massiveaction']  = false;
      $tab[6100]['searchtype']     = 'equals';
      $tab[6100]['datatype']       = 'number';
      $tab[6100]['joinparams']     = ['jointype'   => 'itemtype_item',
                                           'beforejoin' => ['table' => $dbu->getTableForItemType($this->itemtype)]
                                    ];

      $irmJoin = ['jointype'   => 'itemtype_item',
                  'beforejoin' => ['table' => $dbu->getTableForItemType($this->itemtype)]];

      // Toner remaining (%)
      $tab[6110]['table']          = 'glpi_plugin_printercounters_items_recordmodels';
      $tab[6110]['field']          = 'toner_remaining';
      $tab[6110]['name']           = __('Printercounters', 'printercounters').' - '.__('Toner remaining (%)', 'printercounters');
      $tab[6110]['datatype']       = 'specific';
      $tab[6110]['nosearch']       = true;
      $tab[6110]['nosort']         = true;
      $tab[6110]['massiveaction']  = false;
      $tab[6110]['forcegroupby']   = true;
      $tab[6110]['joinparams']     = $irmJoin;
      $tab[6110]['computation']    =
         "(SELECT GROUP_CONCAT(CONCAT(ad.sub_type, ':', ad.value) ORDER BY ad.sub_type SEPARATOR '|')
           FROM glpi_plugin_printercounters_additionals_datas ad
           WHERE ad.plugin_printercounters_items_recordmodels_id = TABLE.id
             AND ad.type = 'toner')";

      // Est. end date
      $tab[6111]['table']          = 'glpi_plugin_printercounters_items_recordmodels';
      $tab[6111]['field']          = 'est_end_date';
      $tab[6111]['name']           = __('Printercounters', 'printercounters').' - '.__('Est. end date', 'printercounters');
      $tab[6111]['datatype']       = 'specific';
      $tab[6111]['nosearch']       = true;
      $tab[6111]['nosort']         = true;
      $tab[6111]['massiveaction']  = false;
      $tab[6111]['forcegroupby']   = true;
      $tab[6111]['joinparams']     = $irmJoin;
      $tab[6111]['computation']    = "TABLE.items_id";

      // Toner coverage (%)
      $tab[6112]['table']          = 'glpi_plugin_printercounters_items_recordmodels';
      $tab[6112]['field']          = 'toner_coverage';
      $tab[6112]['name']           = __('Printercounters', 'printercounters').' - '.__('Coverage (%)', 'printercounters');
      $tab[6112]['datatype']       = 'specific';
      $tab[6112]['nosearch']       = true;
      $tab[6112]['nosort']         = true;
      $tab[6112]['massiveaction']  = false;
      $tab[6112]['forcegroupby']   = true;
      $tab[6112]['joinparams']     = $irmJoin;
      $tab[6112]['computation']    = "TABLE.items_id";

      return $tab;
   }

   /** Cache for per-printer toner/cartridge data used by search columns. */
   static $searchDataCache = [];

   /**
    * Render custom search columns for toner remaining, end date, and coverage.
    */
   static function getSpecificValueToDisplay($field, $values, array $options = []) {
      global $CFG_GLPI;

      if (!is_array($values)) {
         $values = [$field => $values];
      }
      $val = $values[$field] ?? '';

      switch ($field) {
         case 'toner_remaining':
            return self::renderTonerRemaining($val);

         case 'est_end_date':
            return self::renderEstEndDate((int)$val);

         case 'toner_coverage':
            return self::renderTonerCoverage((int)$val);
      }

      return parent::getSpecificValueToDisplay($field, $values, $options);
   }

   /**
    * Render last record date, colored in critical when record_type is an error state.
    * Input format from computation: "YYYY-MM-DD HH:MM:SS||record_type"
    */
   static function renderLastRecordDate($raw) {
      global $CFG_GLPI;

      if (empty($raw)) {
         return '&mdash;';
      }

      $parts = explode('||', $raw, 2);
      $date = $parts[0] ?? '';
      $record_type = (int)($parts[1] ?? 0);

      if (empty($date)) {
         return '&mdash;';
      }

      // record_type 1 = Host error, 2 = Record error
      $isError = ($record_type === 1 || $record_type === 2);

      $formatted = Html::convDateTime($date);

      if ($isError) {
         $colorCritical = $CFG_GLPI['priority_6'] ?? '#ff0000';
         return "<span style=\"color:{$colorCritical}\">{$formatted}</span>";
      }

      return $formatted;
   }

   /**
    * Render toner remaining column from GROUP_CONCAT value.
    * Format: "sub_type:value|sub_type:value|..."
    */
   static function renderTonerRemaining($raw) {
      global $CFG_GLPI;
      if (empty($raw)) {
         return '&mdash;';
      }

      $colorCritical = $CFG_GLPI['priority_6'] ?? '#ff0000';
      $colorVHigh = $CFG_GLPI['priority_5'] ?? '#ff5555';
      $snmpColors = ['black', 'cyan', 'magenta', 'yellow'];
      $order = self::$colorOrder;

      $parts = explode('|', $raw);
      $items = [];
      foreach ($parts as $part) {
         $kv = explode(':', $part, 2);
         if (count($kv) !== 2) continue;
         $sub_type = trim($kv[0]);
         $value = (int)$kv[1];

         // Match SNMP color key
         $matched_key = '';
         foreach ($snmpColors as $c) {
            if (stripos($sub_type, $c) !== false) {
               $matched_key = $c;
               break;
            }
         }

         $remaining = $value;
         if ($remaining <= 5) {
            $style = "color:{$colorCritical}";
         } elseif ($remaining <= 20) {
            $style = "color:{$colorVHigh}";
         } else {
            $style = "color:rgb(var(--tblr-primary-rgb))";
         }

         $items[] = [
            'style'       => $style,
            'value'       => $remaining,
            'color_key'   => $matched_key,
            'color_label' => $matched_key
               ? PluginPrintercountersExpected_Yield::getColorLabel($matched_key)
               : '',
            'sort_order'  => $order[$matched_key] ?? 99,
         ];
      }

      // Sort by canonical color order
      usort($items, function ($a, $b) {
         return $a['sort_order'] - $b['sort_order'];
      });

      $multi = count($items) > 1;
      $html = [];
      foreach ($items as $item) {
         $text = $item['value'] . '%';
         if ($multi && $item['color_label']) {
            $text .= ' (' . htmlspecialchars($item['color_label']) . ')';
         }
         $html[] = "<span style=\"{$item['style']}\">{$text}</span>";
      }

      return implode('<br>', $html);
   }

   /**
    * Get or compute cached printer data for search columns.
    */
   static function getSearchData($printers_id) {
      if (isset(self::$searchDataCache[$printers_id])) {
         return self::$searchDataCache[$printers_id];
      }

      $printer = new Printer();
      if (!$printer->getFromDB($printers_id)) {
         self::$searchDataCache[$printers_id] = null;
         return null;
      }

      $init_pages = (int)$printer->fields['init_pages_counter'];
      $last_pages = (int)$printer->fields['last_pages_counter'];
      $data = PluginPrintercountersCartridge_Yield::getUsedCartridgesData($printers_id, $init_pages, $last_pages);

      self::$searchDataCache[$printers_id] = $data;
      return $data;
   }

   /**
    * Render est. end date column.
    */
   static function renderEstEndDate($printers_id) {
      global $CFG_GLPI;
      if ($printers_id <= 0) return '&mdash;';

      $data = self::getSearchData($printers_id);
      if (empty($data)) return '&mdash;';

      $colorVHigh = $CFG_GLPI['priority_5'] ?? '#ff5555';
      $dateFormat = (int)($CFG_GLPI['date_format'] ?? 0);
      $now = new DateTime();
      $items = [];

      $colorInfo = self::getCartridgeColorInfo($printers_id);
      $multi = count($data) > 1;

      // Iterate in canonical color order
      foreach ($colorInfo as $cid => $_ci) {
         if (!isset($data[$cid])) continue;
         $d = $data[$cid];
         $label = $_ci['label'];
         $suffix = ($multi && $label) ? ' (' . htmlspecialchars($label) . ')' : '';

         if ($d['toner_consumed'] === null || $d['toner_consumed'] <= 0
             || $d['toner_consumed'] >= 100 || !$d['date_use']) {
            $items[] = '&mdash;' . $suffix;
            continue;
         }
         $start = new DateTime($d['date_use']);
         $daysUsed = max(1, (int)$now->diff($start)->days);
         $rate = $d['toner_consumed'] / $daysUsed;
         $remaining = 100 - $d['toner_consumed'];
         $daysRemaining = ($rate > 0) ? round($remaining / $rate) : 0;
         $endDate = (clone $now)->modify("+{$daysRemaining} days");

         $text = self::formatDate($endDate, $dateFormat) . $suffix;
         if ($daysRemaining <= 5) {
            $items[] = "<span style=\"color:{$colorVHigh}\">{$text}</span>";
         } else {
            $items[] = $text;
         }
      }

      return empty($items) ? '&mdash;' : implode('<br>', $items);
   }

   /**
    * Render toner coverage column.
    */
   static function renderTonerCoverage($printers_id) {
      global $CFG_GLPI;
      if ($printers_id <= 0) return '&mdash;';

      $data = self::getSearchData($printers_id);
      if (empty($data)) return '&mdash;';

      $colorCritical = $CFG_GLPI['priority_6'] ?? '#ff0000';
      $colorInfo = self::getCartridgeColorInfo($printers_id);
      $multi = count($data) > 1;
      $items = [];

      // Iterate in canonical color order
      foreach ($colorInfo as $cid => $_ci) {
         if (!isset($data[$cid])) continue;
         $d = $data[$cid];
         $label = $_ci['label'];
         $suffix = ($multi && $label) ? ' (' . htmlspecialchars($label) . ')' : '';

         if ($d['coverage'] === null) {
            $items[] = '&mdash;' . $suffix;
            continue;
         }

         $cov = $d['coverage'];
         $text = number_format($cov, 2, ',', '.') . '%' . $suffix;

         if ($cov <= 5.0) {
            $style = "color:rgb(var(--tblr-primary-rgb))";
         } else {
            $style = "color:{$colorCritical}";
         }

         $items[] = "<span style=\"{$style}\">{$text}</span>";
      }

      return empty($items) ? '&mdash;' : implode('<br>', $items);
   }

   /** Canonical SNMP color sort order. */
   static $colorOrder = ['black' => 0, 'cyan' => 1, 'magenta' => 2, 'yellow' => 3, 'other' => 4];

   /**
    * Get color info for in-use cartridges of a printer.
    * Returns [cartridge_id => ['key' => snmp_color_key, 'label' => localized_label], ...]
    * sorted by canonical color order (black, cyan, magenta, yellow, other).
    */
   static function getCartridgeColorInfo($printers_id) {
      global $DB;
      static $cache = [];
      if (isset($cache[$printers_id])) return $cache[$printers_id];

      $result = $DB->doQuery("
         SELECT c.id, COALESCE(ey.snmp_color, '') AS snmp_color
         FROM glpi_cartridges c
         JOIN glpi_cartridgeitems ci ON ci.id = c.cartridgeitems_id
         LEFT JOIN " . PluginPrintercountersExpected_Yield::$table . " ey
            ON ey.cartridgeitemtypes_id = ci.cartridgeitemtypes_id
            AND ci.cartridgeitemtypes_id > 0
         WHERE c.printers_id = " . (int)$printers_id . "
           AND c.date_use IS NOT NULL AND c.date_out IS NULL
      ");

      $info = [];
      if ($result) {
         while ($row = $result->fetch_assoc()) {
            $color = $row['snmp_color'];
            $info[(int)$row['id']] = [
               'key'   => $color,
               'label' => $color
                  ? PluginPrintercountersExpected_Yield::getColorLabel($color)
                  : '',
            ];
         }
      }

      // Sort by canonical color order
      $order = self::$colorOrder;
      uasort($info, function ($a, $b) use ($order) {
         $oa = $order[$a['key']] ?? 99;
         $ob = $order[$b['key']] ?? 99;
         return $oa - $ob;
      });

      $cache[$printers_id] = $info;
      return $info;
   }

   /**
    * Get color labels for cartridges of a printer (convenience wrapper).
    */
   static function getCartridgeColorLabels($printers_id) {
      $info = self::getCartridgeColorInfo($printers_id);
      $labels = [];
      foreach ($info as $cid => $i) {
         $labels[$cid] = $i['label'];
      }
      return $labels;
   }

   /**
    * Format a DateTime according to GLPI date format setting.
    */
   static function formatDate(DateTime $date, int $format): string {
      switch ($format) {
         case 1: return $date->format('d-m-Y');
         case 2: return $date->format('m-d-Y');
         default: return $date->format('Y-m-d');
      }
   }

   /**
    * Massive actions to be added
    *
    * @param $input array of input datas
    *
    * @return array of results (nbok, nbko, nbnoright counts)
    **/
   function massiveActions() {

      $prefix = $this->getType().MassiveAction::CLASS_ACTION_SEPARATOR;

      switch (strtolower($this->itemtype)) {
         case "printer":
            $output = [];
            if ($this->canCreate()) {
               $output =  [
                  $prefix."plugin_printercounters_automatic_record"        => __('Printer counters', 'printercounters').' - '.__('Enable automatic record', 'printercounters'),
                  $prefix."plugin_printercounters_periodicity"             => __('Printer counters', 'printercounters').' - '.__('Set periodicity', 'printercounters'),
                  $prefix."plugin_printercounters_recordmodel"             => __('Printer counters', 'printercounters').' - '.__('Set record model', 'printercounters'),
                  $prefix."plugin_printercounters_snmp_auth"               => __('Printer counters', 'printercounters').' - '.__('Set SNMP authentication', 'printercounters'),
                  $prefix."plugin_printercounters_retries"                 => __('Printer counters', 'printercounters').' - '.__('Set retries', 'printercounters'),
                  $prefix."plugin_printercounters_max_timeout"             => __('Printer counters', 'printercounters').' - '.__('Set max timeout', 'printercounters'),
                  $prefix."plugin_printercounters_immediate_record"        => __('Printer counters', 'printercounters').' - '.__('Immediate record', 'printercounters'),
                  $prefix."plugin_printercounters_update_counter_position" => __('Printer counters', 'printercounters').' - '.__('Update counter position', 'printercounters'),
                  $prefix."plugin_printercounters_init_counters"           => __('Printer counters', 'printercounters').' - '.__('Init counters to zero', 'printercounters'),
                  $prefix."plugin_printercounters_snmp_set"                => __('Printer counters', 'printercounters').' - '.__('Set printer values', 'printercounters')
               ];
            }

            if ($this->canView() || $this->canCreate()) {
               $output[$prefix."plugin_printercounters_immediate_record"] = __('Printer counters', 'printercounters').' - '.__('Immediate record', 'printercounters');
            }
            return $output;
      }
   }

   /**
    * Massive actions display
    *
    * @param $input array of input datas
    *
    * @return array of results (nbok, nbko, nbnoright counts)
    * */
   static function showMassiveActionsSubForm(MassiveAction $ma) {

      $itemtype = $ma->getItemtype(false);
      $item_recordmodel = new self();

      switch (strtolower($itemtype)) {
         case 'printer':
            switch ($ma->getAction()) {
               case "plugin_printercounters_automatic_record":
                  if ($item_recordmodel->canCreate()) {
                     Dropdown::showYesNo('enable_automatic_record');
                  }
                  break;

               case "plugin_printercounters_periodicity":
                  if ($item_recordmodel->canCreate()) {
                     Dropdown::showTimeStamp("periodicity", ['min'             => HOUR_TIMESTAMP,
                                                                  'max'             => 15*DAY_TIMESTAMP,
                                                                  'step'            => HOUR_TIMESTAMP,
                                                                  'addfirstminutes' => false,
                                                                  'inhours'         => false]);
                  }
                  break;

               case "plugin_printercounters_retries":
                  if ($item_recordmodel->canCreate()) {
                     Dropdown::showNumber('nb_retries', ['min' => 0,
                                                         'max' => 10]);
                  }
                  break;

               case "plugin_printercounters_max_timeout":
                  if ($item_recordmodel->canCreate()) {
                     Dropdown::showNumber("max_timeout", ['min'   => 0,
                                                                'max'   => 60,
                                                                'unit'  => 'second']);
                  }
                  break;

               case "plugin_printercounters_recordmodel":
                  if ($item_recordmodel->canCreate()) {
                     Dropdown::show("PluginPrintercountersRecordmodel", ['name' => 'plugin_printercounters_recordmodels_id']);
                  }
                  break;

               case "plugin_printercounters_snmp_auth":
                  if ($item_recordmodel->canCreate()) {
                     Dropdown::show("PluginPrintercountersSnmpauthentication", ['name' => 'plugin_printercounters_snmpauthentications_id']);
                  }
                  break;
            }
            return parent::showMassiveActionsSubForm($ma);
      }
   }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::processMassiveActionsForOneItemtype()
   **/
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item,
                                                       array $ids) {
      global $CFG_GLPI;

      $_SESSION["plugin_printercounters"]["massiveaction"] = $ma;
      $_SESSION["plugin_printercounters"]["ids"]           = $ids;

      $ma->results['ok'] = 1;

      $ma->setRedirect(PLUGIN_PRINTERCOUNTERS_WEBDIR.'/front/record.form.php?initAjaxMassiveAction=1');
   }


   /**
    * Init massive actions process
    *
    **/
   function initMassiveActionsProcess() {
      global $CFG_GLPI;

      $ma  = $_SESSION["plugin_printercounters"]["massiveaction"];
      $ids = $_SESSION["plugin_printercounters"]["ids"];

      $itemtype = $ma->getItemtype(false);

      $ma->POST["process_count"] = 0;

      $data = $this->find(["items_id"        => $ids,
                           "itemtype" => ucfirst(strtolower($itemtype))]);
      $item_data = [];
      foreach ($data as $key => $val) {
         $item_data[$val['items_id']] = $val;
      }

      foreach ($ids as $key => $val) {
         if ($val) {
            $ma->POST["process_count"]++;
         }
      }

      $ma->POST['item_data'] = $item_data;
      $_SESSION["plugin_printercounters"]["massiveaction"] = $ma;

      // Init progress bar
      echo "<table class='tab_cadrehov'>";
      echo "<tr class='tab_bg_1'><th>".$ma->action_name."</th></tr>";
      echo "<tr class='tab_bg_1'><td><br/>";
      Html::createProgressBar(__('Work in progress...'));
      echo"</td></tr>";
      echo "</table>";

      // Launch ajax massive action
      echo "<script type='text/javascript'>";
      echo "$(document).ready(function() {";
      echo "printecounters_ajaxMassiveAction('".PLUGIN_PRINTERCOUNTERS_WEBDIR."', 'ajaxMassiveAction', ".ini_get('max_execution_time').");";
      echo "});";
      echo "</script>";
   }

   /**
    * Do massive actions treatments
    *
    * @return array of results (nbok, nbko, nbnoright counts)
    **/
   function doMassiveActionProcess() {
      global $CFG_GLPI;

      $ma  = $_SESSION["plugin_printercounters"]["massiveaction"];
      $ids = $_SESSION["plugin_printercounters"]["ids"];

      $itemtype = $ma->getItemtype(false);
      $dbu      = new DbUtils();
      $item     = $dbu->getItemForItemtype($itemtype);

      $item_data = $ma->POST['item_data'];

      if ($count = count($ids)) {
         $i = $ma->POST["process_count"]-$count+1;
         $key = key($ids);
         unset($ids[$key]);

         if ($item->can($key, UPDATE)) {
            $result = false;
            switch ($ma->getAction()) {
               case "plugin_printercounters_automatic_record":
                  if (isset($item_data[$key])) {
                     $result = $this->update(['id' => $item_data[$key]['id'], 'enable_automatic_record' => $ma->POST['enable_automatic_record']]);
                  }
                  break;

               case "plugin_printercounters_periodicity":
                  if (isset($item_data[$key])) {
                     $result = $this->update(['id' => $item_data[$key]['id'], 'periodicity' => $ma->POST['periodicity']]);
                  }
                  break;

               case "plugin_printercounters_retries":
                  if (isset($item_data[$key])) {
                     $result = $this->update(['id' => $item_data[$key]['id'], 'nb_retries' => $ma->POST['nb_retries']]);
                  }
                  break;

               case "plugin_printercounters_max_timeout":
                  if (isset($item_data[$key])) {
                     $result = $this->update(['id' => $item_data[$key]['id'], 'max_timeout' => $ma->POST['max_timeout']]);
                  }
                  break;

               case "plugin_printercounters_recordmodel":
                  if (isset($item_data[$key])) {
                     $result = $this->update(['id' => $item_data[$key]['id'], 'plugin_printercounters_recordmodels_id' => $ma->POST['plugin_printercounters_recordmodels_id'], '_clean_records' => 1]);
                  } else {
                     $result = $this->add(['plugin_printercounters_recordmodels_id' => $ma->POST['plugin_printercounters_recordmodels_id'],
                                                'items_id'                               => $key,
                                                'itemtype'                               => $itemtype]);
                  }
                  break;

               case "plugin_printercounters_snmp_auth":
                  if (isset($item_data[$key])) {
                     $result = $this->update(['id' => $item_data[$key]['id'], 'plugin_printercounters_snmpauthentications_id' => $ma->POST['plugin_printercounters_snmpauthentications_id']]);
                  }
                  break;

               case "plugin_printercounters_immediate_record":
                  $record = new PluginPrintercountersRecord();
                  list($messages, $error) = $record->immediateRecord($key, $itemtype);
                  $result = true;
                  if ($error) {
                     $result = false;
                  }
                  break;

               case "plugin_printercounters_update_counter_position":
                  $record = new PluginPrintercountersRecord();
                  list($messages, $error) = $record->updateCounterPosition($key, $itemtype);
                  $result = true;
                  if ($error) {
                     $result = false;
                  }
                  break;

               case "plugin_printercounters_init_counters":
                  $record = new PluginPrintercountersRecord($itemtype, $key);
                  $result = false;
                  if ($record->setFirstRecord($item_data[$key]['id'], $item_data[$key]['plugin_printercounters_recordmodels_id'])) {
                     $result = true;
                  }
                  break;

               case "plugin_printercounters_snmp_set":
                  $snmpset = new PluginPrintercountersSnmpset();
                  list($messages, $error) = $snmpset->snmpSet($item_data[$key]['items_id'], $item_data[$key]['itemtype']);
                  $result = true;
                  if ($error) {
                     $result = false;
                  }
                  break;

               default :
                  return parent::doSpecificMassiveActions($ma->POST);
            }

            if ($result) {
               $ma->results['ok']++;
            } else {
               $ma->results['ko']++;
            }
         } else {
            $ma->results['noright']++;
         }
      }

      if (count($ids)) {
         // more to do -> redirect
         $_SESSION["plugin_printercounters"]["massiveaction"] = $ma;
         $_SESSION["plugin_printercounters"]["ids"]           = $ids;
         Html::changeProgressBarPosition($i, $ma->POST["process_count"], sprintf(__('%1$s/%2$s'), $i, $ma->POST["process_count"]));
         echo "<script type='text/javascript'>";
         echo "printecounters_ajaxMassiveAction('".PLUGIN_PRINTERCOUNTERS_WEBDIR."', 'ajaxMassiveAction', ".ini_get('max_execution_time').");";
         echo "</script>";

      } else { // Nothing to do redirect
         $nbok      = 0;
         $nbnoright = 0;
         $nbko      = 0;

         if (is_array($ma->results)
               && isset($ma->results['ok'])
               && isset($ma->results['ko'])
               && isset($ma->results['noright'])) {

            $nbok      = $ma->results['ok'];
            $nbko      = $ma->results['ko'];
            $nbnoright = $ma->results['noright'];

         } else {
            if ($ma->results) {
               $nbok++;
            } else {
               $nbko++;
            }
         }

         // Default message : all ok
         $message = __('Operation successful');
         // All failed. operations failed
         if ($nbok == 0) {
            $message = __('Failed operation');
            if ($nbnoright) {
               //TRANS: %$1d and %$2d are numbers
               $message .= "<br>".sprintf(__('(%1$d authorizations problems, %2$d failures)'),
                                           $nbnoright, $nbko);
            }
         } else if ($nbnoright || $nbko) {
            // Partial success
            $message = __('Operation performed partially successful');
            $message .= "<br>".sprintf(__('(%1$d authorizations problems, %2$d failures)'),
                                       $nbnoright, $nbko);
         }

         unset($_SESSION["plugin_printercounters"]["massiveaction"]);
         unset($_SESSION["plugin_printercounters"]["ids"]);

         Html::changeProgressBarPosition(100, 100);
         Session::addMessageAfterRedirect($message);
         Html::redirect($CFG_GLPI['root_doc'].'/front/printer.php');
      }
   }

   /**
   * Handle massive action timeout
   *
   */
   function massiveActionTimeOut() {

      $ma  = $_SESSION["plugin_printercounters"]["massiveaction"];
      $ids = $_SESSION["plugin_printercounters"]["ids"];

      if ($count = count($ids)) {
         $i = $ma->POST["process_count"]-$count+1;
         $key = key($ids);
         unset($ids[$key]);
         $_SESSION["plugin_printercounters"]["massiveaction"] = $ma;
         $_SESSION["plugin_printercounters"]["ids"]           = $ids;
         Html::changeProgressBarPosition($i, $ma->POST["process_count"], sprintf(__('%1$s/%2$s'), $i, $ma->POST["process_count"]));
         $this->doMassiveActionProcess();
      }
   }


   /**
    * Function Show the record planning
    *
    * @return an array
    */
   function showRecordPlanning() {

      $search = new PluginPrintercountersSearch();
      $search->showSearch($this);
   }

   /**
    * Search function : set default search
    *
    * @return an array
    */
   function getDefaultSearch() {

      $default_search = [];
      $options        = Search::getCleanedOptions($this->getType());
      foreach ($options as $num => $val) {
         if ($val['field'] == 'state') {
            $fields_num = $num;
            break;
         }
      }
      foreach ($options as $num => $val) {
         if ($val['table'] == 'glpi_plugin_printercounters_records' && $val['field'] == 'date') {
            $default_search['sort'] = $num;
            break;
         }
      }

      $default_search['criteria'][0] = ['field' => $fields_num, 'searchtype' => 'equals', 'value' => PluginPrintercountersRecord::$PROGRAMMED_STATE, 'link' => ''];
      $default_search['criteria'][1] = ['field' => $fields_num, 'searchtype' => 'equals', 'value' => PluginPrintercountersRecord::$PROGRESS_STATE, 'link' => 'OR'];

      $default_search['order'] = 'ASC';

      return $default_search;
   }

   /**
    * Search function : set restricition
    *
    * @return an array
    */
   function addRestriction() {
      $options = Search::getCleanedOptions($this->getType());
      $dbu     = new DbUtils();
      foreach ($options as $num => $val) {
         if ($val['table'] == $dbu->getTableForItemType($this->itemtype) && $val['field'] == 'name') {
            return PluginPrintercountersSearch::addWhere('', 1, $this->getType(), $num, 'equals', null);
         }
      }
   }

   /**
    * Search function : countLines
    *
    * @param type $search
    * @return type
    */
   function countLines(PluginPrintercountersSearch $search) {

      $this->formatSearchData($search);

      return count($search->dataSearch);
   }


   /**
    * Search function : getSearchTitle
    *
    * @return string
    */
   function getSearchTitle() {
      return __('Record planning', 'printercounters');
   }

   /**
    * Search function : show record history data
    *
    * @param PluginPrintercountersSearch $search
    */
   function showSearchData(PluginPrintercountersSearch $search) {

      $input = [];

      for ($i = $search->current_search['start']; $i < $search->current_search['start'] + $search->current_search['limit']; $i++) {
         if (isset($search->dataSearch[$i])) {
            $input[] = $search->dataSearch[$i];
         }
      }

      $row_num = 1;
      foreach ($input as $row) {
         $row_num++;
         $col_num = 1;
         echo Search::showNewLine($search->output_type);
         foreach ($row as $val) {
            echo Search::showItem($search->output_type, $val, $col_num, $row_num);
         }
         echo Search::showEndLine($search->output_type);
      }
   }

   /**
    * Function format record history data
    *
    * @param PluginPrintercountersSearch $search
    * @return \PluginPrintercountersSearch
    */
   function formatSearchData(PluginPrintercountersSearch $search) {

      $searchopt = [];
      $searchopt = &Search::getOptions($this->getType());

      $types      = [];
      $search_num = [];
      $give_item  = [];
      $count      = 0;
      $sort       = null;
      $order      = "ASC";
      $dbu        = new DbUtils();

      foreach ($searchopt as $num => $val) {
         if ($val['table'] == 'glpi_plugin_printercounters_records' && $val['field'] == 'date') {
            $types['date']      = 'ITEM_'.$num;
            $search_num['date'] = $num;

         } else if ($val['table'] == $dbu->getTableForItemType($this->itemtype) && $val['field'] == 'id') {
            $types['id']      = 'ITEM_'.$num;
            $search_num['id'] = $num;

         } else if ($val['table'] == $dbu->getTableForItemType($this->itemtype) && $val['field'] == 'name') {
            $types['name']      = 'ITEM_'.$num;
            $search_num['name'] = $num;

         } else if ($val['table'] == 'glpi_entities' && $val['field'] == 'name') {
            $types['entity']      = 'ITEM_'.$num;
            $search_num['entity'] = $num;

         } else if ($val['table'] == 'glpi_plugin_printercounters_records' && $val['field'] == 'state') {
            $types['state']      = 'ITEM_'.$num;
            $search_num['state'] = $num;

         } else if ($val['table'] == 'glpi_plugin_printercounters_records' && $val['field'] == 'result') {
            $types['result']      = 'ITEM_'.$num;
            $search_num['result'] = $num;

         } else if ($val['table'] == $this->getTable() && $val['field'] == 'periodicity') {
            $types['periodicity']      = 'ITEM_'.$num;
            $search_num['periodicity'] = $num;
         }

         $count++;
      }

      // Get sort params
      foreach ($search_num as $field => $num) {
         if ($search->current_search['sort'] == $num) {
            $sort = $types[$field];
            break;
         }
      }
      $order = $search->current_search['order'];

      // Inject planned record to data list
      // Manage search parameters
      $condition = [];
      $criteria  = [];

      foreach ($search->current_search['criteria'] as $key => $search_item) {
         if (!empty($search_item['value'])) {
            $LINK    = " ";
            $NOT     = 0;
            $tmplink = "";

            if (isset($search_item['link'])) {
               if (strstr($search_item['link'], "NOT")) {
                  $tmplink = " ".str_replace(" NOT", "", $search_item['link']);
                  $NOT     = 1;
               } else {
                  $tmplink = " ".$search_item['link'];
               }
            } else {
               $tmplink = " AND ";
            }
            // Manage Link if not first item
            if (!empty($condition)) {
               $LINK = $tmplink;
            }

            // Condition cannot be state or date or result
            if ($search_item['field'] != $search_num['result']
                    && $search_item['field'] != $search_num['state']
                    && $search_item['field'] != $search_num['date']) {

               $condition[$key] = PluginPrintercountersSearch::addWhere($LINK, $NOT, $this->getType(), $search_item['field'], $search_item['searchtype'], $search_item['value']);
            }

            $criteria[$key] = ['LINK'        => $tmplink,
                                    'NOT'         => $NOT,
                                    'field'       => $search_item['field'],
                                    'value'       => $search_item['value'],
                                    'searchtype'  => $search_item['searchtype']];
         }
      }

      // Inject planned record
      $query = '';
      if (!empty($condition)) {
         $query .= " AND ( ";
         foreach ($condition as $value) {
            $query .= $value;
         }
         $query .= " )";
      }

      $select_items = $this->getRecordsPlanification($query);
      foreach ($select_items as $value) {
         // Compare search data
         if ($search->compareData($this->getType(), $criteria, [$search_num['date']   => $value['date'],
                                                                     $search_num['state']  => $value['state'],
                                                                     $search_num['result'] => $value['result']])) {

            $search->dataSearch[] = [$types['name']        => $value['name'],
                                          $types['name'].'_id'  => $value['id'],
                                          $types['entity']      => $value['entities_name'],
                                          $types['date']        => $value['date'],
                                          $types['periodicity'] => $value['periodicity'],
                                          $types['state']       => $value['state'],
                                          $types['result']      => $value['result']];
         }
      }

      // Sort
      if (!empty($sort)) {
         switch ($order) {
            case "ASC":
               usort($search->dataSearch, function($a, $b) use ($sort) {
                  if (is_numeric($a[$sort])) {
                     return $a[$sort] - $b[$sort];
                  } else {
                     return strcmp($a[$sort], $b[$sort]);
                  }
               });
               break;
            case "DESC":
               usort($search->dataSearch, function($a, $b) use ($sort) {
                  if (is_numeric($a[$sort])) {
                     return $b[$sort] - $a[$sort];
                  } else {
                     return strcmp($b[$sort], $a[$sort]);
                  }
               });
               break;
         }
      }

      //Format normal output
      if (!empty($search->dataSearch)) {
         $line['raw'] = [];
         foreach ($search->dataSearch as $i => $row) {
            $row['id']   = $row[$types['name'].'_id'];
            $line['raw'] = $row;
            PluginPrintercountersSearch::parseData($line,$this->getType());
            $count = 0;
            foreach ($searchopt as $num => $val) {
               if (!isset($val['nodisplay']) || !$val['nodisplay']) {
                  $give_item[$i]['ITEM_'.$num] = Search::giveItem($this->getType(), $num, $line, $count);
               }
               $count++;
            }
         }
      }

      $search->dataSearch = $give_item;

      return $search;
   }

   /**
    * Function gets items to be query on next interrogation
    *
    * @param type $condition
    * @return null
    */
   function getRecordsPlanification($condition = '') {

      $output = [];

      $process      = new PluginPrintercountersProcess();
      $select_items = $process->selectPrinterToSearch(true, false, $condition);

      if (!empty($select_items)) {
         foreach ($select_items as $value) {
            $state = PluginPrintercountersRecord::$PROGRAMMED_STATE;
            $date  = $value['next_record'];
            // If mutex is valid search is in progress on the item
            if (!empty($value['active_mutex']) && $value['mutex_delay'] < PluginPrintercountersProcess::MUTEX_TIMEOUT) {
               $state = self::$PROGRESS_STATE;
               $date  = strtotime($value['active_mutex']);
            }

            $output[$value['items_id']]['id']            = $value['items_id'];
            $output[$value['items_id']]['name']          = $value['items_name'];
            $output[$value['items_id']]['entities_name'] = $value['entities_name'];
            $output[$value['items_id']]['date']          = date('Y-m-d H:i:s', $date);
            $output[$value['items_id']]['periodicity']   = $value['periodicity_seconds'];
            $output[$value['items_id']]['state']         = $state;
            $output[$value['items_id']]['result']        = null;
            $output[$value['items_id']]['active_mutex']  = $value['active_mutex'];
         }
      }

      return $output;
   }

   /**
   * getItemRecordConfig
   *
   * @global type $DB
   * @param type $items_id
   * @param type $itemtype
   * @return type
   */
   function getItemRecordConfig($items_id, $itemtype) {
      global $DB;

      $output   = [];
      $dbu      = new DbUtils();
      $itemjoin = $dbu->getTableForItemType($itemtype);

      if (!empty($items_id) && !empty($itemtype)) {
         $query = "SELECT `".$this->getTable()."`.`id` as plugin_items_recordmodels_id,
                          `".$this->getTable()."`.`plugin_printercounters_recordmodels_id` as plugin_recordmodels_id,
                          `".$this->getTable()."`.`items_id`,
                          `".$this->getTable()."`.`periodicity`,
                          (`".$this->getTable()."`.`max_timeout`)*1000000 as max_timeout,
                          `".$this->getTable()."`.`nb_retries`,
                          `".$this->getTable()."`.`enable_automatic_record`,
                          `".$this->getTable()."`.`error_counter`,
                          `".$itemjoin."`.`entities_id`,
                          `".$itemjoin."`.`locations_id`,
                          `".$itemjoin."`.`name`
             FROM ".$this->getTable()."
             LEFT JOIN `".$itemjoin."`
                ON (`".$this->getTable()."`.`items_id` = `".$itemjoin."`.`id`)
             WHERE `".$this->getTable()."`.`items_id` IN ('".implode("','", $items_id)."')
             AND LOWER(`".$this->getTable()."`.`itemtype`)=LOWER('".$itemtype."')";

         $result = $DB->doQuery($query);
         if ($DB->numrows($result)) {
            while ($data = $DB->fetchAssoc($result)) {
               $output[$data['items_id']] = $data;
            }
         }
      }

      return $output;
   }

   /**
   * function set mutex on item
   *
   * @global type $DB
   * @param array $items_id
   * @param type $process_id
   */
   function setMutex(array $items_id, $process_id) {
      global $DB;

      // process_id column is unsigned, use 0 for non-process contexts
      $safe_process_id = ($process_id !== null && $process_id >= 0) ? $process_id : 0;

      $DB->doQuery("UPDATE ".$this->getTable()."
                  SET `active_mutex`='".date('Y-m-d H:i:s', time())."', `process_id`='".$safe_process_id."'
                  WHERE `".$this->getTable()."`.`items_id` IN ('".implode("','", $items_id)."')
                  AND LOWER(`".$this->getTable()."`.`itemtype`)='".$this->itemtype."'");

   }

   /**
   * function unset mutex on item
   *
   * @global type $DB
   * @param int $items_recordmodels_id
   */
   function unsetMutex($items_recordmodels_id) {
      $this->update(['id' => $items_recordmodels_id, 'active_mutex' => 'NULL', 'process_id' => 0]);
   }

   /**
   * function get mutex of item
   *
   * @global type $DB
   * @param int $items_recordmodels_id
   */
   function getMutex($items_recordmodels_id) {
      $this->getFromDB($items_recordmodels_id);

      return $this->fields['active_mutex'];
   }

   /**
    * Add Logs
    *
    * @return nothing
    */
   function addLog() {

      if ($this->dohistory && !empty($this->oldvalues)) {
         $searchopt = Search::getOptions($this->fields['itemtype']);

         if (!is_array($searchopt)) {
            return false;
         }
         $dbu  = new DbUtils();
         $item = $dbu->getItemForItemtype($this->fields['itemtype']);

         foreach ($this->oldvalues as $key => $oldval) {
            $changes = [];

            // Parsing $SEARCHOPTION to find changed field
            foreach ($searchopt as $id_search_option => $val2) {
               if (!is_array($val2) || !isset($val2['table']) || !isset($val2['field'])) {
                  // skip sub-title
                  continue;
               }

               // Linkfield or standard field not massive action enable
               if (($val2['field'] == $key && $val2['table'] == $this->getTable())
                       || ($key == getForeignKeyFieldForItemType('PluginPrintercountersRecordmodel')
                               && $val2['field'] == 'name'
                           && $val2['table'] == $dbu->getTableForItemType('PluginPrintercountersRecordmodel')
                       || ($key == getForeignKeyFieldForItemType('PluginPrintercountersSnmpauthentication')
                               && $val2['field'] == 'name'
                           && $val2['table'] == $dbu->getTableForItemType('PluginPrintercountersSnmpauthentication')))) {

                  $changes = [$id_search_option, $item->getValueToDisplay($searchopt[$id_search_option],
                                                                          addslashes($oldval)),
                              $item->getValueToDisplay($searchopt[$id_search_option], $this->fields[$key])];
                  break;
               }
            }

            Log::history($this->fields['items_id'], $this->fields['itemtype'], $changes);
         }
      }
   }


   /**
   * Add left join to the search
   *
   * @param type $type
   * @param type $ref_table
   * @param type $new_table
   * @param type $linkfield
   * @param type $already_link_tables
   * @return type
   */
   function addLeftJoin($type, $ref_table, $new_table, $linkfield, &$already_link_tables) {

      switch ($new_table) {
         case "glpi_plugin_printercounters_budgets" :
            $out = " LEFT JOIN `$new_table`
               ON (`glpi_plugin_printercounters_records`.`entities_id` = `$new_table`.`entities_id`
                   AND `glpi_plugin_printercounters_records`.`date` >= `$new_table`.`begin_date` 
                   AND `glpi_plugin_printercounters_records`.`date` <= `$new_table`.`end_date`)";
            return $out;
            break;
      }
   }

   /**
   * Actions done after add
   */
   function post_addItem() {
      $config = PluginPrintercountersConfig::getInstance();
      if ($config['set_first_record']) {
         $record = new PluginPrintercountersRecord($this->fields['itemtype'], $this->fields['items_id']);
         $record->setFirstRecord($this->fields['id'], $this->fields['plugin_printercounters_recordmodels_id']);
      }

      parent::post_addItem();
   }

   /**
   * Actions done before add
   *
   * @param type $input
   * @return boolean
   */
   function prepareInputForAdd($input) {

      // Default authentication
      $snmpAuthentication      = new PluginPrintercountersSnmpauthentication();
      $defaultAuthentification = $snmpAuthentication->getDefaultAuthentication();
      if (!empty($defaultAuthentification)) {
         $input['plugin_printercounters_snmpauthentications_id'] = $defaultAuthentification;
      }

      if (!$this->checkMandatoryFields($input)) {
         return false;
      }

      return $input;
   }

   /**
   * Actions done before update
   *
   * @param type $input
   * @return boolean
   */
   function prepareInputForUpdate($input) {
      if (!$this->checkMandatoryFields($input)) {
         return false;
      }

      if (isset($input['_clean_records'])
              && isset($this->fields['plugin_printercounters_recordmodels_id'])
              && isset($input['plugin_printercounters_recordmodels_id'])
              && isset($input['id'])
              && $this->input['plugin_printercounters_recordmodels_id']
              != $this->fields['plugin_printercounters_recordmodels_id']) {

         $temp = new PluginPrintercountersRecord();
         $temp->deleteByCriteria(['last_recordmodels_id' => $this->fields['plugin_printercounters_recordmodels_id'],
             'plugin_printercounters_items_recordmodels_id' => $input['id']], 1);
      }

      return $input;
   }

   /**
   * Check mandatory fields
   *
   * @param type $input
   * @return boolean
   */
   function checkMandatoryFields($input) {
      $msg     = [];
      $checkKo = false;
      $dbu     = new DbUtils();

      $item = $dbu->getItemForItemtype($this->itemtype);

      $mandatory_fields = ['plugin_printercounters_snmpauthentications_id'  => PluginPrintercountersSnmpauthentication::getTypeName(),
                                'plugin_printercounters_recordmodels_id'         => PluginPrintercountersRecordmodel::getTypeName(),
                                'items_id'                                       => $item::getTypeName(),
                                'periodicity'                                    => __('Periodicity', 'printercounters')];

      foreach ($input as $key => $value) {
         if (array_key_exists($key, $mandatory_fields)) {
            if (empty($value)) {
               $msg[] = $mandatory_fields[$key];
               $checkKo = true;
            }
         }
      }

      if ($checkKo) {
         Session::addMessageAfterRedirect(sprintf(__("Mandatory fields are not filled. Please correct: %s"), implode(', ', $msg)), true, ERROR);
         return false;
      }
      return true;
   }

}
