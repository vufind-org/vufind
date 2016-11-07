/* https://github.com/crhallberg/autocomplete.js 0.16.3 */
(function autocomplete( $ ) {
  var cache = {},
    element = false,
    options = { // default options
      ajaxDelay: 200,
      cache: true,
      hidingClass: 'hidden',
      highlight: true,
      loadingString: 'Loading...',
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
    input.data('length', data.length);
    // highlighting setup
    // escape term for regex - https://github.com/sindresorhus/escape-string-regexp/blob/master/index.js
    var escapedTerm = input.val().replace(/[|\\{}()\[\]\^$+*?.]/g, '\\$&');
    var regex = new RegExp('(' + escapedTerm + ')', 'ig');
    var shell = $('<div/>');
    for (var i = 0; i < data.length; i++) {
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
    element.find('.item').mousedown(function acItemClick() {
      populate($(this).data(), input, {mouse: true});
      setTimeout(function acClickDelay() {
        input.focus();
        hide();
      }, 10);
    });
    align(input);
  }

  function handleResults(input, term, data) {
    // Limit results
    var data = data.slice(0, Math.min(options.maxResults, data.length));
    var cid = input.data('cache-id');
    cache[cid][term] = data;
    if (data.length === 0) {
      hide();
    } else {
      createList(data, input);
    }
  }
  function search(input) {
    if (xhr) { xhr.abort(); }
    if (input.val().length >= options.minLength) {
      element.html('<i class="item loading">' + options.loadingString + '</i>');
      show();
      align(input);
      input.data('selected', -1);
      var term = input.val();
      // Check cache (only for handler-based setups)
      var cid = input.data('cache-id');
      if (options.cache && typeof cache[cid][term] !== "undefined") {
        if (cache[cid][term].length === 0) {
          hide();
        } else {
          createList(cache[cid][term], input);
        }
      // Check for static list
      } else if (typeof options.static !== 'undefined') {
        var lcterm = term.toLowerCase();
        var matches = options.static.filter(function staticFilter(_item) {
          return _item.match.match(lcterm);
        });
        if (typeof options.staticSort === 'function') {
          matches.sort(options.staticSort);
        } else {
          matches.sort(function defaultStaticSort(a, b) {
            return a.match.indexOf(lcterm) - b.match.indexOf(lcterm);
          });
        }
        handleResults(input, term, matches);
      // Call handler
      } else {
        options.handler(input, function achandlerCallback(data) {
          handleResults(input, term, data);
        });
      }
    } else {
      hide();
    }
  }

  function setup(input) {
    if ($('.autocomplete-results').length === 0) {
      element = $('<div/>')
        .addClass('autocomplete-results hidden')
        .html('<i class="item loading">' + options.loadingString + '</i>');
      align(input);
      $(document.body).append(element);
    }

    input.data('selected', -1);
    input.data('length', 0);

    if (options.cache) {
      var cid = Math.floor(Math.random() * 1000);
      input.data('cache-id', cid);
      cache[cid] = {};
    }

    input.blur(function acinputBlur(e) {
      if (e.target.acitem) {
        setTimeout(hide, 10);
      } else {
        hide();
      }
    });
    input.click(function acinputClick() {
      search(input, element);
    });
    input.focus(function acinputFocus() {
      search(input, element);
    });
    input.keyup(function acinputKeyup(event) {
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
    input.keydown(function acinputKeydown(event) {
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
          element.find('.item:eq(' + position + ')').addClass('selected');
        }
        $(this).data('selected', position);
        break;
      case 40: // down key
        event.preventDefault();
        if (element.hasClass(options.hidingClass)) {
          search(input, element);
        } else if (position < input.data('length') - 1) {
          position++;
          element.find('.item.selected').removeClass('selected');
          element.find('.item:eq(' + position + ')').addClass('selected');
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

  $.fn.autocomplete = function acJQuery(settings) {

    return this.each(function acJQueryEach() {

      var input = $(this);

      if (typeof settings === "string") {
        if (settings === "show") {
          show();
          align(input);
        } else if (settings === "hide") {
          hide();
        } else if (options.cache && settings === "clear cache") {
          var cid = parseInt(input.data('cache-id'), 10);
          cache[cid] = {};
        }
        return input;
      } else if (typeof settings.handler === 'undefined' && typeof settings.static === 'undefined') {
        console.error('Neither handler function nor static result list provided for autocomplete');
        return this;
      } else {
        if (typeof settings.static !== 'undefined') {
          // Preprocess strings into items
          settings.static = settings.static.map(function preprocessStatic(_item) {
            var item = typeof _item === 'string'
              ? { value: _item }
              : _item;
            item.match = (item.label || item.value).toLowerCase();
            return item;
          });
        }
        options = $.extend( {}, options, settings );
        setup(input);
      }

      return input;

    });
  };

  var timer = false;
  $.fn.autocomplete.ajax = function acAjax(ops) {
    if (timer) { clearTimeout(timer); }
    if (xhr) { xhr.abort(); }
    timer = setTimeout(
      function acajaxDelay() { xhr = $.ajax(ops); },
      options.ajaxDelay
    );
  };

}( jQuery ));
