// AEI - Firebelly 2018
/*jshint latedef:false*/

//=include "../bower_components/jquery/dist/jquery.js"
//=include "../bower_components/jquery.fitvids/jquery.fitvids.js"
//=include "../bower_components/velocity/velocity.js"
//=include "../bower_components/jquery_lazyload/jquery.lazyload.js"
//=include "../bower_components/waypoints/lib/jquery.waypoints.js"
//=include "../bower_components/waypoints/lib/shortcuts/sticky.js"
//=include "../bower_components/masonry-layout/dist/masonry.pkgd.js"
//=include "../bower_components/infinite-scroll/dist/infinite-scroll.pkgd.js"
//=include "../bower_components/slick-carousel/slick/slick.js"
//=include "../bower_components/tablesorter/jquery.tablesorter.js"
//=include "../bower_components/js-cookie/src/js.cookie.js"
//=include "./bodyScrollLock.js"

// Good Design for Good Reason for Good Namespace
var FB = (function($) {

  var screen_width = 0,
      $document,
      $siteNav,
      $body,
      $masonryGrid,
      initialHref,
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
    $siteNav = $('.site-nav');
    $masonryGrid = $('.masonry-grid');
    $body.addClass('loaded');

    // Set screen size vars
    _resize();

    // Initial page href
    initialHref = location.href;

    // Fit them vids!
    $('main').fitVids();

    // Only show share block if addthis initializes
    if (typeof addthis !== 'undefined' && addthis.user) {
      addthis.user.ready(function(d) {
        $('.share').removeClass('hidden');
      });
    }

    // Show child nav if parent or child is currently active
    $('li.has-children').each(function() {
      var $this = $(this);
      var $children = $this.next('ul.children');
      $this.next('ul.children').addBack().wrapAll('<div class="nobreak"/>');
      if ($this.hasClass('current') || $children.find('li.current').length > 0) {
        $this.addClass('current');
        $children.velocity('slideDown', { duration: 250 });
      }
      $this.on('click', function(e) {
        e.preventDefault();
        if ($this.hasClass('current')) {
          $children.velocity('slideUp', { duration: 250 });
          $this.removeClass('current');
        } else {
          // Collapse any open children navs
          $('li.has-children.current').each(function() {
            $(this).removeClass('current').next('ul.children').velocity('slideUp', { duration: 250 });
          });
          $children.velocity('slideDown', { duration: 250 });
          $this.addClass('current');
        }
      });
    });

    // Sitewide notice X close button
    $('.sitewide-notice a.close').on('click', function(e) {
      e.preventDefault();
      _closeSitewideNotice();
    });

    // Esc handlers
    $document.keyup(function(e) {
      if (e.keyCode === 27) {
        _closeNav();
        _closeSitewideNotice();
        _closeContactModal();
        _closeSearch();
      }
    });

    // Add .button class to a elements in p.button-block (Redactor button-blocks)
    $('.user-content p.button-block').each(function() {
      $this = $(this).removeClass('button-block');
      $this.find('a').prepend('<span class="border"></span><span class="extra-corners"></span>').addClass('button').append('<svg class="icon icon-right-arrow"><use xlink:href="#icon-right-arrow" /></svg>');
    });

    // Back/fwd support
    $(window).on('popstate',function() {
      // Page affected by search modal history, reload it
      if ((history.state && history.state.ajax)) {
        location.reload();
      }
      // Hide modals on popstate
      // _closeNav();
      // _closeContactModal();
      // _closeSearch();
    });

    // Smoothscroll links
    $('a.smoothscroll').click(function(e) {
      e.preventDefault();
      var href = $(this).attr('href');
      _scrollBody($(href), 500, 0, true);
    });

    // Bigclicky™
    $document.on('click', '.bigclicky', function(e) {
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
    _hangQuotes();
    _truncateLists();
    _initInfiniteScroll();
    _initSearchStickyHeaders($body[0]);

    // After page loads
    $(window).on('load',function() {
      // Trigger delayed resize functions after load (layout isotope, etc)
      _delayed_resize();
      // Scroll down to hash after page load
      if (window.location.hash) {
        _scrollBody($(window.location.hash), 250, 0, true);
      }
    });

  } // end init()

  function _closeSitewideNotice() {
    $('.sitewide-notice').velocity('slideUp', { duration: 250 });
    // var expiresWhen = new Date(new Date().getTime() + 7 * 24 * 60 * 60 * 1000);
    var expiresWhen = new Date(new Date().getTime() + 5 * 60 * 1000);
    Cookies.set('aei-notice-shown', 'yes', {
      expires: expiresWhen
    });
  }

  // Forever scrolling... scrolling...
  function _initInfiniteScroll() {
    var $infScrollContainer = $('.infinite-scroll-container');
    if ($infScrollContainer.length) {
      // Infinite scroll
      $infScrollContainer.infiniteScroll({
        path: function() {
          // Are there more pages?
          if (this.pageIndex < parseInt($('.pagination').attr('data-total-pages'))) {
            // Replace /p2 with this.loadCount + 1 from infinite scroll
            var nextUrl = $('.pagination .next a').attr('href').replace(/(p[\d]+)$/, 'p' + (this.loadCount + 2));
            // Omit the featured post if there is one
            nextUrl += '?omitId=' + ($('.featured-article').attr('data-id') || '');
            return nextUrl;
          } else {
            return false;
          }
        },
        append: false,
        history: false
      });
      $infScrollContainer.on('load.infiniteScroll', function(event, response) {
        var $items = $(response).find('.infinite-scroll-object');
        $(this).append($items);
        if ($masonryGrid.length) {
          $masonryGrid.masonry('appended', $items);
        }
        _initLazyload();
      });
    }
  }

  // Truncate longer lists with View More link
  function _truncateLists() {
    $('ul.truncate-list').each(function() {
      var $longBlock = $(this);
      var $lis = $longBlock.find('li');
      if ($lis.length > 5) {
        $longBlock.find('li:gt(4)').hide();
        var $moreLink = $('<p><a class="expand-list" href="#">View More (' + ($lis.length - 5) + ')</a></p>').insertAfter($longBlock);
        $moreLink.on('click', function(e) {
          e.preventDefault();
          $longBlock.find('li').slideDown(200);
          $moreLink.remove();
        });
      }
    });
  }

  function _initFilters() {
    $('.mobile-filter').each(function() {
      $this = $(this);

      // Click on header to expand filters
      $this.find('.filter-header').on('click', function(e) {
        e.preventDefault();
        $this.toggleClass('active');
        if ($this.hasClass('active')) {
          $this.find('.filters').velocity('slideDown', { duration: 150, easing: 'easeOut' });
        } else {
          $this.find('.filters').velocity('slideUp', { duration: 150, easing: 'easeOut' });
        }
        setTimeout(_checkFilterBodyScroll, 150);
      });

      // Make filter sticky
      new Waypoint.Sticky({
        element: $this[0],
        handler: function(direction) {
          setTimeout(_checkFilterBodyScroll, 150);
        }
      });
    });
  }

  function _checkFilterBodyScroll() {
    var $el = $('.mobile-filter');
    if (!breakpoint_sm && $el.is('.active.stuck')) {
      _disableBodyScroll($el[0]);
    } else {
      _enableBodyScroll($el[0]);
    }
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

  // Code for sticky header
  function _initStickyHeader() {

    // Make sidebar sticky if present
    $('.sidebar').each(function() {
      new Waypoint.Sticky({
        element: this,
        wrapper: '<div class="sidebar-sticky-wrapper" />'
      });
    });

    // Make StickyHeader class
    function StickyHeader() {

      var me = this;
      var $header = $('#sticky-header');
      var scrollDownThreshold = $header.outerHeight();
      var scrollTop = $(window).scrollTop();
      var lastScrollTop = scrollTop;
      var scrolled, scrollingUp, stuck, lastScrolled, lastScrollingUp;
      var upThreshold = scrollDownThreshold * 2, turningPoint = 0;

      // Determine whether header should be sticky
      this.refreshState = function() {
        lastScrollTop = scrollTop;
        scrollTop = $(window).scrollTop();

        // Are we scrolling down?
        lastScrolled = scrolled;
        scrolled = scrollTop > scrollDownThreshold && lastScrollTop < scrollTop;
        if (scrolled !== lastScrolled) {
          $header.addClass('-scrolled');
          $body.addClass('-scrolled');
        }

        // Are we scrolling up?
        lastScrollingUp = scrollingUp;
        scrollingUp = lastScrollTop > scrollTop;
        if (scrollingUp !== lastScrollingUp ) {
          turningPoint = scrollTop;
          if (!scrollingUp) {
            $header.removeClass('-stuck -scrolling-up -scrolled');
            $body.removeClass('nav-stuck');
            stuck = false;
          }
        } else {
          if (scrollingUp && (turningPoint - scrollTop > upThreshold)) {
            $header.addClass('-scrolling-up').removeClass('-scrolled');
            $body.addClass('nav-stuck').removeClass('-scrolled');

            if (!stuck) {
              $header.addClass('-stuck');
              stuck = true;
            }
          }
        }

        // Have we hit the top?
        if (scrollTop < 5) {
          stuck = false;
          $header.removeClass('-scrolled -stuck -scrolling-up');
          $body.removeClass('-scrolled nav-stuck');
        }
      };

      // Init the stickiheader and tie behavior to window events
      this.init = function() {

        // Start off in correct state
        me.refreshState();

        // Scroll Handling
        var lastMove = 0;
        var eventThrottle = 10;
        window.addEventListener('scroll', function() {
          var now = Date.now();
          if (now > lastMove + eventThrottle) {
            lastMove = now;
            me.refreshState();
          }
        });

        // Tell CSS the header is ready to reveal
        $header.removeClass('-unloaded');
      };

      // Fire away
      this.init();
    }

    // Make the header
    StickyHeader();
  }

  function _initSearch() {
    // Open/close behavior for search modal
    $document.on('click', '.search-open', function(e) {
      e.preventDefault();
      _openSearch();
    });
    $document.on('click', '.search-close', function(e) {
      e.preventDefault();
      _closeSearch();
    });

    // Init the overlay
    $('#search-overlay').show().velocity('fadeOut', { duration: 0 });

    // Pipe in search results on submit
    $document.on('submit', '#search-modal .search-form', function(e) {
      e.preventDefault();
      var $this = $(this);
      $.get($this.attr('action'), $this.serialize(), function(data) {
        var title = $(data).filter('title').text();
        // Already on search? Just replace it so back & closeSearch goes to previous non-search page
        if (!initialHref.match('/search?') && location.href.match('/search?')) {
          history.replaceState({'ajax': true} , title, $this.attr('action')+'?'+$this.serialize());
        } else {
          // Otherwise push search request to history, replace current with state object to track need to refresh
          history.replaceState({'ajax': true} , document.title, location.href);
          history.pushState({'ajax': true} , title, $this.attr('action')+'?'+$this.serialize());
        }
        var $content = $('#search-modal .results');

        // Populate search results
        $content.html(data);

        // Fancy fade in effect on desktop
        if (breakpoint_md) {
          $content.velocity('fadeOut', {duration: 0});
          $content.find('.search-section, .search-article').velocity('fadeOut', {duration: 0});

          var speed = 200;
          var delay = 40;
          var i = 0;
          var j = 0;
          $content.velocity('fadeIn', {duration: speed, delay: delay*(i++)}).find('.search-form input[name=q]').focus();
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
        }

        // Sticky the search column titles
        _initSearchStickyHeaders($('#search-modal .scroll-wrap')[0]);

        // Required to maintain width on sticky headers
        _fixStickyHeaderWidths();

      });
    });
  }

  function _initSearchStickyHeaders(context) {
    // Make header titles sticky
    $('.search-results .sticky-header').each(function() {
      $this = $(this);
      var sticky = new Waypoint.Sticky({
        element: $this[0],
        context: context,
        wrapper: '<div class="search-sticky-wrapper" />'
      });
    });
  }

  // Lock/unlock body from scrolling when modal is open (using https://github.com/willmcpo/body-scroll-lock)
  function _enableBodyScroll(el) {
    if (typeof el === 'undefined') {
      bodyScrollLock.clearAllBodyScrollLocks();
    } else {
      bodyScrollLock.enableBodyScroll(el);
    }
    $body.removeClass('no-scroll');
  }
  function _disableBodyScroll(el) {
    bodyScrollLock.clearAllBodyScrollLocks();
    $body.addClass('no-scroll');
    bodyScrollLock.disableBodyScroll(el);
  }

  function _openSearch() {
    // Hide mobile nav
    _closeNav();

    // Animate in the modal
    $('#search-modal').addClass('active');
    $('#search-overlay').velocity('fadeIn', { duration: 100, easing: 'easeOut' });

    // Prevent body scroll
    _disableBodyScroll($('#search-modal .scroll-wrap')[0]);

    // Empty results and fade in, focus on input
    $('#search-modal .results').empty();
    $('#search-modal .content').velocity('fadeIn', {
      duration: 200,
      complete: function() {
        $('#search-modal input[name=q]').focus();
      }
    });
  }

  function _closeSearch() {
    // Search modal has changed URL (if not originally on a /search page), go back to close modal and change URL
    if (!initialHref.match('/search?') && location.href.match('/search?')) {
      history.back();
    }
    // Otherwise just close modal
    $('#search-modal').removeClass('active');
    $('#search-modal .content').velocity('fadeOut', { duration: 100, easing: 'easeOut' });
    $('#search-overlay').velocity('fadeOut', { delay: 300, duration: 300, easing: 'easeOut' });

    // Enable body scrolling
    _enableBodyScroll($('#search-modal .scroll-wrap')[0]);
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
      $modal.velocity('slideUp', { duration: 0 });
      $('#contact-modal-overlay').velocity('fadeOut', { duration: 0 });

      // Init clicking behavior.
      $document.on('click', '.contact-modal-close', _closeContactModal);
      $document.on('click', '.contact-modal-open', _openContactModal);

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
        .velocity('fadeIn', { duration: 300, easing: 'easeOut', queue: false })
        .velocity('slideDown', { duration: 300, easing: 'easeOut' });

      $overlay
        .velocity('fadeIn', { duration: 200, easing: 'easeOut' });
    }
  }

  function _closeContactModal() {

    // If it exists, animate the modal closed and fade out the overlay.
    var $modal = $('#contact-modal');
    if ($modal.length) {

      var $overlay = $('#contact-modal-overlay');

      $modal
        .velocity('fadeOut', { duration: 300, easing: 'easeOut', queue: false })
        .velocity('slideUp', { duration: 300, easing: 'easeOut' });

      $overlay
        .velocity('fadeOut', { delay: 300, duration: 300, easing: 'easeOut' });
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
    element.velocity('scroll', {
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
    element.velocity('scroll', {
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
    $document.on('click', '.nav-close', function() {
      _closeNav();
    });
    $document.on('click', '.nav-open', function() {
      _openNav();
    });
    $document.on('click', '.nav-toggle', function() {
      _toggleNav();
    });
    $document.on('click', '.open-filters', function() {
      _openNav();
      _scrollContainer($siteNav, $('.filters'), 250, 250);
    });
  }

  function _openNav() {
    $body
      .addClass('site-nav-open no-scroll')
      .removeClass('site-nav-closed');
    _disableBodyScroll($siteNav[0]);
  }

  function _closeNav() {
    $body
      .removeClass('site-nav-open no-scroll')
      .addClass('site-nav-closed');
    _enableBodyScroll($siteNav[0]);
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
    $document.keyup(function(e) {
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

      // Refix waypoints
      Waypoint.refreshAll();

      // Resize fixed headers
      _fixStickyHeaderWidths();

      // Refresh masonry after delay
      if ($masonryGrid.length) {
        $masonryGrid.masonry();
      }

    }, 250);
  }

  function _fixStickyHeaderWidths() {
    $('.sticky-header').each(function() {
      var parentWidth = $(this).parent().width();
      $(this).width(parentWidth);
    });
  }

  function _initMasonry() {
    if ($masonryGrid.length) {
      $masonryGrid.masonry({
        percentPosition: true,
        transitionDuration: 0,
        itemSelector: '.masonry-item',
        columnWidth: '.masonry-sizer'
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

// Slo-mo zig-zag the mothership
jQuery(window).resize(FB.delayed_resize);
