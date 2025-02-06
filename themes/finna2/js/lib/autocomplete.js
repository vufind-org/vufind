// Modified from /themes/bootstrap3/js/autocomplete.js

/*global extractClassParams, VuFind*/
/**
 * vufind.typeahead.js 0.10
 * ~ @crhallberg (original version)
 * ~ @samuli (modifications)
 * ~ @emaijala (modifications)
 * @param {any} $ jQuery base
 */
(function autocompleteLib( $ ) {
  var xhr = false;
  var searchTimer = false;

  // Disable original autocomplete by providing just a stub function instead
  $.fn.autocomplete = function autocompleteOriginal() {
  };

  $.fn.autocompleteFinna = function autocompleteFinna(settings) {
    var options = $.extend( {}, $.fn.autocompleteFinna.options, settings );
    if (options.minLength < 5) {
      options.minLength = 5;
    }

    // Use input position from setup or focus event with IE Mobile to avoid
    // trouble with changing offset of the input field when the keyboard is
    // displayed (IE Mobile does something quite weird here).
    var autocompleteTop = 0;

    /**
     * Align the element properly with the input
     * @param {jQuery} input Input to align to
     * @param {jQuery} element Autocomplete container element to align
     */
    function align(input, element) {
      var position = input.offset();
      var iemobile = navigator.userAgent.match(/iemobile/i);
      element.css({
        position: 'absolute',
        top: iemobile ? autocompleteTop : position.top + input.outerHeight(),
        left: position.left,
        minWidth: input.width(),
        maxWidth: Math.max(input.width(), input.closest('form').width()),
        zIndex: 1100
      });
    }

    /**
     * Show autocomplete
     */
    function show() {
      $.fn.autocompleteFinna.element.removeClass(options.hidingClass);
    }
    /**
     * Hide autocomplete
     */
    function hide() {
      var element = $.fn.autocompleteFinna.element;
      element.find('.item').removeClass('selected');
      element.addClass(options.hidingClass);
    }

    /**
     * Populate items
     * @param {jQuery} item Item element
     * @param {jQuery} input Input of autocomplete
     * @param {string} eventType Type of the event to call in autocomplete select
     */
    function populate(item, input, eventType) {
      var type = item.data('type');
      var value = item.text();
      var form = input.closest('form');
      if (type === 'facet' || type === 'filter') {
        var filters = item.data('filters').split('&');
        $.each(filters, function eachFilter(i, _filter) {
          var filter = _filter.split('#').join(':');
          $('<input>')
            .attr('type', 'hidden').attr('name', 'filter[]')
            .val(filter)
            .appendTo(form);
        });
      } else if (type === 'handler') {
        var handler = item.data('handler');
        form.find('input[name=type]').val(handler);
      } else if (type === 'isbn') {
        window.location.href = item.attr('href');
        return;
      } else {
        input.val(value);
      }
      hide();
      input.trigger('autocomplete:select', {item: item, value: value, eventType: eventType});
    }

    /**
     * Get state of "preserve filters" toggle
     * @param {HTMLElement} input Input to use to find applied df filters
     * @returns {boolean} Should filters be preserved?
     */
    function getPreserveFiltersMode(input) {
      return $(input).closest('form').find('.applied-filter[name=dfApplied]').is(':checked');
    }

    /**
     * Cover callback handler
     * @param {object} response Object containing response data
     */
    function coverCallback(response) {
      if (response.data !== undefined) {
        var img = $('#ac-recordcover');
        img[0].src = response.data.url;
        img[0].onload = function onImgLoad() {
          if (this.naturalWidth && this.naturalWidth !== 10 && this.naturalHeight !== 10) {
            img.toggleClass('hidden');
            $('.ac-isbn .iconlabel').toggleClass('hidden');
          }
        };
      }
    }

    /**
     * Create autocomplete list
     * @param {Array} data Array containing data for filters and suggestions
     * @param {jQuery} input Element to form autocomplete to
     */
    function createList(data, input) {
      var shell = $('<div/>');
      var length = data.length;
      input.data('length', length);
      for (var i = 0; i < length; i++) {
        // To prevent conflicts, show filters only when
        // preserve-filters option is not checked
        if (data[i].type === 'filter' && getPreserveFiltersMode(input)) {
          continue;
        }
        var content = data[i].label;
        if (options.highlight && data.type === 'suggestion') {
          // escape term for regex
          // https://github.com/sindresorhus/escape-string-regexp/blob/master/index.js
          var escapedTerm = input.val().replace(/[|\\{}()[\]^$+*?.]/g, '\\$&');
          // Get the search terms as html
          escapedTerm = $('<div/>').text(escapedTerm).html();
          var regex = new RegExp('(' + escapedTerm + ')', 'ig');
          content = content.replace(regex, '<b>$1</b>');
        } else {
          // Get the search terms as html
          content = $('<div/>').text(content).html();
        }
        var item = $('<div/>');

        item.attr('data-index', i + 0)
          .attr('data-value', data[i].val)
          .attr('data-type', data[i].type)
          .addClass('item')
          .addClass(data[i].css)
          .html(content);

        if ("handler" in data[i]) {
          item.attr('data-handler', data[i].handler);
          item.attr('data-title', VuFind.translate(data[i].handler));
        }
        var type = data[i].type;
        if (type === 'phrase') {
          item.attr('data-title', VuFind.translate('autocomplete_phrase'));
          var query = $('.searchForm_lookfor').val().trim();
          if (query.indexOf('"') !== -1) {
            item.hide();
          }
        } else if (type === 'facet' || type === 'filter') {
          item.attr('data-filters', data[i].filters);
        } else if (type === 'isbn') {
          item.attr('data-title', data[i].match.title);
          item.attr('href', data[i].match.url);
          item.append('<br>');
          item.append(document.createTextNode('ISBN: ' + data[i].match.isbn));
          item.wrapInner($('<span>'));
          item.prepend('<div class="iconlabel format-1bookbook"></div>');
          item.prepend($('<img />')
            .attr('id', 'ac-recordcover')
            .attr('data-recordid', data[i].match.recordId)
            .addClass('hidden'));
          item.wrapInner($('<div class="ac-isbn">'));

          var url = VuFind.path + '/AJAX/JSON?method=' + 'getRecordCover';
          var imgdata = {
            recordId: data[i].match.recordId,
            size: 'small'
          };
          $.ajax({
            dataType: "json",
            url: url,
            method: "GET",
            data: imgdata,
            success: coverCallback
          });

        }

        if (typeof data[i].description !== 'undefined') {
          item.append($('<small/>').text(data[i].description));
        }
        shell.append(item);
      }

      $.fn.autocompleteFinna.element.html(shell);

      if (!shell.children().length) {
        hide();
        return;
      }

      $(['isbn', 'suggestion', 'facet', 'filter', 'phrase', 'handler']).each(function eachSection(ind, obj) {
        var label = 'autocomplete_section_' + obj;
        var translated = VuFind.translate(label);
        var wrapper = $('<div/>').addClass('group ' + obj);
        if (label !== translated) {
          wrapper.attr('data-title', translated);
        }
        var items = shell.find('.item.' + obj + ':visible');
        if (items.length) {
          items.wrapAll(wrapper);
        }
      });

      shell.find('.item').first().addClass('first');
      shell.find('.item').last().addClass('last');

      $.fn.autocompleteFinna.element.find('.item').mousedown(function onMouseDown() {
        populate($(this), input, {mouse: true});
      });
      align(input, $.fn.autocompleteFinna.element);
    }

    var parseResponse = function parseResponse(data, filters, handlers, phraseSearch) {
      var datums = [];
      if (data.length) {
        // ISBN match
        if (data[2].length !== 0) {
          datums.push({label: data[2].title, type: 'isbn', css: 'isbn', match: data[2]});
        }
        // Suggestions
        if (typeof data[0] === 'string') {
          // Basic suggestions
          $.map(data, function mapBasic(obj) {
            datums.push({label: obj, css: 'suggestion', type: 'suggestion'});
            return obj;
          });
        } else {
          // Extended suggestions
          $.map(data[0], function mapExtended(obj) {
            datums.push({label: obj, css: 'suggestion', type: 'suggestion'});
            return obj;
          });
        }
      }

      // Filters
      if (filters) {
        $.each(filters.split('||'), function eachFilter(i, filter) {
          var f = filter.split('|');
          var label = VuFind.translate(f[0]);
          datums.push({
            label: label, filters: f[1],
            type: 'filter', css: 'filter filter-' + f[0]
          });
        });
      }

      // Facets
      if (data.length > 1 && typeof data[0] !== 'string') {
        $.each(data[1], function eachFacet(facet, facetData) {
          $.map(facetData, function mapFacet(item) {
            var label = item[0] + ' (' + item[1] + ')';
            datums.push({
              label: label,
              type: 'facet',
              css: 'facet ' + facet + ' ' + item[2].split('/')[1],
              facet: facet,
              filters: item[2]
            });
          });
        });
      }

      var query = $('.searchForm_lookfor').val().trim();

      // Phrase search
      if (phraseSearch) {
        datums.push({label: '"' + query + '"', type: 'phrase', css: 'phrase'});
      }

      // Handlers
      if (handlers) {
        $.each(handlers.split('|'), function eachHandler(i, handler) {
          if (handler === 'AllFields') {
            return true;
          }
          datums.push({
            label: query, type: 'handler',
            handler: handler, css: 'handler handler-' + handler
          });
        });
      }
      return datums;
    };

    /**
     * Get search handler
     * @param {jQuery} input Input to get applied filter named type with
     * @returns {string} Current applied search handler
     */
    function getSearchHandler(input) {
      var form = $(input).closest('form');
      var handler = form.find('input[name=type]').not('.applied-filter');
      if (handler.length) {
        return handler.val();
      }
      return form.find('.applied-filter[name=type]').val();
    }

    /**
     * Perform a search with debounce
     * @param {jQuery} input Element to listen for writing
     * @param {jQuery} element Autocomplete element container
     */
    function search(input, element) {
      if (searchTimer) { clearInterval(searchTimer); }
      if (input.val().length >= options.minLength) {
        var ajaxDelay = $.fn.autocompleteFinna.options.ajaxDelay;
        if (ajaxDelay < 1500) {
          ajaxDelay = 1500;
        }
        searchTimer = setInterval(
          function intervalTimer() {
            if (xhr && (xhr === true || xhr.state() === 'pending')) {
              return;
            }
            clearInterval(searchTimer);

            element.html('<i class="item loading">' + options.loadingString + '</i>');
            show();
            align(input, $.fn.autocompleteFinna.element);

            var term = [];
            term.push(input.val());
            term.push(getSearchHandler(input));
            term.push(getPreserveFiltersMode(input) ? "1" : "0");
            term = term.join('###');
            var cid = input.data('cache-id');
            if (options.cache && typeof $.fn.autocompleteFinna.cache[cid][term] !== "undefined") {
              if ($.fn.autocompleteFinna.cache[cid][term].length === 0) {
                hide();
              } else {
                createList($.fn.autocompleteFinna.cache[cid][term], input);
              }
            } else if (typeof options.handler !== "undefined") {
              xhr = true;
              options.handler(input.val(), function optionsHandler(data) {
                if (data.length === 0 && options.suggestions) {
                  $.fn.autocompleteFinna.cache[cid][term] = [];
                  hide();
                } else {
                  var searcher = extractClassParams(input);
                  var filters = null;
                  if (!("onlySuggestions" in searcher) || String(searcher.onlySuggestions) !== '1') {
                    filters = "filters" in searcher ? searcher.filters : null;
                  }
                  var handlers = "handlers" in searcher ? searcher.handlers : null;
                  var phrase = "phrase" in searcher ? searcher.phrase : null;

                  var response = parseResponse(data, filters, handlers, phrase);
                  createList(response, input);
                  $.fn.autocompleteFinna.cache[cid][term] = response;
                }
              });
            }
            input.data('selected', -1);
          },
          ajaxDelay
        );
      } else {
        hide();
      }
    }

    /**
     * Update autocomplete top for absolute positioning
     * @param {jQuery} input Input to use as anchor
     */
    function updateAutocompleteTop(input) {
      autocompleteTop = input.offset().top + input.outerHeight();
    }

    /**
     * Set up autocomplete
     * @param {jQuery} input Element to use the autocomplete feature
     * @param {jQuery} _element Container holding autocomplete results
     * @returns {jQuery} Autocomplete element container
     */
    function setup(input, _element) {
      var element;
      if (typeof _element !== 'undefined') {
        element = _element;
      } else {
        element = $('<div/>')
          .addClass('autocomplete-results hidden')
          .html('<i class="item loading">' + options.loadingString + '</i>');
        align(input, element);
        $(document.body).append(element);
      }

      updateAutocompleteTop(input);
      input.data('selected', -1);
      input.data('length', 0);

      if (options.cache) {
        var cid = Math.floor(Math.random() * 1000);
        input.data('cache-id', cid);
        $.fn.autocompleteFinna.cache[cid] = {};
      }

      input.blur(function onInputBlur(e) {
        if (searchTimer) {
          clearInterval(searchTimer);
        }
        if (e.target.acitem) {
          setTimeout(hide, 10);
        } else {
          hide();
        }
      });
      input.on('click', function onInputClick() {
        search(input, element);
      });
      input.focus(function onInputFocus() {
        updateAutocompleteTop(input);
        search(input, element);
      });
      input.keyup(function onInputKeyUp(event) {
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
        case 9: // tab
        case 13: // enter
        case 16: // shift
        case 20: // caps lock
        case 27: // esc
        case 33: // page up
        case 34: // page down
        case 35: // end
        case 36: // home
        case 37: // arrows
        case 38:
        case 39:
        case 40:
        case 45: // insert
        case 144: // num lock
        case 145: // scroll lock
        case 19: // pause/break
          return;
        default:
          search(input, element);
        }
      });
      input.keydown(function onInputKeyDown(event) {
        // - Ignore control functions
        if (event.ctrlKey || event.which === 17) {
          return;
        }
        var position = $(this).data('selected');
        switch (event.which) {
        // arrow keys through items
        case 38:
          event.preventDefault();
          element.find('.item.selected').removeClass('selected');
          if (position > 0) {
            position--;
            element.find('.item:eq(' + position + ')').addClass('selected');
            $(this).data('selected', position);
          } else {
            $(this).data('selected', -1);
          }
          break;
        case 40:
          event.preventDefault();
          if ($.fn.autocompleteFinna.element.hasClass(options.hidingClass)) {
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
              location.assign(selected.attr('href'));
            } else {
              populate(selected, $(this), element, {key: true});
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

      if (
        typeof options.data === "undefined" &&
          typeof options.handler === "undefined" &&
          typeof options.preload === "undefined" &&
          typeof options.remote === "undefined"
      ) {
        return input;
      }

      $(window).on("resize", function onResize() {
        updateAutocompleteTop(input);
        hide();
      });

      return element;
    }

    return this.each(function eachThis() {

      var input = $(this);

      if (typeof settings === "string") {
        if (settings === "show") {
          show();
          align(input, $.fn.autocompleteFinna.element);
        } else if (settings === "hide") {
          hide();
        } else if (settings === "clear cache" && options.cache) {
          var cid = parseInt(input.data('cache-id'));
          $.fn.autocompleteFinna.cache[cid] = {};
        }
        return input;
      } else if (!$.fn.autocompleteFinna.element) {
        $.fn.autocompleteFinna.element = setup(input);
      } else {
        setup(input, $.fn.autocompleteFinna.element);
      }

      return input;
    });
  };

  if (typeof $.fn.autocompleteFinna.cache === 'undefined') {
    $.fn.autocompleteFinna.cache = {};
    $.fn.autocompleteFinna.element = false;
    $.fn.autocompleteFinna.options = {
      ajaxDelay: 500,
      cache: true,
      hidingClass: 'hidden',
      highlight: true,
      loadingString: 'Loading...',
      minLength: 3,
      suggestions: true
    };
    $.fn.autocompleteFinna.ajax = function ajax(ops) {
      if (xhr && xhr !== true) {
        xhr.abort();
        xhr = false;
      }
      xhr = $.ajax(ops);
    };
  }

}( jQuery ));
