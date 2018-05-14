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
  var _csrf,
  $importForm,
  $progressBar,
  $log;

  function _init() {
    $importForm = $('#deltek-import-form');
    $progressBar = $importForm.find('.progressbar');
    $log = $('#deltek-import-form .log-output');

    // Eventbrite Importer
    $importForm.on('submit', function(e) {
      e.preventDefault();
      window.scrollTo(0,0);

      var $inputSubmit = $('#deltek-import-form input[type=submit]');
      _csrf = $importForm.find('input[name=CRAFT_CSRF_TOKEN]').val();
      // Show spinner + Working text after submitting
      $importForm.addClass('importing').find('input[type=submit]').prop('disabled', true).val('Please wait...');
      $importForm.find('.info-output').removeClass('hidden');
      $log.removeClass('hidden').html('<h2>Log output:</h2>');
      _updateProgressBar();
      _importNextSection();
    });
  }

  function _updateProgressBar() {
    if ($importForm.hasClass('importing')) {
      var sectionsToImport = $importForm.find('input[type=checkbox]:checked').length;
      var sectionsImported = $importForm.find('input[type=checkbox]:checked.done').length;
      var percentDone = sectionsImported / sectionsToImport * 100;
      $progressBar.removeClass('hidden').find('div').css('width', percentDone + '%');
    } else {
      $progressBar.addClass('hidden').find('div').css('width', '0');
    }
  }

  function _importNextSection(section) {
    var sectionsToImport = $importForm.find('input[type=checkbox]:checked:not(.done)');
    if (sectionsToImport.length) {
      $.ajax({
        type: 'POST',
        dataType: 'json',
        url: $importForm.attr('action'),
        data: {
          CRAFT_CSRF_TOKEN: _csrf,
          'sections-to-import[]': sectionsToImport.first().val()
        },
        success: function(data) {
          setTimeout(function() {
            // Display log messages from import script
            $log.append('<h3>' + data.summary + '</h3>' + data.log);
            sectionsToImport.first().addClass('done');
            _updateProgressBar();
            _importNextSection();
          }, 500);
        }
      });
    } else {
      $importForm.removeClass('importing').find('input[type=submit]').prop('disabled', false).val('Run Importer');
      $importForm.find('.info-output').addClass('hidden');
      $importForm.find('input').removeClass('done');
      $importForm[0].reset();
      _updateProgressBar();
    }
  }

  // Public functions
  return {
    init: _init
  };

})(jQuery);

// Fire up the mothership
jQuery(document).ready(FB_Admin.init);
