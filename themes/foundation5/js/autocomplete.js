/*global jQuery, window, document, console, setTimeout, clearTimeout */
/**
 * crhallberg/autocomplete.js 0.14
 * ~ @crhallberg
 */
(function ( $ ) {
  var cache = {},
      element = false,
      input = false,
      options = {
        ajaxDelay: 200,
        cache: true,
        hidingClass: 'hide',
        highlight: true,
        loadingString: 'Loading ...',
        maxResults: 20,
        minLength: 3
      };

  var xhr = false;

  function align(input) {
      var position = input.offset();
      element.css({
        top: position.top + input.outerHeight(),
        left: position.left,
        minWidth: input.width(),
        maxWidth: Math.max(input.width(), input.closest('form').width())
      });
    }

    function show() {
    element.removeClass(options.hidingClass);
    }
    function hide() {
    element.addClass(options.hidingClass);
    }

  function populate(data, input, eventType) {
    input.val(data.value);
    input.data('selection', data);
    if (options.callback) {
      options.callback(data, input, eventType);
    }
      hide();
    }

    function createList(data, input) {
    // Limit results
    data = data.slice(0, Math.min(options.maxResults, data.length));
    input.data('length', data.length);
    // highlighting setup
    // escape term for regex - https://github.com/sindresorhus/escape-string-regexp/blob/master/index.js
    var escapedTerm = input.val().replace(/[|\\{}()\[\]\^$+*?.]/g, '\\$&');
    var regex = new RegExp('('+escapedTerm+')', 'ig');
      var shell = $('<div/>');
    for (var i=0; i<data.length; i++) {
        if (typeof data[i] === 'string') {
        data[i] = {value: data[i]};
        }
      var content = data[i].label || data[i].value;
        if (options.highlight) {
          content = content.replace(regex, '<b>$1</b>');
        }
        var item = typeof data[i].href === 'undefined'
          ? $('<div/>')
          : $('<a/>').attr('href', data[i].href);
      // Data
      item.data(data[i])
            .addClass('item')
            .html(content);
        if (typeof data[i].description !== 'undefined') {
        item.append($('<small/>').html(
          options.highlight
            ? data[i].description.replace(regex, '<b>$1</b>')
            : data[i].description
        ));
        }
        shell.append(item);
      }
    element.html(shell);
    element.find('.item').mousedown(function() {
      populate($(this).data(), input, {mouse: true});
        setTimeout(function() {
          input.focus();
          hide();
        }, 10);
      });
    align(input);
    }

  function search(input) {
      if (xhr) { xhr.abort(); }
      if (input.val().length >= options.minLength) {
        element.html('<i class="item loading">'+options.loadingString+'</i>');
        show();
      align(input);
        var term = input.val();
        var cid = input.data('cache-id');
      if (options.cache && typeof cache[cid][term] !== "undefined") {
        if (cache[cid][term].length === 0) {
            hide();
          } else {
          createList(cache[cid][term], input);
          }
      } else {
          options.handler(input.val(), function(data) {
          cache[cid][term] = data;
            if (data.length === 0) {
              hide();
            } else {
            createList(data, input);
            }
          });
        }
        input.data('selected', -1);
      } else {
        hide();
      }
    }

    function setup(input, element) {
      if (typeof element === 'undefined') {
        element = $('<div/>')
          .addClass('autocomplete-results hide')
          .html('<i class="item loading">'+options.loadingString+'</i>');
      align(input);
        $(document.body).append(element);
      }

      input.data('selected', -1);
      input.data('length', 0);

      if (options.cache) {
        var cid = Math.floor(Math.random()*1000);
        input.data('cache-id', cid);
      cache[cid] = {};
      }

      input.blur(function(e) {
        if (e.target.acitem) {
          setTimeout(hide, 10);
        } else {
          hide();
        }
      });
      input.click(function() {
        search(input, element);
      });
      input.focus(function() {
        search(input, element);
      });
      input.keyup(function(event) {
        // Ignore navigation keys
        // - Ignore control functions
        if (event.ctrlKey || event.which === 17) {
          return;
        }
        // - Function keys (F1 - F15)
        if (112 <= event.which && event.which <= 126) {
          return;
        }
        switch (event.which) {
          case 9:    // tab
          case 13:   // enter
          case 16:   // shift
          case 20:   // caps lock
          case 27:   // esc
          case 33:   // page up
          case 34:   // page down
          case 35:   // end
          case 36:   // home
          case 37:   // arrows
          case 38:
          case 39:
          case 40:
          case 45:   // insert
          case 144:  // num lock
          case 145:  // scroll lock
          case 19:   // pause/break
            return;
          default:
            search(input, element);
        }
      });
      input.keydown(function(event) {
        // - Ignore control functions
        if (event.ctrlKey || event.which === 17) {
          return;
        }
        var position = $(this).data('selected');
        switch (event.which) {
          // arrow keys through items
        case 38: // up key
            event.preventDefault();
            element.find('.item.selected').removeClass('selected');
          if (position-- > 0) {
              element.find('.item:eq('+position+')').addClass('selected');
          }
              $(this).data('selected', position);
            break;
        case 40: // down key
            event.preventDefault();
          if (element.hasClass(options.hidingClass)) {
              search(input, element);
            } else if (position < input.data('length')-1) {
              position++;
              element.find('.item.selected').removeClass('selected');
              element.find('.item:eq('+position+')').addClass('selected');
              $(this).data('selected', position);
            }
            break;
          // enter to nav or populate
          case 9:
          case 13:
            var selected = element.find('.item.selected');
            if (selected.length > 0) {
              event.preventDefault();
              if (event.which === 13 && selected.attr('href')) {
              window.location.assign(selected.attr('href'));
              } else {
              populate(selected.data(), $(this), {key: true});
                element.find('.item.selected').removeClass('selected');
                $(this).data('selected', -1);
              }
            }
            break;
          // hide on escape
          case 27:
            hide();
            $(this).data('selected', -1);
            break;
        }
      });

      window.addEventListener("resize", hide, false);

      return element;
    }

  $.fn.autocomplete = function(settings) {

    if ('undefined' == typeof settings.handler) {
      console.error('handler function not provided for autocomplete');
      return this;
    }

    options = $.extend( {}, options, settings );

    return this.each(function() {

      input = $(this);

      if (typeof settings === "string") {
        if (settings === "show") {
          show();
          align(input);
        } else if (settings === "hide") {
          hide();
        } else if (settings === "clear cache" && options.cache) {
          var cid = parseInt(input.data('cache-id'), 10);
          cache[cid] = {};
        }
        return input;
      } else {
        element = $('.autocomplete-results');
        if (element.length == 0) {
          element = setup(input);
        } else {
          setup(input, element);
        }
      }

      return input;

    });
  };

  var timer = false;
    $.fn.autocomplete.ajax = function(ops) {
      if (timer) { clearTimeout(timer); }
      if (xhr) { xhr.abort(); }
      timer = setTimeout(
        function() { xhr = $.ajax(ops); },
      options.ajaxDelay
      );
    };

}( jQuery ));