/**
 * AEI plugin for Craft CMS
 *
 * Deltek Import Admin js
 *
 * @author    Firebelly Design
 * @copyright Copyright (c) 2018 Firebelly Design
 * @link      https://www.firebellydesign.com/
 * @package   AEI
 * @since     1.0.0
 */

// Good Design for Good Reason for Good Namespace
var DeltekImportIndex = (function($) {
  var $importForm,
      $progressBar,
      $log;

  function _init() {
    $importForm = $('#deltek-import-form');
    $progressBar = $importForm.find('.progressbar');
    $log = $('#deltek-import-form .log-output');

    // Hijack import form to send AJAX request
    $importForm.on('submit', function(e) {
      e.preventDefault();

      // Show spinner + Working text after submitting, disable submit button
      $importForm.addClass('importing').find('input[type=submit]').prop('disabled', true).val('Please wait...');
      $importForm.find('input').removeClass('done');
      $log.removeClass('hidden').html('<h2>Log output:</h2>');
      // Show progress bar
      _updateProgressBar();
      // Start importing all sections checked
      _importNextSection();
    });
  }

  // Import each section selected, one-by-one
  function _importNextSection(section) {
    // Find all sections to import
    var sectionsToImport = $importForm.find('input[type=checkbox]:checked:not(.done)');
    if (sectionsToImport.length) {
      $.ajax({
        dataType: 'json',
        url: $importForm.attr('action'),
        data: {
          CRAFT_CSRF_TOKEN: $importForm.find('input[name=CRAFT_CSRF_TOKEN]').val(),
          'sections-to-import[]': sectionsToImport.first().val(),
          'deltek-ids': $importForm.find('input[name=deltek-ids]').val()
        }
      }).done(function(data) {
        if (data.status == 1) {
          // Display log messages from import script (after slight pause)
          setTimeout(function() {
            $log.append('<h3>' + data.summary + '</h3>' + data.log);
            sectionsToImport.first().addClass('done');
            _updateProgressBar();
            _importNextSection();
          }, 500);
        } else {
          _importError('<h3>There was an error</h3>' + data.message);
        }
      }).fail(function(jqXHR) {
        _importError('<h3>There was an error sending the request</h3>' + jqXHR.responseJSON.error);
      });
    } else {
      // Reset import form (after slight pause)
      setTimeout(_finishImport, 1000);
    }
  }

  // Hide/show progress bar and show percent done if available
  function _updateProgressBar() {
    var sectionsToImport = $importForm.find('input[type=checkbox]:checked').length;
    // Only show progress bar if more than one section is importing
    if ($importForm.hasClass('importing') && sectionsToImport > 1) {
      var sectionsImported = $importForm.find('input[type=checkbox]:checked.done').length;
      var percentDone = sectionsImported / sectionsToImport * 100;
      $progressBar.removeClass('hidden').find('div').css('width', percentDone + '%');
    } else {
      $progressBar.addClass('hidden').find('div').css('width', '0');
    }
  }

  // Finish import and reset form
  function _finishImport() {
    $importForm.removeClass('importing').find('input[type=submit]').prop('disabled', false).val('Run Importer');
    // $importForm.find('input').removeClass('done');
    // $importForm[0].reset();
    _updateProgressBar();
  }

  // Something went wrong
  function _importError(message) {
    $log.append(message);
    $importForm.removeClass('importing').find('input[type=submit]').prop('disabled', false).val('Run Importer');
  }

  // Public functions
  return {
    init: _init
  };

})(jQuery);

// Fire up the mothership
jQuery(document).ready(DeltekImportIndex.init);
