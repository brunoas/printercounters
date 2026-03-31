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

ini_set("memory_limit", "-1");
ini_set("max_execution_time", "0");

// GLPI 11 bootstrap
$glpi_root = realpath(__DIR__ . "/../../..");

require_once $glpi_root . '/src/Glpi/Application/ResourcesChecker.php';
(new \Glpi\Application\ResourcesChecker($glpi_root))->checkResources();

require_once $glpi_root . '/vendor/autoload.php';

$kernel = new \Glpi\Kernel\Kernel();
$kernel->boot();

if (!($DB instanceof DBmysql) || !$DB->connected) {
   die("ERROR: Database connection failed.\n");
}

if (!Config::isLegacyConfigurationLoaded()) {
   die("ERROR: Unable to load GLPI configuration.\n");
}

// Check Memory_limit
$mem = Toolbox::getMemoryLimit();
if (($mem > 0) && ($mem < (64 * 1024 * 1024))) {
   die("PHP memory_limit = ".$mem." - "."A minimum of 64Mio is commonly required for GLPI.\n\n");
}

if (Plugin::isPluginActive("printercounters")) {

   // Clean record
   $record = new PluginPrintercountersRecord();
   if ($record->cleanRecords()) {
      echo __('Records cleaned', 'printercounters');
   } else {
      echo __('No records to clean', 'printercounters');
   }

} else {
   echo __('Plugin disabled or automatic record disabled', 'printercounters');
   exit(1);
}

