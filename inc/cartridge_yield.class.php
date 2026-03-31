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
      self::injectSummaryTable($printers_id, $printer->fields['init_pages_counter']);
   }

   /**
    * Calculate per-type yield for each worn cartridge of a printer.
    *
    * For each cartridge, finds the previous cartridge of the same type
    * (cartridgeitemtypes_id) and computes: yield = pages - prev_same_type_pages.
    * If cartridgeitemtypes_id is 0 (unset), falls back to grouping by cartridgeitems_id.
    * If no previous same-type cartridge exists, uses init_pages_counter.
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
                JOIN glpi_cartridgeitems ci2 ON ci2.id = c2.cartridgeitems_id
                WHERE c2.printers_id = c.printers_id
                  AND c2.date_out IS NOT NULL
                  AND (c2.date_out < c.date_out OR (c2.date_out = c.date_out AND c2.id < c.id))
                  AND (
                     (ci.cartridgeitemtypes_id > 0 AND ci2.cartridgeitemtypes_id = ci.cartridgeitemtypes_id)
                     OR (ci.cartridgeitemtypes_id = 0 AND c2.cartridgeitems_id = c.cartridgeitems_id)
                  )
                ORDER BY c2.date_out DESC, c2.id DESC
                LIMIT 1),
               " . (int)$init_pages . "
            ) AS yield_by_model
         FROM glpi_cartridges c
         JOIN glpi_cartridgeitems ci ON ci.id = c.cartridgeitems_id
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
    * Get yield summary grouped by cartridge type for a printer.
    *
    * Groups by cartridgeitemtypes_id when set, falls back to cartridgeitems_id.
    * Uses the same type-aware yield calculation as getYieldsByModel().
    *
    * @param int $printers_id  Printer ID
    * @param int $init_pages   Printer's init_pages_counter
    * @return array  [group_id => ['name' => ..., 'count' => ..., 'total' => ..., 'avg' => ..., 'expected' => ...], ...]
    */
   static function getSummaryByModel($printers_id, $init_pages = 0) {
      global $DB;

      $result = $DB->doQuery("
         SELECT
            CASE WHEN ci.cartridgeitemtypes_id > 0 THEN ci.cartridgeitemtypes_id ELSE ci.id END AS group_id,
            CASE WHEN ci.cartridgeitemtypes_id > 0 THEN cit.name ELSE ci.name END AS group_name,
            ci.cartridgeitemtypes_id,
            COUNT(c.id) AS cartridge_count,
            SUM(
               GREATEST(0, c.pages - COALESCE(
                  (SELECT c2.pages
                   FROM glpi_cartridges c2
                   JOIN glpi_cartridgeitems ci2 ON ci2.id = c2.cartridgeitems_id
                   WHERE c2.printers_id = c.printers_id
                     AND c2.date_out IS NOT NULL
                     AND (c2.date_out < c.date_out OR (c2.date_out = c.date_out AND c2.id < c.id))
                     AND (
                        (ci.cartridgeitemtypes_id > 0 AND ci2.cartridgeitemtypes_id = ci.cartridgeitemtypes_id)
                        OR (ci.cartridgeitemtypes_id = 0 AND c2.cartridgeitems_id = c.cartridgeitems_id)
                     )
                   ORDER BY c2.date_out DESC, c2.id DESC
                   LIMIT 1),
                  " . (int)$init_pages . "
               ))
            ) AS total_printed
         FROM glpi_cartridges c
         JOIN glpi_cartridgeitems ci ON ci.id = c.cartridgeitems_id
         LEFT JOIN glpi_cartridgeitemtypes cit ON cit.id = ci.cartridgeitemtypes_id
         WHERE c.printers_id = " . (int)$printers_id . "
           AND c.date_out IS NOT NULL
         GROUP BY group_id, group_name, ci.cartridgeitemtypes_id
         ORDER BY group_name
      ");

      $summary = [];
      $type_ids = [];
      while ($data = $result->fetch_assoc()) {
         $gid = (int)$data['group_id'];
         $has_type = ((int)$data['cartridgeitemtypes_id'] > 0);
         $count = (int)$data['cartridge_count'];
         $total = (int)$data['total_printed'];
         $summary[$gid] = [
            'name'     => $data['group_name'],
            'count'    => $count,
            'total'    => $total,
            'avg'      => ($count > 0) ? round($total / $count) : 0,
            'expected' => 0,
            'is_type'  => $has_type,
         ];
         if ($has_type) {
            $type_ids[] = $gid;
         }
      }

      if (!empty($type_ids)) {
         $expected = PluginPrintercountersExpected_Yield::getForCartridgeItemTypes($type_ids);
         foreach ($expected as $tid => $value) {
            if (isset($summary[$tid])) {
               $summary[$tid]['expected'] = $value;
            }
         }
      }

      return $summary;
   }

   /**
    * Inject the summary table HTML after the worn cartridges table.
    */
   static function injectSummaryTable($printers_id, $init_pages = 0) {
      $summary = self::getSummaryByModel($printers_id, $init_pages);

      if (empty($summary)) {
         return;
      }

      $header_type     = __('Cartridge model', 'printercounters');
      $header_expected = __('Expected yield (ISO 5%)', 'printercounters');
      $header_total    = __('Total printed', 'printercounters');
      $header_avg      = __('Average per cartridge', 'printercounters');
      $header_coverage = __('Actual coverage (%)', 'printercounters');
      $title           = __('Yield summary by cartridge model', 'printercounters');

      $rows = '';
      foreach ($summary as $data) {
         $expected_str = ($data['expected'] > 0) ? number_format($data['expected'], 0, ',', '.') : '&mdash;';
         $total_str    = number_format($data['total'], 0, ',', '.');
         $avg_str      = number_format($data['avg'], 0, ',', '.');

         if ($data['expected'] > 0 && $data['avg'] > 0) {
            $coverage = ($data['expected'] / $data['avg']) * 5.0;
            $coverage_str = number_format($coverage, 2, ',', '.') . '%';
         } else {
            $coverage_str = '&mdash;';
         }

         $name = htmlspecialchars($data['name']);
         $count_str = ' (' . $data['count'] . ')';

         $rows .= "<tr>";
         $rows .= "<td>{$name}{$count_str}</td>";
         $rows .= "<td class=\"text-end\">{$expected_str}</td>";
         $rows .= "<td class=\"text-end\">{$total_str}</td>";
         $rows .= "<td class=\"text-end\">{$avg_str}</td>";
         $rows .= "<td class=\"text-end\">{$coverage_str}</td>";
         $rows .= "</tr>";
      }

      echo <<<HTML
<div class="mt-3">
   <table class="table table-hover">
      <thead>
         <tr>
            <th colspan="5" class="text-center"><strong>{$title}</strong></th>
         </tr>
         <tr>
            <th>{$header_type}</th>
            <th class="text-end">{$header_expected}</th>
            <th class="text-end">{$header_total}</th>
            <th class="text-end">{$header_avg}</th>
            <th class="text-end">{$header_coverage}</th>
         </tr>
      </thead>
      <tbody>
         {$rows}
      </tbody>
   </table>
</div>
HTML;
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
