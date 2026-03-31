<?php
// Suppress PHP errors/warnings to avoid breaking JavaScript output
@ini_set('display_errors', '0');
ob_start();
include('../../inc/includes.php');
ob_end_clean();
header('Content-Type: text/javascript');

$webdir = defined('PLUGIN_PRINTERCOUNTERS_WEBDIR') ? PLUGIN_PRINTERCOUNTERS_WEBDIR : '';
?>

var root_printercounters_doc = "<?php echo $webdir; ?>";
(function ($) {
   $.fn.printercounters_load_scripts = function () {

      init();

      // Start the plugin
      function init() {
         if (!root_printercounters_doc) return;
         // Send data
         $.ajax({
            url: root_printercounters_doc + '/ajax/loadscripts.php',
            type: "POST",
            dataType: "html",
            data: 'action=load',
            success: function (response, opts) {
               var scripts, scriptsFinder = /<script[^>]*>([\s\S]+?)<\/script>/gi;
               while (scripts = scriptsFinder.exec(response)) {
                  eval(scripts[1]);
               }
            }
         });
      }

      return this;
   };
}(jQuery));

$(document).printercounters_load_scripts();
