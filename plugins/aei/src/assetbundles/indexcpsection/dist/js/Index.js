/**
 * AEI plugin for Craft CMS
 *
 * Index Field JS
 *
 * @author    Firebelly Design
 * @copyright Copyright (c) 2018 Firebelly Design
 * @link      https://www.firebellydesign.com/
 * @package   AEI
 * @since     1.0.0
 */
// Good Design for Good Reason for Good Namespace
var FB_Admin = (function($) {

  function _init() {
    // Eventbrite Importer
    $('#deltek-import-form').on('submit', function(e) {
      e.preventDefault();
      window.scrollTo(0,0);

      var $inputSubmit = $('#deltek-import-form input[type=submit]');
      var $form = $(this);
      var $log = $('#deltek-import-form .log-output')

      // Show spinner + Working text after submitting
      $inputSubmit.prop('disabled', true).val('Please wait...');
      $log.html('<p><div id="graphic" class="spinner big"></div> Working... (be patient, can take a while)</p>');

      $.ajax({
        type: 'POST',
        dataType: 'json',
        url: $form.attr('action'),
        data: $(this).serialize(),
        success: function(data) {
          setTimeout(function() {
            // Display log messages from import script
            $log.html('<h2>Summary:</h2>' + data.summary + '<h2>Log:</h2>' + data.log);
            $inputSubmit.prop('disabled', false).val('Run Importer');
          }, 500);
        }
      });
    });
  }

  // Public functions
  return {
    init: _init
  };

})(jQuery);

// Fire up the mothership
jQuery(document).ready(FB_Admin.init);
