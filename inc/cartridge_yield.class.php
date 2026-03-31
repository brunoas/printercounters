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
      $init_pages = $printer->fields['init_pages_counter'];
      $last_pages = $printer->fields['last_pages_counter'];

      // Worn cartridges data
      $yields = self::getYieldsByModel($printers_id, $init_pages);
      $expectedYields = self::getExpectedYieldsByCartridge($printers_id);

      // Used (in-use) cartridges data
      $usedData = self::getUsedCartridgesData($printers_id, $init_pages, $last_pages);

      if (empty($yields) && empty($usedData)) {
         return;
      }

      self::injectCorrectionScript($yields, $expectedYields, $usedData, $last_pages);

      if (!empty($yields)) {
         self::injectSummaryTable($printers_id, $init_pages);
      }
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
    * Get data for in-use cartridges: toner level, pages printed, coverage, remaining pages.
    *
    * @param int $printers_id      Printer ID
    * @param int $init_pages       Printer's init_pages_counter
    * @param int $last_pages       Printer's last_pages_counter (current counter)
    * @return array  [cartridge_id => ['toner_consumed'=>..., 'pages_printed'=>..., 'coverage'=>..., 'remaining'=>...], ...]
    */
   static function getUsedCartridgesData($printers_id, $init_pages = 0, $last_pages = 0) {
      global $DB;

      // Get in-use cartridges with their type info and date_use
      $result = $DB->doQuery("
         SELECT c.id, c.cartridgeitems_id, ci.cartridgeitemtypes_id, c.date_use
         FROM glpi_cartridges c
         JOIN glpi_cartridgeitems ci ON ci.id = c.cartridgeitems_id
         WHERE c.printers_id = " . (int)$printers_id . "
           AND c.date_use IS NOT NULL
           AND c.date_out IS NULL
      ");

      if (!$result) {
         return [];
      }

      $cartridges = [];
      $type_ids = [];
      while ($row = $result->fetch_assoc()) {
         $cartridges[] = $row;
         $tid = (int)$row['cartridgeitemtypes_id'];
         if ($tid > 0) {
            $type_ids[$tid] = $tid;
         }
      }

      if (empty($cartridges)) {
         return [];
      }

      // Get SNMP color mapping for these cartridge types
      $color_map = [];
      $expected_map = [];
      if (!empty($type_ids)) {
         $color_map = PluginPrintercountersExpected_Yield::getColorsByCartridgeItemTypes(array_values($type_ids));
         $expected_map = PluginPrintercountersExpected_Yield::getForCartridgeItemTypes(array_values($type_ids));
      }

      // Get toner levels for this printer from additionals_datas
      $toner_levels = self::getTonerLevels($printers_id);

      // For each in-use cartridge, calculate data
      $data = [];
      foreach ($cartridges as $cart) {
         $cid = (int)$cart['id'];
         $tid = (int)$cart['cartridgeitemtypes_id'];
         $items_id = (int)$cart['cartridgeitems_id'];

         // Get toner level via snmp_color mapping
         $toner_value = null;
         if ($tid > 0 && isset($color_map[$tid]) && isset($toner_levels[$color_map[$tid]])) {
            $toner_value = (int)$toner_levels[$color_map[$tid]];
         }

         // Pages printed = current counter - last worn cartridge of same type
         $prev_pages = self::getLastWornPages($printers_id, $tid, $items_id, $init_pages);
         $pages_printed = max(0, $last_pages - $prev_pages);

         $toner_consumed = ($toner_value !== null) ? (100 - $toner_value) : null;

         // Expected yield for coverage calculation
         $expected = ($tid > 0 && isset($expected_map[$tid])) ? $expected_map[$tid] : 0;

         // Coverage: (expected_yield / estimated_total_pages) * 5
         // estimated_total_pages = pages_printed / (consumed / 100)
         $coverage = null;
         if ($toner_consumed !== null && $toner_consumed > 0 && $expected > 0 && $pages_printed > 0) {
            $estimated_total = $pages_printed / ($toner_consumed / 100);
            $coverage = round(($expected / $estimated_total) * 5.0, 2);
         }

         // Remaining pages: (pages_printed / consumed%) * remaining%
         $remaining = null;
         if ($toner_consumed !== null && $toner_consumed > 0 && $pages_printed > 0) {
            $remaining = round(($pages_printed / $toner_consumed) * (100 - $toner_consumed));
         }

         $data[$cid] = [
            'toner_consumed' => $toner_consumed,
            'pages_printed'  => $pages_printed,
            'coverage'       => $coverage,
            'date_use'       => $cart['date_use'],
            'remaining'      => $remaining,
         ];
      }

      return $data;
   }

   /**
    * Get toner levels for a printer from additionals_datas.
    *
    * @param int $printers_id
    * @return array  [snmp_color => toner_value, ...]  e.g. ['black' => 75, 'cyan' => 50]
    */
   static function getTonerLevels($printers_id) {
      global $DB;

      $result = $DB->doQuery("
         SELECT ad.sub_type, ad.value
         FROM glpi_plugin_printercounters_additionals_datas ad
         JOIN glpi_plugin_printercounters_items_recordmodels irm
            ON irm.id = ad.plugin_printercounters_items_recordmodels_id
         WHERE irm.items_id = " . (int)$printers_id . "
           AND irm.itemtype = 'Printer'
           AND ad.type = 'toner'
      ");

      $levels = [];
      if ($result) {
         $colors = ['black', 'cyan', 'magenta', 'yellow'];
         while ($row = $result->fetch_assoc()) {
            foreach ($colors as $color) {
               if (preg_match('/(' . $color . ')/i', $row['sub_type'])) {
                  $levels[$color] = (int)$row['value'];
                  break;
               }
            }
         }
      }
      return $levels;
   }

   /**
    * Get page counter from the last worn cartridge of the same type.
    *
    * @param int $printers_id
    * @param int $cartridgeitemtypes_id
    * @param int $cartridgeitems_id  Fallback when type is 0
    * @param int $init_pages         Fallback when no previous cartridge
    * @return int
    */
   static function getLastWornPages($printers_id, $cartridgeitemtypes_id, $cartridgeitems_id, $init_pages = 0) {
      global $DB;

      if ($cartridgeitemtypes_id > 0) {
         $condition = "ci2.cartridgeitemtypes_id = " . (int)$cartridgeitemtypes_id;
      } else {
         $condition = "c2.cartridgeitems_id = " . (int)$cartridgeitems_id;
      }

      $result = $DB->doQuery("
         SELECT c2.pages
         FROM glpi_cartridges c2
         JOIN glpi_cartridgeitems ci2 ON ci2.id = c2.cartridgeitems_id
         WHERE c2.printers_id = " . (int)$printers_id . "
           AND c2.date_out IS NOT NULL
           AND $condition
         ORDER BY c2.date_out DESC, c2.id DESC
         LIMIT 1
      ");

      if ($result && $row = $result->fetch_assoc()) {
         return (int)$row['pages'];
      }
      return (int)$init_pages;
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

      $header_type     = _n('Cartridge model', 'Cartridge models', 1);
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
    * Get expected yield for each worn cartridge based on its cartridgeitemtypes_id.
    *
    * @param int $printers_id  Printer ID
    * @return array  [cartridge_id => expected_yield, ...]
    */
   static function getExpectedYieldsByCartridge($printers_id) {
      global $DB;

      $result = $DB->doQuery("
         SELECT c.id, COALESCE(ey.expected_yield, 0) AS expected_yield
         FROM glpi_cartridges c
         JOIN glpi_cartridgeitems ci ON ci.id = c.cartridgeitems_id
         LEFT JOIN " . PluginPrintercountersExpected_Yield::$table . " ey
            ON ey.cartridgeitemtypes_id = ci.cartridgeitemtypes_id
            AND ci.cartridgeitemtypes_id > 0
         WHERE c.printers_id = " . (int)$printers_id . "
           AND c.date_out IS NOT NULL
      ");

      $expected = [];
      while ($data = $result->fetch_assoc()) {
         $expected[(int)$data['id']] = (int)$data['expected_yield'];
      }
      return $expected;
   }

   /**
    * Inject inline script for both worn and used cartridges tables.
    *
    * @param array $yields          [cartridge_id => yield_value, ...]
    * @param array $expectedYields  [cartridge_id => expected_yield, ...]
    * @param array $usedData        [cartridge_id => {toner_consumed, pages_printed, coverage, remaining}, ...]
    * @param int   $lastPages       Printer's last_pages_counter
    */
   static function injectCorrectionScript($yields, $expectedYields = [], $usedData = [], $lastPages = 0) {
      global $CFG_GLPI;
      $jsonYields = json_encode($yields);
      $jsonExpected = json_encode($expectedYields);
      $jsonUsed = json_encode($usedData);
      $dateFormat = (int)($CFG_GLPI['date_format'] ?? 0);
      $coverageHeader = __('Coverage (%)', 'printercounters');
      $hdrCounter = __('Printer counter', 'printercounters');
      $hdrConsumed = __('Consumed (%)', 'printercounters');
      $hdrPagesPrinted = __('Printed pages', 'printercounters');
      $hdrCoverage = __('Est. coverage (%)', 'printercounters');
      $hdrRemaining = __('Est. remaining pages', 'printercounters');
      $hdrEndDate = __('Est. end date', 'printercounters');
      echo <<<SCRIPT
<script>
(function() {
   var yields = {$jsonYields};
   var expected = {$jsonExpected};
   var usedData = {$jsonUsed};
   var lastPages = {$lastPages};
   var dateFormat = {$dateFormat};

   function addCell(row, refCell, text, attrs) {
      var td = document.createElement('td');
      td.setAttribute('colspan', '1');
      td.setAttribute('aria-label', '');
      if (text !== null) td.textContent = text;
      else td.innerHTML = '&mdash;';
      if (attrs) { for (var k in attrs) td.setAttribute(k, attrs[k]); }
      if (refCell && refCell.nextSibling) row.insertBefore(td, refCell.nextSibling);
      else row.appendChild(td);
      return td;
   }

   function addHeader(headerRow, refTh, text) {
      var th = document.createElement('th');
      th.textContent = text;
      if (refTh && refTh.nextSibling) headerRow.insertBefore(th, refTh.nextSibling);
      else headerRow.appendChild(th);
      return th;
   }

   function extendSuperHeader(table, headerRow, count) {
      var superRow = table.querySelector('thead tr:first-child');
      if (superRow && superRow !== headerRow) {
         var superTh = superRow.querySelector('th[colspan]');
         if (superTh) superTh.colSpan = parseInt(superTh.colSpan) + count;
      }
   }

   function hideColumn(table, colIndex) {
      table.querySelectorAll('thead tr:last-child th').forEach(function(th, i) {
         if (i === colIndex) th.style.display = 'none';
      });
      table.querySelectorAll('tbody tr').forEach(function(row) {
         var cells = row.querySelectorAll('td');
         if (cells[colIndex]) cells[colIndex].style.display = 'none';
      });
      var tfoot = table.querySelector('tfoot');
      if (tfoot) {
         tfoot.querySelectorAll('tr').forEach(function(row) {
            var cells = row.querySelectorAll('td, th');
            if (cells[colIndex]) cells[colIndex].style.display = 'none';
         });
      }
      // Adjust super header colspan
      var superRow = table.querySelector('thead tr:first-child');
      var headerRow = table.querySelector('thead tr:last-child');
      if (superRow && superRow !== headerRow) {
         var superTh = superRow.querySelector('th[colspan]');
         if (superTh) superTh.colSpan = Math.max(1, parseInt(superTh.colSpan) - 1);
      }
   }

   function formatDate(d) {
      var dd = String(d.getDate()).padStart(2, '0');
      var mm = String(d.getMonth() + 1).padStart(2, '0');
      var yyyy = d.getFullYear();
      // GLPI date_format: 0=YYYY-MM-DD, 1=DD-MM-YYYY, 2=MM-DD-YYYY
      if (dateFormat === 1) return dd + '-' + mm + '-' + yyyy;
      if (dateFormat === 2) return mm + '-' + dd + '-' + yyyy;
      return yyyy + '-' + mm + '-' + dd;
   }

   function estimateEndDate(dateUse, consumed) {
      if (!dateUse || consumed <= 0 || consumed >= 100) return null;
      var start = new Date(dateUse);
      var now = new Date();
      var daysUsed = Math.max(1, Math.round((now - start) / 86400000));
      var ratePerDay = consumed / daysUsed;
      var remaining = 100 - consumed;
      var daysRemaining = Math.round(remaining / ratePerDay);
      var endDate = new Date(now.getTime() + daysRemaining * 86400000);
      return formatDate(endDate);
   }

   var tables = document.querySelectorAll('table.table');
   tables.forEach(function(table) {
      var firstRow = table.querySelector('tbody tr[data-id]');
      if (!firstRow) return;
      var colCount = firstRow.querySelectorAll('td').length;

      if (colCount >= 8) {
         // === WORN CARTRIDGES TABLE ===
         var pagesCol = colCount - 1;
         var headerRow = table.querySelector('thead tr:last-child');
         if (headerRow) {
            var ths = headerRow.querySelectorAll('th');
            addHeader(headerRow, ths[pagesCol], '{$coverageHeader}');
         }
         extendSuperHeader(table, headerRow, 1);

         table.querySelectorAll('tbody tr[data-id]').forEach(function(row) {
            var id = parseInt(row.getAttribute('data-id'));
            var cells = row.querySelectorAll('td');
            if (yields.hasOwnProperty(id) && cells[pagesCol]) {
               cells[pagesCol].textContent = yields[id].toLocaleString();
            }
            var covText = null;
            if (yields.hasOwnProperty(id) && expected.hasOwnProperty(id)
                && expected[id] > 0 && yields[id] > 0) {
               covText = (expected[id] / yields[id] * 5.0).toFixed(2).replace('.', ',') + '%';
            }
            addCell(row, cells[pagesCol], covText);
         });

         var tfoot = table.querySelector('tfoot');
         if (tfoot) tfoot.style.display = 'none';

      } else if (colCount >= 5 && colCount < 8) {
         // === USED (IN-USE) CARTRIDGES TABLE ===
         if (!Object.keys(usedData).length) return;

         // Check if any row matches usedData
         var hasMatch = false;
         table.querySelectorAll('tbody tr[data-id]').forEach(function(row) {
            if (usedData.hasOwnProperty(parseInt(row.getAttribute('data-id')))) hasMatch = true;
         });
         if (!hasMatch) return;

         // Hide "Cartridge type" column (index 3: checkbox=0, ID=1, model=2, type=3)
         hideColumn(table, 3);

         var lastDataCol = colCount - 1;
         var headerRow = table.querySelector('thead tr:last-child');
         if (headerRow) {
            var ths = headerRow.querySelectorAll('th');
            var ref = ths[lastDataCol];
            ref = addHeader(headerRow, ref, '{$hdrCounter}');
            ref = addHeader(headerRow, ref, '{$hdrConsumed}');
            ref = addHeader(headerRow, ref, '{$hdrPagesPrinted}');
            ref = addHeader(headerRow, ref, '{$hdrCoverage}');
            ref = addHeader(headerRow, ref, '{$hdrRemaining}');
            addHeader(headerRow, ref, '{$hdrEndDate}');
         }
         extendSuperHeader(table, headerRow, 6);

         table.querySelectorAll('tbody tr[data-id]').forEach(function(row) {
            var id = parseInt(row.getAttribute('data-id'));
            var cells = row.querySelectorAll('td');
            var ref = cells[lastDataCol];
            var d = usedData.hasOwnProperty(id) ? usedData[id] : null;

            ref = addCell(row, ref, lastPages > 0 ? lastPages.toLocaleString() : null);
            ref = addCell(row, ref, d && d.toner_consumed !== null ? d.toner_consumed + '%' : null);
            ref = addCell(row, ref, d && d.pages_printed > 0 ? d.pages_printed.toLocaleString() : null);
            ref = addCell(row, ref, d && d.coverage !== null ? d.coverage.toFixed(2).replace('.', ',') + '%' : null);
            ref = addCell(row, ref, d && d.remaining !== null ? d.remaining.toLocaleString() : null);
            // Est. end date
            var endDate = (d && d.toner_consumed !== null && d.date_use) ?
               estimateEndDate(d.date_use, d.toner_consumed) : null;
            addCell(row, ref, endDate);
         });

         var tfoot = table.querySelector('tfoot');
         if (tfoot) tfoot.style.display = 'none';
      }
   });
})();
</script>
SCRIPT;
   }
}
