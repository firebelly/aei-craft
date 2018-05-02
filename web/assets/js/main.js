// Firebelly 2016
/*jshint latedef:false*/

//=include "../bower_components/jquery/dist/jquery.js"
//=include "../bower_components/jquery.fitvids/jquery.fitvids.js"
//=include "../bower_components/velocity/velocity.js"
//=include "../bower_components/imagesloaded/imagesloaded.pkgd.min.js"
//=include "../bower_components/jquery_lazyload/jquery.lazyload.js"
//=include "../bower_components/waypoints/lib/jquery.waypoints.js"
//=include "../bower_components/isotope-layout/dist/isotope.pkgd.js"
//=include "../bower_components/slick-carousel/slick/slick.js"
//=include "../bower_components/tablesorter/jquery.tablesorter.js

// Good Design for Good Reason for Good Namespace
var FB = (function($) {

  var screen_width = 0,
      breakpoint_xs = false,
      breakpoint_sm = false,
      breakpoint_md = false,
      breakpoint_lg = false;


  function _init() {
    // Cache some common DOM queries
    $document = $(document);
    $header = $('.site-header');
    $body = $('body');
    $nav = $('.site-nav');
    $body.addClass('loaded');

    // Set screen size vars
    _resize();

    // Fit them vids!
    $('main').fitVids();

    // Esc handlers
    $(document).keyup(function(e) {
      if (e.keyCode === 27) {
        _closeNav();
      }
    });

    // Smoothscroll links
    $('a.smoothscroll').click(function(e) {
      e.preventDefault();
      var href = $(this).attr('href');
      _scrollBody($(href), 500, 0, true);
    });

    // Bigclicky™
    $(document).on('click', '.bigclicky', function(e) {
      if (!$(e.target).is('a')) {
        e.preventDefault();
        var link = $(this).find('a:first');
        var href = link.attr('href');
        if (href) {
          if (e.metaKey || link.attr('target')) {
            window.open(href);
          } else {
            location.href = href;
          }
        }
      }
    });

    // Scroll down to hash after page load
    $(window).on('load',function() {
      if (window.location.hash) {
        _scrollBody($(window.location.hash), 250, 0, true);
      }
    });

    _initSlick();
    _initLazyload();
    _initNav();
    _initTheater();
    _initMasonry();
    _initStatLabelWrappingDetection();
    _initTableSort();

  } // end init()

  function _initTableSort() {
    $('.award-table table.sortable')
      .tablesorter({
        sortList: [[1,1]]
      });
  }

  function _initSlick() {
    $('.quote-carousel').slick({
      arrows: false,
      autoplay: true,
      autoplaySpeed: 5000,
      fade: true,
      swipe: false,
      touchMove: false,
      draggable: false,
    }).removeClass('-unslicked');
  }

  function _scrollBody(element, duration, delay) {
    isAnimating = true;
    element.velocity("scroll", {
      duration: duration,
      delay: delay,
      offset: 0,
      complete: function(elements) {
        isAnimating = false;
      }
    }, "easeOutSine");
  }

  function _initLazyload() {
    $('.lazy').lazyload({
      effect: "fadeIn",
      effectTime: 100,
      threshold: 500,
      load: function() {
        $(this).addClass('lazyloaded');
      }
    });
  }

  function _initStatLabelWrappingDetection() {

    $labels = $('.stat-module .label');

    if ($labels.length) {

      function detectLabelWrap() {
        $('.stat-module .label').each(function () {

          $label = $(this);

          $label.removeClass('-wrapped');

          labelOffset = $label.offset().left;
          parentOffset = $label.parent().offset().left;
          parentPadding = parseInt($label.parent().css('padding-left'));

          if(labelOffset-parentOffset-parentPadding===0) {
            $label.addClass('-wrapped');
          }
        });
      }

      detectLabelWrap();
      $(window).resize(detectLabelWrap);
    }
  }

  function _initNav() {

    _closeNav();
    $(document).on('click','.nav-close', function () {
      _closeNav();
    });
    $(document).on('click','.nav-open', function () {
      _openNav();
    });
    $(document).on('click','.nav-toggle', function () {
      _toggleNav();
    });
  }

  function _openNav() {
    $body
      .addClass('site-nav-open')
      .removeClass('site-nav-closed');
  }

  function _closeNav() {
    $body
      .removeClass('site-nav-open')
      .addClass('site-nav-closed');
  }

  function _toggleNav() {
    if($body.hasClass('site-nav-open')) {
      _closeNav();
    } else {
      _openNav();
    }
  }

  function _initTheater() {
    if($('.theater-wrap .player').length) {

      $.getScript("https://www.youtube.com/iframe_api", function () {

        $('.theater-wrap').each(function () {
          var theater = new Theater($(this));
        });
      });
    }
  }

  function Theater($theaterWrap) {

    // Alias this
    var me = this;

    // Find me in markup
    me.$theaterWrap = $theaterWrap;
    me.$player = me.$theaterWrap.find('.player');

    // Get youtube id
    me.youtubeId = me.$player.attr('data-youtube-id');

    // This will store player object from youtube api
    me.player = false;

    // Open the theater and play
    me.open = function () {
      me.$theaterWrap
        .addClass('-open')
        .removeClass('-closed');

      // Play if player object is already populated
      if(me.player) {
        me.player.playVideo();

      // Otherwise populate it with api call
      } else {
        me.player = new YT.Player(me.$player[0], {
          videoId: me.youtubeId,
          playerVars: {
              autoplay: 1,
              rel: 0,
              showinfo: 0,
              modestbranding: 0,
          },
          events: {
            'onReady': function (e) {
              me.$theaterWrap.find('.player-wrap').addClass('player-ready');
            },
          }
        });
      }
    }

    // Close the theater and stop
    me.close = function () {
      if(me.player) {
        me.player.stopVideo();
      }
      me.$theaterWrap
        .removeClass('-open')
        .addClass('-closed');
    }

    // Add open/close functionality to DOM elements with apprpriate class
    $theaterWrap.find('.theater-open').click(me.open);
    $theaterWrap.find('.theater-close').click(me.close);

    // Close theater on ESC key press
    $(document).keyup(function(e) {
      if (e.keyCode === 27) {
        me.close();
      }
    });

  }

  // Called in quick succession as window is resized
  function _resize() {
    screenWidth = document.documentElement.clientWidth;

    // Check breakpoint indicator in DOM ( :after { content } is controlled by CSS media queries )
    var breakpointIndicatorString = window.getComputedStyle(
      document.querySelector('#breakpoint-indicator'), ':after'
    ).getPropertyValue('content')
    .replace(/['"]+/g, '');

    // Determin current breakpoint
    breakpoint_lg = breakpointIndicatorString === 'lg';
    breakpoint_md = breakpointIndicatorString === 'md' || breakpoint_lg;
    breakpoint_sm = breakpointIndicatorString === 'sm' || breakpoint_md;
    breakpoint_xs = breakpointIndicatorString === 'xs' || breakpoint_sm;
  }

  function _initMasonry() {
    if($('.masonry-grid').length) {
      $('.masonry-grid').isotope({
        itemSelector: '.masonry-item',
        percentPosition: true,
        transitionDuration: 0,
        masonry: {
          // use outer width of grid-sizer for columnWidth
          columnWidth: '.masonry-sizer',
        }
      });
    }
  }

  // Public functions
  return {
    init: _init,
    resize: _resize,
    scrollBody: function(section, duration, delay) {
      _scrollBody(section, duration, delay);
    }
  };

})(jQuery);

// Fire up the mothership
jQuery(document).ready(FB.init);

// Zig-zag the mothership
jQuery(window).resize(FB.resize);