/**
 * Color Swatches field CP behavior
 */
/*jshint latedef:false*/

var FB_ColorSwatches = (function($) {
  function _init() {
    var colorThief = new ColorThief();

    // If there's a project image field on the page, hook into selectElements event, and match a swatch to thumbnail
    if ($('#fields-projectImage').length) {
      $('#fields-projectImage').data('elementSelect').on('selectElements', function(e) {
        $('#fields-projectImage .elements .element').each(function() {
          var $thumb = $(this).find('.elementthumb img');
          $thumb.on('load', function() {
            // Use colorThief to get "primary color"
            var primaryColor = colorThief.getColor($thumb[0], 1);
            var closest = 9999;
            var match;
            // Loop through swatches to find closest match
            $('.color-swatches input').each(function() {
              if ($(this).attr('data-hex')) {
                howClose = compareColors(primaryColor, hexToRgb($(this).attr('data-hex')));
                if (howClose < closest) {
                  closest = howClose;
                  match = this;
                }
              }
            });
            // Select closest match
            $(match).click();
          });
        });
      });
    }
  }

  function hexToRgb(hex) {
    var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? [
        parseInt(result[1], 16),
        parseInt(result[2], 16),
        parseInt(result[3], 16)
    ] : null;
  }

  function compareColors(colorA, colorB) {
    return Math.abs(colorA[0] - colorB[0]) + Math.abs(colorA[1] - colorB[1]) + Math.abs(colorA[2] - colorB[2]);
  }

  return {
    init: _init
  };

})(jQuery);

jQuery(document).ready(FB_ColorSwatches.init);
