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

class PluginPrintercountersCartridge_Yield {

   /**
    * POST_SHOW_TAB hook handler.
    * When displaying the Cartridge tab on a Printer, injects a script
    * that corrects the "Printed pages" column to use per-model yield.
    */
   static function postShowTab($params) {
      if (!isset($params['item']) || !($params['item'] instanceof Printer)) {
         return;
      }
      if (!isset($params['options']['itemtype']) || $params['options']['itemtype'] !== 'Cartridge') {
         return;
      }

      $printer = $params['item'];
      $printers_id = $printer->getField('id');
      $yields = self::getYieldsByModel($printers_id, $printer->fields['init_pages_counter']);

      if (empty($yields)) {
         return;
      }

      self::injectCorrectionScript($yields);
   }

   /**
    * Calculate per-model yield for each worn cartridge of a printer.
    *
    * For each cartridge, finds the previous cartridge of the same model
    * (cartridgeitems_id) and computes: yield = pages - prev_same_model_pages.
    * If no previous same-model cartridge exists, uses init_pages_counter.
    *
    * @param int $printers_id      Printer ID
    * @param int $init_pages       Printer's init_pages_counter
    * @return array  [cartridge_id => yield_value, ...]
    */
   static function getYieldsByModel($printers_id, $init_pages = 0) {
      global $DB;

      $result = $DB->doQuery("
         SELECT
            c.id,
            c.pages,
            c.pages - COALESCE(
               (SELECT c2.pages
                FROM glpi_cartridges c2
                WHERE c2.printers_id = c.printers_id
                  AND c2.cartridgeitems_id = c.cartridgeitems_id
                  AND c2.date_out IS NOT NULL
                  AND (c2.date_out < c.date_out OR (c2.date_out = c.date_out AND c2.id < c.id))
                ORDER BY c2.date_out DESC, c2.id DESC
                LIMIT 1),
               " . (int)$init_pages . "
            ) AS yield_by_model
         FROM glpi_cartridges c
         WHERE c.printers_id = " . (int)$printers_id . "
           AND c.date_out IS NOT NULL
      ");

      $yields = [];
      while ($data = $result->fetch_assoc()) {
         $yield = (int)$data['yield_by_model'];
         $yields[(int)$data['id']] = ($yield > 0) ? $yield : 0;
      }

      return $yields;
   }

   /**
    * Inject inline script to replace "Printed pages" values in the DOM.
    *
    * @param array $yields  [cartridge_id => yield_value, ...]
    */
   static function injectCorrectionScript($yields) {
      $json = json_encode($yields);
      echo <<<SCRIPT
<script>
(function() {
   var yields = {$json};
   var tables = document.querySelectorAll('table.table');
   tables.forEach(function(table) {
      // Worn cartridges table has more columns than used cartridges (End date, Printer counter, Printed pages)
      // Identify it by having tbody rows with data-id whose td count is > 7 (checkbox + 7 data cols = 8+)
      var firstRow = table.querySelector('tbody tr[data-id]');
      if (!firstRow) return;
      var colCount = firstRow.querySelectorAll('td').length;
      if (colCount < 8) return; // Not the worn cartridges table
      // "Printed pages" is always the last column
      var pagesCol = colCount - 1;

      var rows = table.querySelectorAll('tbody tr[data-id]');
      var total = 0;
      var count = 0;
      rows.forEach(function(row) {
         var id = parseInt(row.getAttribute('data-id'));
         if (yields.hasOwnProperty(id)) {
            var cells = row.querySelectorAll('td');
            if (cells[pagesCol]) {
               cells[pagesCol].textContent = yields[id].toLocaleString();
               total += yields[id];
               if (yields[id] > 0) count++;
            }
         }
      });

      // Update average in footer
      if (count > 0) {
         var footerCells = table.querySelectorAll('tfoot td, tfoot th');
         if (footerCells[pagesCol]) {
            var text = footerCells[pagesCol].textContent || footerCells[pagesCol].innerText;
            var lines = text.split('\\n');
            if (lines.length >= 1) {
               footerCells[pagesCol].textContent = lines[0] + '\\n' + Math.round(total / count);
            }
         }
      }
   });
})();
</script>
SCRIPT;
   }
}
