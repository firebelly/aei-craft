// AEI - Firebelly 2018
/*jshint latedef:false*/

//=include "../bower_components/jquery/dist/jquery.js"
//=include "../bower_components/jquery.fitvids/jquery.fitvids.js"
//=include "../bower_components/velocity/velocity.js"
//=include "../bower_components/imagesloaded/imagesloaded.pkgd.min.js"
//=include "../bower_components/jquery_lazyload/jquery.lazyload.js"
//=include "../bower_components/waypoints/lib/jquery.waypoints.js"
//=include "../bower_components/waypoints/lib/shortcuts/sticky.js"
//=include "../bower_components/isotope-layout/dist/isotope.pkgd.js"
//=include "../bower_components/infinite-scroll/dist/infinite-scroll.pkgd.js"
//=include "../bower_components/slick-carousel/slick/slick.js"
//=include "../bower_components/tablesorter/jquery.tablesorter.js"

// Good Design for Good Reason for Good Namespace
var FB = (function($) {

  var screen_width = 0,
      delayed_resize_timer,
      breakpoint_xs = false,
      breakpoint_sm = false,
      breakpoint_md = false,
      breakpoint_lg = false;


  function _init() {
    // Cache some common DOM queries
    $document = $(document);
    $header = $('.site-header');
    $body = $('body');
    $header = $('.site-nav');
    $body.addClass('loaded');

    // Set screen size vars
    _resize();

    // Fit them vids!
    $('main').fitVids();

    // Esc handlers
    $(document).keyup(function(e) {
      if (e.keyCode === 27) {
        _closeNav();
        _closeContactModal();
        _closeSearch();
      }
    });

    // Smoothscroll links
    $('a.smoothscroll').click(function(e) {
      e.preventDefault();
      var href = $(this).attr('href');
      _scrollBody($(href), 500, 0, true);
    });

    // Infinite scroll
    $('.infinite-scroll-container').infiniteScroll({
      path: function() {
        // Are there more pages?
        if (this.pageIndex < parseInt($('.pagination').attr('data-total-pages'))) {
          // Replace /p2 with this.loadCount + 1 from infinite scroll
          var nextUrl = $('.pagination .next a').attr('href').replace(/(p[\d]+)$/, 'p' + (this.loadCount + 2));
          // Omit the featured post if there is one
          nextUrl += '?omitId=' + ($('.hero-wrap article').attr('data-id') || '');
          return nextUrl;
        } else {
          return false;
        }
      },
      append: false,
      history: false,
    });
    $('.infinite-scroll-container').on( 'load.infiniteScroll', function( event, response ) {
      var $items = $(response).find('.infinite-scroll-object');
      $(this).append($items);
      if ($('.masonry-grid').length) {
        $('.masonry-grid').isotope('appended', $items);
      }
      _initLazyload();
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
    _initContactModal();
    _initSearch();
    _initStickyHeader();
    _initFilters();
    _fitFigures();
    _hangQuotes();

  } // end init()

  function _initFilters() {
    $('.mobile-filter').each(function() {
      $this = $(this);

      // Click on header to expand filters
      $this.find('.filter-header').on('click', function(e) {
        e.preventDefault();
        $this.toggleClass('active');
      });

      // Make filter sticky
      new Waypoint.Sticky({
        element: $this[0]
      });
    });
  }

  // Wrap first quotation mark in blockquotes in a span to apply hanging quotes
  function _hangQuotes() {
    $('blockquote').each(function() {
      var content = $(this).html().trim();
      if (content[0] === '“') {
        var content = '<span class="hang">“</span>'+content.slice(1);
        $(this).empty().append(content);
      }
    });
  }

  function _fitFigures() {
    $('.fit-figure').each(function() {

      var $figure = $(this);
      var $container = $figure.closest('.fit-figure-container');

      // Try 10 sizes, one after the other, til I don't fit no more
      for (i=1; i<10; i++) {

        // Set the font size
        $figure.css('font-size', i + 'em');
        $figure.attr('data-figure-size', i); // We'll use this data-attr for tying figures

        // If I'm too big, go with one size smaller
        if ($figure.width() >= $container.width()) {
          $figure.css('font-size', (i-1) + 'em');
          $figure.attr('data-figure-size', i-1);
          break;
        }
      }
    });

    $('.tie-fit-figures').each(function() {
      var smallest = 10;
      var $figures = $(this).find('.fit-figure');
      $figures.each(function() {
        var size = parseInt( $(this).attr('data-figure-size') );
        smallest = Math.min(smallest, size);
      });

      $figures.css('font-size', smallest + 'em');
      $figures.attr('data-figure-size', smallest);
    });
  }

  // Code for sticky header
  function _initStickyHeader() {

    // Make sidebar sticky
    new Waypoint.Sticky({
      element: $('.sidebar')[0],
      wrapper: '<div class="sidebar-sticky-wrapper" />'
    });

    // Make StickyHeader class
    function StickyHeader() {

      var $header = $('#sticky-header');
      var turningPoint = 250;
      var me = this;
      var scrollTop = $(window).scrollTop();
      var lastScrollTop = scrollTop;
      var scrolled, scrollingUp, stuck, lastScrollingUp;

      // Determine whether header should be sticky
      this.refreshState = function() {
        lastScrollTop = scrollTop;
        scrollTop = $(window).scrollTop();

        var lastScrolled = scrolled;
        scrolled = scrollTop > turningPoint;
        if (scrolled !== lastScrolled ) {
          if (scrolled) {

            // Mark this as is currently scrolled
            $header.addClass('-scrolled');
            $body.addClass('-scrolled');

            if (!stuck) {
              // Mark this as having had scrolled at some point
              $header.addClass('-stuck');
              stuck = true;
              turningPoint = 128;

              // Prevent a transition
              $header.css('transition','none');
              setTimeout(function() {
                $header.css('transition','');
              }, 1);

            }
          }
          if (!scrolled) {
            $header.removeClass('-scrolled');
            $body.removeClass('-scrolled');
          }
        }

        lastScrollingUp = scrollingUp;
        scrollingUp = lastScrollTop > scrollTop;
        if (scrollingUp !== lastScrollingUp ) {
          if (scrollingUp && stuck) {
            $header.addClass('-scrolling-up');
            $body.addClass('nav-stuck');
          }
          if (!scrollingUp) {
            $header.removeClass('-scrolling-up');
            $body.removeClass('nav-stuck');
          }
        }

        if (scrollTop < 5 && stuck) {
          stuck = false;
          $header.removeClass('-stuck');
        }
      };

      // Init the stickiheader and tie behavior to window events
      this.init = function() {

        // Start off in correct state
        me.refreshState();

        // Scroll Handling
        var lastMove = 0;
        var eventThrottle = 40;
        window.addEventListener('scroll', function() {
          var now = Date.now();
          if (now > lastMove + eventThrottle) {
            lastMove = now;
            me.refreshState();
          }
        });

        // Resize Handling
        // $(window).resize(function() {
        //   me.refreshState();
        // });

        // Tell CSS the header is ready to reveal
        $header.removeClass('-unloaded');
      };

      // Fire away
      this.init();
    }

    // Make the header
    _stickyHeader = new StickyHeader();
  }

  function _initSearch() {
    // Open on click
    $(document).on('click', '.search-open', function(e) {
      e.preventDefault();
      _openSearch();
    });

    $(document).on('click', '.search-close', function(e) {
      e.preventDefault();
      _closeSearch();
    });

    // Clutter up the DOM (add search modal and overlay)
    $('<div id="search-modal"><div class="scroll-wrap"><div class="content"></div></div><svg class="icon icon-x"><use xlink:href="#icon-x" /></svg></div>')
      .appendTo('body');
    $('<div class="overlay search-close" id="search-overlay"></div>')
      .appendTo('body');

    // Hide the overlay
    $('#search-overlay').velocity('fadeOut', { duration: 0 });

    // Pipe in search results on submit
    $(document).on('submit', '.search-form', function(e) {
      e.preventDefault();
      var $this = $(this);
      $.get($this.attr('action'), $this.serialize(), function(data) {
        var $content = $('#search-modal .content');
        var $scrollContext = $('#search-modal .scroll-wrap')

        $content.html(data).velocity('fadeOut', {duration: 0});
        $content.find('.search-section, .search-article').velocity('fadeOut', {duration: 0});

        var speed = 200;
        var delay = 40;
        var i = 0;
        var j = 0;
        $content.velocity('fadeIn', {duration: speed, delay: delay*(i++)}).find('.search-form input[type="search"]').focus();
        $content.find('.search-section').each(function() {
          $(this).velocity('fadeIn', {duration: speed, delay: delay*(j+i++)});

          $(this).find('.search-article').each(function() {
            if (i<10) {
              $(this).velocity('fadeIn', {duration: speed, delay: delay*(j+i++)});
            } else {
              $(this).velocity('fadeIn', {delay: delay*(j+10), duration: 0});
            }
          });
          j+=5;
          i=0;
        });

        // Make header titles sticky
        $('.sticky-header').each(function() {
          $this = $(this);
          var sticky = new Waypoint.Sticky({
            element: $this[0],
            context: $scrollContext[0],
          });
        });

        // Required to maintain width on sticky headers
        _fixStickyHeaderWidths();

      });
    });
  }

  function _openSearch() {
    // Hide mobile nav
    _closeNav();

    // Animate in the modal
    $('#search-modal').addClass('active');
    $('#search-overlay').velocity('fadeIn', { duration: 100, easing: 'easeOut' });

    // Prevent body scroll
    $('body').addClass('no-scroll');

    // Fill with content
    $.get('/search/', function(data) {
      $('#search-modal .content').html(data).velocity('fadeIn', {duration: 200});
      setTimeout(function() {
        $('#search-modal .content').find('.search-form input[name=q]').focus();
      },100);
    });
  }

  function _closeSearch() {
    // Animate it away
    $('#search-modal').removeClass('active');
    $('#search-modal .content').velocity("fadeOut", { duration: 100, easing: 'easeOut' });
    $('#search-overlay').velocity("fadeOut", { delay: 300, duration: 300, easing: 'easeOut' });

    // Enable scroll
    $('body').removeClass('no-scroll');
  }

  function _initContactModal() {

    // Does the contact modal exist?
    var $modal = $('#contact-modal');
    if ($modal.length) {

      // Add junk to DOM.
      $('<div class="overlay contact-modal-close" id="contact-modal-overlay"></div>')
        .appendTo('body');
      $('<svg class="icon icon-x"><use xlink:href="#icon-x" /></svg>')
        .prependTo($modal)
        .on('click', _closeContactModal);

      // Sweep it all under the rug.
      $modal.velocity("slideUp", { duration: 0 });
      $('#contact-modal-overlay').velocity("fadeOut", { duration: 0 });

      // Init clicking behavior.
      $(document).on('click', '.contact-modal-close', _closeContactModal);
      $(document).on('click', '.contact-modal-open', _openContactModal);

      // CSS will display: none this until the -unloaded class is removed.
      $modal.removeClass('-unloaded');
    }
  }

  function _openContactModal() {

    // If it exists, animate the modal open and fade in its overlay.
    var $modal = $('#contact-modal');
    if ($modal.length) {

      var $overlay = $('#contact-modal-overlay');

      $modal
        .velocity("fadeIn", { duration: 300, easing: 'easeOut', queue: false })
        .velocity("slideDown", { duration: 300, easing: 'easeOut' });

      $overlay
        .velocity("fadeIn", { duration: 200, easing: 'easeOut' });
    }
  }

  function _closeContactModal() {

    // If it exists, animate the modal closed and fade out the overlay.
    var $modal = $('#contact-modal');
    if ($modal.length) {

      var $overlay = $('#contact-modal-overlay');

      $modal
        .velocity("fadeOut", { duration: 300, easing: 'easeOut', queue: false })
        .velocity("slideUp", { duration: 300, easing: 'easeOut' });

      $overlay
        .velocity("fadeOut", { delay: 300, duration: 300, easing: 'easeOut' });
    }
  }

  function _initTableSort() {
    $('.award-table table.sortable')
      .tablesorter({
        sortList: [[1,1]]
      });
  }

  function _initSlick() {
    // Make quote-carousels with more than one quote a slick carousel
    $('.quote-carousel').each(function() {
      var $this = $(this);
      if ($this.find('blockquote').length > 1) {
          $this.slick({
            // arrows: false,
            nextArrow: '<div class="next"><svg class="icon icon-right-arrow"><use xlink:href="#icon-right-arrow" /></svg></div>',
            prevArrow: '<div class="prev"><svg class="icon icon-left-arrow"><use xlink:href="#icon-left-arrow" /></svg></div>',
            autoplay: true,
            autoplaySpeed: 5000,
            fade: true,
            swipe: false,
            touchMove: false,
            draggable: false,
            pauseOnHover: false,
          }).removeClass('-unslicked');
      }
    });
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

  function _scrollContainer(container, element, duration, delay) {
    isAnimating = true;
    element.velocity("scroll", {
      container: container,
      duration: duration,
      delay: delay,
      offset: 0,
      complete: function(elements) {
        isAnimating = false;
      }
    }, "easeOutSine");
  }

  function _initLazyload() {
    $('.lazy:not(.lazyLoaded)').lazyload({
      effect: 'fadeIn',
      effectTime: 100,
      threshold: 500,
      load: function() {
        $(this).addClass('lazyLoaded');
      }
    });
  }

  function _initStatLabelWrappingDetection() {

    $labels = $('.stat-module .label');

    if ($labels.length) {

      function detectLabelWrap() {
        $('.stat-module .label').each(function() {

          $label = $(this);

          $label.removeClass('-wrapped');

          labelOffset = $label.offset().left;
          parentOffset = $label.parent().offset().left;
          parentPadding = parseInt($label.parent().css('padding-left'));

          if (labelOffset-parentOffset-parentPadding===0) {
            $label.addClass('-wrapped');
          }
        });
      }

      detectLabelWrap();
      $(window).resize(detectLabelWrap);
    }
  }

  function _initNav() {
    // Initial state
    _closeNav();
    $(document).on('click','.nav-close', function() {
      _closeNav();
    });
    $(document).on('click','.nav-open', function() {
      _openNav();
    });
    $(document).on('click','.nav-toggle', function() {
      _toggleNav();
    });
    $(document).on('click','.open-filters', function() {
      _openNav();
      _scrollContainer($('.site-nav'),$('.filters'),250,250);
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
    if ($body.hasClass('site-nav-open')) {
      _closeNav();
    } else {
      _openNav();
    }
  }

  function _initTheater() {
    if ($('.theater-wrap .player').length) {

      $.getScript("https://www.youtube.com/iframe_api", function() {

        $('.theater-wrap').each(function() {
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
    me.play = function() {
      me.$theaterWrap
        .addClass('-open')
        .removeClass('-closed');

      // Play if player object is already populated
      if (me.player) {
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
    me.stop = function() {
      if (me.player) {
        me.player.stopVideo();
      }
      me.$theaterWrap
        .removeClass('-open')
        .addClass('-closed');
    }

    // Add play/stop functionality to DOM elements with apprpriate class
    $theaterWrap.find('.theater-play').click(me.play);
    $theaterWrap.find('.theater-stop').click(me.stop);

    // Close theater on ESC key press
    $(document).keyup(function(e) {
      if (e.keyCode === 27) {
        me.stop();
      }
    });

  }

  // Called in quick succession as window is resized
  function _resize() {
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

  function _delayed_resize() {
    clearTimeout(delayed_resize_timer);
    delayed_resize_timer = setTimeout(function() {

      // Refit stats
      _fitFigures();

      // Refix waypoints
      Waypoint.refreshAll();

      // Resize fixed headers
      _fixStickyHeaderWidths();

    }, 250);
  }

  function _fixStickyHeaderWidths() {
    $('.sticky-header').each(function() {
      var parentWidth = $(this).parent().width();
      $(this).width(parentWidth);
    });
  }

  function _initMasonry() {
    if ($('.masonry-grid').length) {
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
    delayed_resize: _delayed_resize,
    scrollBody: function(section, duration, delay) {
      _scrollBody(section, duration, delay);
    }
  };

})(jQuery);

// Fire up the mothership
jQuery(document).ready(FB.init);

// Zig-zag the mothership
jQuery(window).resize(FB.resize);

// Zig-zag the mothership
jQuery(window).resize(FB.delayed_resize);
