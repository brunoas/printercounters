<?php
/*
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

class PluginPrintercountersExpected_Yield {

   static $table = 'glpi_plugin_printercounters_expected_yields';

   /** Available SNMP color values (RFC 1759 prtMarkerColorantValue). */
   static $snmp_colors = [
      ''        => '---',
      'black'   => 'Black',
      'cyan'    => 'Cyan',
      'magenta' => 'Magenta',
      'yellow'  => 'Yellow',
      'other'   => 'Other',
   ];

   /**
    * POST_ITEM_FORM hook handler.
    * Adds "Expected Yield (ISO 5%)" field to CartridgeItemType form.
    */
   static function postItemForm($params) {
      if (!isset($params['item']) || !($params['item'] instanceof CartridgeItemType)) {
         return;
      }

      $item = $params['item'];
      $cartridgeitemtypes_id = $item->getID();

      if ($cartridgeitemtypes_id <= 0) {
         return;
      }

      $data = self::getFullForCartridgeItemType($cartridgeitemtypes_id);
      $value = (int)$data['expected_yield'];
      $current_color = $data['snmp_color'] ?? '';
      $readonly = !Session::haveRight('cartridge', UPDATE);
      $disabled = $readonly ? 'disabled' : '';
      $rand = mt_rand();
      $id_yield = 'expected_yield_' . $rand;
      $id_color = 'snmp_color_' . $rand;
      $label_yield = __('Expected yield (ISO 5%)', 'printercounters');
      $label_color = __('SNMP color', 'printercounters');

      echo '<div id="yeldformtable">';
      echo '<div class="card-body d-flex flex-wrap">';
      echo '<div class="col-12 col-xxl-12 flex-column">';
      echo '<div class="d-flex flex-row flex-wrap flex-xl-nowrap">';
      echo '<div class="row flex-row align-items-start flex-grow-1" style="min-width: 0;">';
      echo '<div class="row flex-row">';

      // Expected yield field
      echo '<div class="form-field row align-items-center col-12 col-sm-6 mb-2">';
      echo '<label class="col-form-label col-xxl-5 text-xxl-end" for="' . $id_yield . '">' . $label_yield . '</label>';
      echo '<div class="col-xxl-7 field-container">';
      echo '<input type="number" id="' . $id_yield . '" class="form-control "' . $disabled . ' name="expected_yield" min="0" step="1" value="' . $value . '">';
      echo '</div>';
      echo '</div>';

      // SNMP color dropdown
      echo '<div class="form-field row align-items-center col-12 col-sm-6 mb-2">';
      echo '<label class="col-form-label col-xxl-5 text-xxl-end" for="' . $id_color . '">' . $label_color . '</label>';
      echo '<div class="col-xxl-7 field-container">';
      echo '<select id="' . $id_color . '" class="form-select" ' . $disabled . ' name="snmp_color">';
      foreach (self::$snmp_colors as $key => $label_text) {
         $selected = ($key === $current_color) ? ' selected' : '';
         echo '<option value="' . htmlspecialchars($key) . '"' . $selected . '>' . htmlspecialchars($label_text) . '</option>';
      }
      echo '</select>';
      echo '</div>';
      echo '</div>';

      echo '</div>';
      echo '</div>';
      echo '</div>';
      echo '</div>';
      echo '</div>';
      echo '</div>';
   }

   /**
    * Hook handler for CartridgeItemType update — saves expected_yield and snmp_color.
    */
   static function preItemUpdate($item) {
      if (!($item instanceof CartridgeItemType)) {
         return;
      }
      if (isset($_POST['expected_yield']) || isset($_POST['snmp_color'])) {
         self::save(
            $item->getID(),
            (int)($_POST['expected_yield'] ?? 0),
            $_POST['snmp_color'] ?? ''
         );
      }
   }

   /**
    * Hook handler for CartridgeItemType add — saves expected_yield and snmp_color.
    */
   static function itemAdd($item) {
      if (!($item instanceof CartridgeItemType)) {
         return;
      }
      if (isset($_POST['expected_yield']) || isset($_POST['snmp_color'])) {
         self::save(
            $item->getID(),
            (int)($_POST['expected_yield'] ?? 0),
            $_POST['snmp_color'] ?? ''
         );
      }
   }

   /**
    * Get expected yield for a single cartridge item type.
    */
   static function getForCartridgeItemType($cartridgeitemtypes_id) {
      $data = self::getFullForCartridgeItemType($cartridgeitemtypes_id);
      return (int)$data['expected_yield'];
   }

   /**
    * Get full record (expected_yield + snmp_color) for a cartridge item type.
    */
   static function getFullForCartridgeItemType($cartridgeitemtypes_id) {
      global $DB;

      $result = $DB->doQuery(
         "SELECT expected_yield, snmp_color FROM " . self::$table .
         " WHERE cartridgeitemtypes_id = " . (int)$cartridgeitemtypes_id
      );

      if ($result && $data = $result->fetch_assoc()) {
         return [
            'expected_yield' => (int)$data['expected_yield'],
            'snmp_color'     => $data['snmp_color'] ?? '',
         ];
      }
      return ['expected_yield' => 0, 'snmp_color' => ''];
   }

   /**
    * Get expected yields for multiple cartridge item types.
    *
    * @param array $cartridgeitemtypes_ids  Array of cartridgeitemtypes IDs
    * @return array  [cartridgeitemtypes_id => expected_yield, ...]
    */
   static function getForCartridgeItemTypes($cartridgeitemtypes_ids) {
      global $DB;

      if (empty($cartridgeitemtypes_ids)) {
         return [];
      }

      $ids = array_map('intval', $cartridgeitemtypes_ids);
      $result = $DB->doQuery(
         "SELECT cartridgeitemtypes_id, expected_yield FROM " . self::$table .
         " WHERE cartridgeitemtypes_id IN (" . implode(',', $ids) . ")"
      );

      $yields = [];
      while ($data = $result->fetch_assoc()) {
         $yields[(int)$data['cartridgeitemtypes_id']] = (int)$data['expected_yield'];
      }
      return $yields;
   }

   /**
    * Get snmp_color mapped by cartridgeitemtypes_id.
    *
    * @param array $cartridgeitemtypes_ids
    * @return array  [cartridgeitemtypes_id => snmp_color, ...]
    */
   static function getColorsByCartridgeItemTypes($cartridgeitemtypes_ids) {
      global $DB;

      if (empty($cartridgeitemtypes_ids)) {
         return [];
      }

      $ids = array_map('intval', $cartridgeitemtypes_ids);
      $result = $DB->doQuery(
         "SELECT cartridgeitemtypes_id, snmp_color FROM " . self::$table .
         " WHERE cartridgeitemtypes_id IN (" . implode(',', $ids) . ")" .
         " AND snmp_color != ''"
      );

      $colors = [];
      while ($data = $result->fetch_assoc()) {
         $colors[(int)$data['cartridgeitemtypes_id']] = $data['snmp_color'];
      }
      return $colors;
   }

   /**
    * Save expected yield and snmp_color for a cartridge item type (insert or update).
    */
   static function save($cartridgeitemtypes_id, $expected_yield, $snmp_color = '') {
      global $DB;

      $cartridgeitemtypes_id = (int)$cartridgeitemtypes_id;
      $expected_yield = (int)$expected_yield;
      $snmp_color = isset(self::$snmp_colors[$snmp_color]) ? $snmp_color : '';

      if ($cartridgeitemtypes_id <= 0) {
         return;
      }

      $safe_color = $DB->escape($snmp_color);

      if (self::existsForCartridgeItemType($cartridgeitemtypes_id)) {
         $DB->doQuery(
            "UPDATE " . self::$table .
            " SET expected_yield = $expected_yield, snmp_color = '$safe_color'" .
            " WHERE cartridgeitemtypes_id = $cartridgeitemtypes_id"
         );
      } else {
         $DB->doQuery(
            "INSERT INTO " . self::$table .
            " (cartridgeitemtypes_id, expected_yield, snmp_color)" .
            " VALUES ($cartridgeitemtypes_id, $expected_yield, '$safe_color')"
         );
      }
   }

   /**
    * Check if a record exists for a cartridge item type.
    */
   static function existsForCartridgeItemType($cartridgeitemtypes_id) {
      global $DB;

      $result = $DB->doQuery(
         "SELECT id FROM " . self::$table .
         " WHERE cartridgeitemtypes_id = " . (int)$cartridgeitemtypes_id
      );
      return $result && $result->fetch_assoc();
   }

   /**
    * Install — create table.
    */
   static function install() {
      global $DB;

      if (!$DB->tableExists(self::$table)) {
         $DB->doQuery("
            CREATE TABLE `" . self::$table . "` (
               `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
               `cartridgeitemtypes_id` INT(10) UNSIGNED NOT NULL,
               `expected_yield` INT(11) NOT NULL DEFAULT 0,
               `snmp_color` VARCHAR(50) NOT NULL DEFAULT '',
               PRIMARY KEY (`id`),
               UNIQUE KEY `cartridgeitemtypes_id` (`cartridgeitemtypes_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
         ");
      }

      // Migration: rename column if upgrading from cartridgeitems_id version
      if ($DB->tableExists(self::$table)
          && $DB->fieldExists(self::$table, 'cartridgeitems_id')
          && !$DB->fieldExists(self::$table, 'cartridgeitemtypes_id')) {
         $DB->doQuery("
            ALTER TABLE `" . self::$table . "`
            CHANGE `cartridgeitems_id` `cartridgeitemtypes_id` INT(10) UNSIGNED NOT NULL,
            DROP INDEX `cartridgeitems_id`,
            ADD UNIQUE KEY `cartridgeitemtypes_id` (`cartridgeitemtypes_id`)
         ");
      }

      // Migration: add snmp_color column if missing
      if ($DB->tableExists(self::$table)
          && !$DB->fieldExists(self::$table, 'snmp_color')) {
         $DB->doQuery("
            ALTER TABLE `" . self::$table . "`
            ADD `snmp_color` VARCHAR(50) NOT NULL DEFAULT ''
         ");
      }
   }

   /**
    * Uninstall — drop table.
    */
   static function uninstall() {
      global $DB;
      $DB->dropTable(self::$table);
   }
}
