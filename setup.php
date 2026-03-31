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

use Glpi\Plugin\Hooks;

define('PLUGIN_PRINTERCOUNTERS_VERSION', '3.2.1');

// Minimal GLPI version, inclusive
define('PLUGIN_PRINTERCOUNTERS_MIN_GLPI', '11.0.0');
// Maximum GLPI version, exclusive
define('PLUGIN_PRINTERCOUNTERS_MAX_GLPI', '11.0.99');

if (!defined("PLUGIN_PRINTERCOUNTERS_DIR")) {
   define("PLUGIN_PRINTERCOUNTERS_DIR", Plugin::getPhpDir("printercounters"));
   define("PLUGIN_PRINTERCOUNTERS_NOTFULL_DIR", Plugin::getPhpDir("printercounters",false));
   define("PLUGIN_PRINTERCOUNTERS_WEBDIR", Plugin::getWebDir("printercounters"));
   define("PLUGIN_PRINTERCOUNTERS_NOTFULL_WEBDIR", Plugin::getWebDir("printercounters",false));
}

// Init the hooks of the plugins -Needed
function plugin_init_printercounters() {
   global $PLUGIN_HOOKS, $CFG_GLPI;

   $PLUGIN_HOOKS['change_profile']['printercounters'] = ['PluginPrintercountersProfile', 'changeProfile'];

   if (isset($_SESSION['glpiactiveprofile']['interface'])
       && $_SESSION['glpiactiveprofile']['interface'] == 'central') {
      $PLUGIN_HOOKS[Hooks::ADD_CSS]['printercounters']          = ['printercounters.css'];
      $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['printercounters']   = ['printercounters.js'];
   }
   if (Session::getLoginUserID()) {

      // Add tabs
      Plugin::registerClass('PluginPrintercountersProfile', ['addtabon' => 'Profile']);
      Plugin::registerClass('PluginPrintercountersCountertype_Recordmodel', ['addtabon' => 'PluginPrintercountersRecordmodel']);
      Plugin::registerClass('PluginPrintercountersItem_Recordmodel', ['addtabon' => 'PluginPrintercountersRecordmodel']);
      Plugin::registerClass('PluginPrintercountersSysdescr', ['addtabon' => 'PluginPrintercountersRecordmodel']);
      Plugin::registerClass('PluginPrintercountersPagecost', ['addtabon' => 'PluginPrintercountersBillingmodel']);
      Plugin::registerClass('PluginPrintercountersItem_Billingmodel', ['addtabon' => 'PluginPrintercountersBillingmodel']);
      Plugin::registerClass('PluginPrintercountersItem_Ticket', ['addtabon' => 'PluginPrintercountersConfig']);
      Plugin::registerClass('PluginPrintercountersProcess', ['addtabon' => 'PluginPrintercountersConfig']);
      Plugin::registerClass('PluginPrintercountersAdditional_data', ['notificationtemplates_types' => true]);

      if (Session::haveRight("plugin_printercounters", READ) && class_exists('PluginPrintercountersProfile')) {
         Plugin::registerClass('PluginPrintercountersItem_Recordmodel', ['addtabon' => 'Printer']);
         Plugin::registerClass('PluginPrintercountersItem_Billingmodel', ['addtabon' => 'Printer']);

         $PLUGIN_HOOKS['use_massive_action']['printercounters'] = 1;

         // Injection
         $PLUGIN_HOOKS['plugin_datainjection_populate']['printercounters'] = 'plugin_datainjection_populate_printercounters';

         $PLUGIN_HOOKS['menu_toadd']['printercounters']          = ['tools' => 'PluginPrintercountersMenu'];
         if (Session::haveRight("plugin_printercounters", UPDATE)) {
            $PLUGIN_HOOKS['config_page']['printercounters'] = 'front/config.form.php';
         }
      }

      // Pre item purge
      $PLUGIN_HOOKS[Hooks::PRE_ITEM_PURGE]['printercounters'] = [
         'PluginPrintercountersRecordmodel'             => 'plugin_pre_item_purge_printercounters',
         'PluginPrintercountersBillingmodel'            => 'plugin_pre_item_purge_printercounters',
         'PluginPrintercountersCountertype'             => 'plugin_pre_item_purge_printercounters',
         'PluginPrintercountersItem_Recordmodel'        => 'plugin_pre_item_purge_printercounters',
         'PluginPrintercountersRecord'                  => 'plugin_pre_item_purge_printercounters',
         'PluginPrintercountersCountertype_Recordmodel' => 'plugin_pre_item_purge_printercounters',
         'Printer'                                      => 'plugin_pre_item_purge_printercounters',
         'Ticket'                                       => 'plugin_pre_item_purge_printercounters',
         'Entity'                                       => 'plugin_pre_item_purge_printercounters'];

      // Post item purge
      $PLUGIN_HOOKS[Hooks::ITEM_PURGE]['printercounters'] = [
         'PluginPrintercountersCounter' => 'plugin_item_purge_printercounters'];

      // Pre item delete
      $PLUGIN_HOOKS[Hooks::PRE_ITEM_DELETE]['printercounters'] = [
         'Printer' => 'plugin_item_delete_printercounters'];

      // Item transfer
      $PLUGIN_HOOKS[Hooks::ITEM_TRANSFER]['printercounters'] = 'plugin_item_transfer_printercounters';

      // Correct cartridge yield calculation (per model) on Printer > Cartridges tab
      $PLUGIN_HOOKS[Hooks::POST_SHOW_TAB]['printercounters'] = 'plugin_printercounters_post_show_tab';
   }
}

// Get the name and the version of the plugin - Needed
function plugin_version_printercounters() {
   return [
      'name'         => __('Printer counters', 'printercounters'),
      'version'      => PLUGIN_PRINTERCOUNTERS_VERSION,
      'author'       => "<a href='https://blogglpi.infotel.com'>Infotel</a>",
      'license'      => 'GPLv2+',
      'homepage'     => 'https://github.com/InfotelGLPI/printercounters',
      'requirements' => [
         'glpi' => [
            'min' => PLUGIN_PRINTERCOUNTERS_MIN_GLPI,
            'max' => PLUGIN_PRINTERCOUNTERS_MAX_GLPI,
         ],
         'php' => [
            'min' => '8.2',
            'exts' => ['snmp'],
         ]
      ]
   ];
}

