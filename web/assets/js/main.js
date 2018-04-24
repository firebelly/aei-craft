// Firebelly 2016
/*jshint latedef:false*/

//=include "../bower_components/jquery/dist/jquery.js"
//=include "../bower_components/jquery.fitvids/jquery.fitvids.js"
//=include "../bower_components/velocity/velocity.js"
//=include "../bower_components/imagesloaded/imagesloaded.pkgd.min.js"
//=include "../bower_components/jquery_lazyload/jquery.lazyload.js"
//=include "../bower_components/waypoints/lib/jquery.waypoints.js"
//=include "../bower_components/isotope-layout/dist/isotope.pkgd.js"

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
        _closeTheater();
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

    _initLazyload();
    _initNav();
    _initTheater();
    _initMasonry();

  } // end init()

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
    if($('.theater #youtube-player').length) {

     $.getScript("https://www.youtube.com/iframe_api", function () {

        $(document).on('click','.theater-close', function () {
          _closeTheater();
        });

        $(document).on('click','.theater-open', function () {
          _openTheater();
        });

        $(document).on('click','.theater-toggle', function () {
          _toggleTheater();
        });
      });
    }
  }
  function _openTheater() {
    $('.theater-wrap')
      .addClass('-open')
      .removeClass('-closed');

    // Load video if not already loaded, then play
    if(typeof youtubePlayer === 'undefined') {
      var youtubeId = $('#youtube-player').attr('data-youtube-id');
      youtubePlayer = new YT.Player('youtube-player', {
        videoId: youtubeId,
        events: {
          'onReady': function (e) {
            $('.theater-wrap .video').addClass('video-ready');
            e.target.playVideo();
          },
        }
      });
    } else {
      youtubePlayer.playVideo();
    }
  }
  function _closeTheater() {
    if(typeof youtubePlayer !== 'undefined') {
      youtubePlayer.stopVideo();
    }
    $('.theater-wrap')
      .removeClass('-open')
      .addClass('-closed');
  }
  function _toggleTheater() {
    if($('.theater-wrap').hasClass('-open')) {
      _closeTheater();
    } else {
      _openTheater();
    }
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