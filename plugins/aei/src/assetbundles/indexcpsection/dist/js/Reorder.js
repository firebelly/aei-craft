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
var AeiReorder = (function($) {
  var $reorderForm;

  function _init() {
    $reorderForm = $('#reorder-form');
    $log = $reorderForm.find('.log-output');

    // Market select
    $('select.market-select').on('change', function(e) {
      e.preventDefault();
      window.location = this.options[this.selectedIndex].value;
    });

    // Sortable projects
    $('.market-projects').each(function() {
      var $this = $(this);
      var sortable_projects = new Sortable(this, {
        handle: 'li',
        onUpdate: function() {
          $.ajax({
            dataType: 'json',
            url: $reorderForm.attr('action'),
            data: {
              CRAFT_CSRF_TOKEN: $reorderForm.find('input[name=CRAFT_CSRF_TOKEN]').val(),
              'market': $reorderForm.find('input[name=market]').val(),
              'project-ids': sortable_projects.toArray()
            }
          }).done(function(data) {
            if (data.status != 1) {
              $log.html('<h3>There was an error</h3>' + data.message);
            }
          }).fail(function(jqXHR) {
            $log.html('<h3>There was an error sending the request</h3>' + jqXHR.responseJSON.error);
          });
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
jQuery(document).ready(AeiReorder.init);
