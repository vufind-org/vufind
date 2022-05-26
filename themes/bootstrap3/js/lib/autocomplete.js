/* https://github.com/vufind-org/autocomplete.js 1.0b2 */
(function autocomplete($) {
  var element = false,
    cache = {},
    optionStorage = {},
    xhr = false;

  function Factory(_input, settings) {
    return function acClosure() {
      var input = $(this);
      var options = optionStorage[input.data('ac-id')];

      var _align = function _align() {
        var position = input.offset();
        element.css({
          top: position.top + input.outerHeight(),
          minWidth: input.width()
        });
        if (options.rtl) {
          element.css("right", document.body.offsetWidth - position.left - input.outerWidth());
        } else {
          element.css("left", position.left);
        }
      };

      var _show = function _show() {
        element.removeClass(options.hidingClass);
      };
      var hide = function hide() {
        element.addClass(options.hidingClass);
      };

      var _populate = function _populate(item, eventType) {
        input.trigger('ac:select', [item, eventType]);
        var ret;
        if (options.callback) {
          ret = options.callback(item, input, eventType);
          if (ret === true && typeof item.href !== 'undefined') {
            return window.location.assign(item.href);
          }
          if (ret === false) {
            return false;
          }
        } else if (typeof item.href !== 'undefined') {
          return window.location.assign(item.href);
        }
        input.val(typeof ret === 'undefined' ? item.value : ret);
        // Reset
        element.find('.ac-item.selected').removeClass('selected');
        $(this).data('ac-selected', -1);
        setTimeout(function acPopulateDelay() {
          input.focus();
          hide();
        }, 10);
      };

      var _listToHTML = function _listToHTML(list, regex) {
        var shell = $('<div/>');
        for (var i = 0; i < list.length; i++) {
          if (typeof list[i] === 'string') {
            list[i] = { value: list[i] };
          }
          var content = list[i].label || list[i].value;
          if (options.highlight) {
            content = content.replace(regex, '<b>$1</b>');
          }
          var item = typeof list[i].href === 'undefined' ? $('<div/>') : $('<a/>').attr('href', list[i].href);
          // list
          item
            .data(list[i])
            .addClass('ac-item')
            .html(content);
          if (typeof list[i].description !== 'undefined') {
            item.append(
              $('<small/>').html(
                options.highlight ? list[i].description.replace(regex, '<b>$1</b>') : list[i].description
              )
            );
          }
          shell.append(item);
        }
        input.trigger('ac:render', [shell[0]]);
        return shell;
      };
      var _createList = function _createList(data) {
        // highlighting setup
        // escape term for regex - https://github.com/sindresorhus/escape-string-regexp/blob/master/index.js
        var escapedTerm = input.val().replace(/[|\\{}()\[\]\^$+*?.]/g, '\\$&');
        var regex = new RegExp('(' + escapedTerm + ')', 'ig');
        var shell;
        if (typeof data.groups === 'undefined') {
          shell = _listToHTML(data, regex);
        } else {
          shell = $('<div/>');
          for (var i = 0; i < data.groups.length; i++) {
            if (typeof data.groups[i].label !== 'undefined' || i > 0) {
              shell.append($('<hr/>', { class: 'ac-section-divider' }));
            }
            if (typeof data.groups[i].label !== 'undefined') {
              shell.append(
                $('<header>', {
                  class: 'ac-section-header',
                  html: data.groups[i].label
                })
              );
            }
            if (typeof data.groups[i].label !== 'undefined' && data.groups[i].items.length > 0) {
              shell.append(_listToHTML(data.groups[i].items, regex));
            } else if (data.groups[i].length > 0) {
              shell.append(_listToHTML(data.groups[i], regex));
            }
          }
        }
        element.html(shell);
        input.data('ac-length', shell.find('.ac-item').length);
        element.find('.ac-item').mousedown(function acItemClick() {
          _populate($(this).data(), { mouse: true });
        });
        _align();
      };

      var _handleResults = function _handleResults(term, _data) {
        // Limit results
        var data =
          typeof _data.groups === 'undefined'
            ? _data.slice(0, Math.min(options.maxResults, _data.length))
            : _data;
        var cid = input.data('ac-id');
        cache[cid][term] = data;
        if (data.length === 0 || (typeof data.groups !== 'undefined' && data.groups.length === 0)) {
          hide();
        } else {
          _createList(data);
        }
      };
      var _defaultStaticSort = function _defaultStaticSort(a, b) {
        return a.match.indexOf(this) - b.match.indexOf(this);
      };
      var _staticGroups = function _staticGroups(lcterm) {
        var matches = [];
        function isTermMatch(_item) {
          return _item.match.match(lcterm);
        }
        for (var i = 0; i < options.static.groups.length; i++) {
          if (typeof options.static.groups[i].label !== 'undefined') {
            var mitems = options.static.groups[i].items.filter(isTermMatch);
            if (mitems.length > 0) {
              if (typeof options.staticSort === 'function') {
                mitems.sort(options.staticSort);
              } else {
                mitems.sort(_defaultStaticSort.bind(lcterm));
              }
              matches.push({
                label: options.static.groups[i].label,
                items: mitems
              });
            }
          } else {
            var ms = options.static.groups[i].filter(isTermMatch);
            if (ms.length > 0) {
              if (typeof options.staticSort === 'function') {
                ms.sort(options.staticSort);
              } else {
                ms.sort(_defaultStaticSort.bind(lcterm));
              }
              matches.push(ms);
            }
          }
        }
        return matches;
      };
      var search = function search() {
        if (xhr) {
          xhr.abort();
        }
        if (input.val().length >= options.minLength) {
          element.html('<i class="ac-item loading">' + options.loadingString + '</i>');
          _show();
          _align();
          input.data('ac-selected', -1);
          var term = input.val();
          // Check cache (only for handler-based setups)
          var cid = input.data('ac-id');
          if (options.cache && typeof cache[cid][term] !== 'undefined') {
            if (cache[cid][term].length === 0) {
              hide();
            } else {
              _createList(cache[cid][term]);
            }
            // Check for static list
          } else if (typeof options.static !== 'undefined') {
            var lcterm = term.toLowerCase();
            var matches;
            if (typeof options.static.groups !== 'undefined') {
              matches = { groups: _staticGroups(lcterm) };
            } else {
              matches = options.static.filter(function staticFilter(_item) {
                return _item.match.match(lcterm);
              });
              if (typeof options.staticSort === 'function') {
                matches.sort(options.staticSort);
              } else {
                matches.sort(_defaultStaticSort.bind(lcterm));
              }
            }
            _handleResults(term, matches);
            // Call handler
          } else {
            options.handler(input, function achandlerCallback(data) {
              _handleResults(term, data);
            });
          }
        } else {
          hide();
        }
      };

      function preprocessStatic(_item) {
        var item = typeof _item === 'string' ? { value: _item } : _item;
        item.match = (item.label || item.value).toLowerCase();
        return item;
      }
      var _setup = function _setup(settings) {
        var cid = Math.floor(Math.random() * 1000);
        input.data('ac-id', cid);
        input.data('ac-selected', -1);
        input.data('ac-length', 0);

        options = $.extend({}, $.fn.autocomplete.defaults, settings);
        optionStorage[input.data('ac-id')] = options;

        if (options.cache) {
          cache[cid] = {};
        }

        element = $('.autocomplete-results');
        if (element.length === 0) {
          element = $('<div/>')
            .addClass('autocomplete-results ' + options.hidingClass)
            .html('<i class="item loading">' + options.loadingString + '</i>');
          _align();
          $(document.body).append(element);
        }

        input.blur(function acinputBlur(e) {
          if (e.target.acitem) {
            setTimeout(hide, 10);
          } else {
            hide();
          }
        });
        input.click(function acinputClick() {
          search();
        });
        input.focus(function acinputFocus() {
          search();
        });
        input.on('paste', function acinputPaste() {
          requestAnimationFrame(search);
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
            search();
          }
        });
        input.keydown(function acinputKeydown(event) {
          // - Ignore control functions
          if (event.ctrlKey || event.which === 17) {
            return;
          }
          var position = $(this).data('ac-selected');
          switch (event.which) {
          // arrow keys through items
          case 38: // up key
            event.preventDefault();
            element.find('.ac-item.selected').removeClass('selected');
            if (position > -1) {
              if (position-- > 0) {
                element.find('.ac-item:eq(' + position + ')').addClass('selected');
              }
              $(this).data('ac-selected', position);
            }
            break;
          case 40: // down key
            event.preventDefault();
            if (element.hasClass(options.hidingClass)) {
              search();
            } else if (position < input.data('ac-length') - 1) {
              position++;
              element.find('.ac-item.selected').removeClass('selected');
              element.find('.ac-item:eq(' + position + ')').addClass('selected');
              $(this).data('ac-selected', position);
            }
            break;
            // enter to nav or populate
          case 9:
          case 13:
            var selected = element.find('.ac-item.selected');
            if (selected.length > 0) {
              event.preventDefault();
              if (event.which === 13 && selected.attr('href') && options.callback === 'undefined') {
                return window.location.assign(selected.attr('href'));
              } else {
                _populate(selected.data(), { key: true });
              }
            }
            break;
            // hide on escape
          case 27:
            hide();
            $(this).data('ac-selected', -1);
            break;
          }
        });

        window.addEventListener('resize', hide, false);
      };

      // Setup
      if (!input.data('ac-id')) {
        if (typeof settings === 'undefined') {
          console.error('Autocomplete not initialized, please pass setup parameters.');
        }
        if (typeof settings.handler === 'undefined' && typeof settings.static === 'undefined') {
          console.error('Neither handler function nor static result list provided for autocomplete');
          return null;
        }
        if (typeof settings.static !== 'undefined') {
          // Preprocess strings into items
          if (typeof settings.static.groups !== 'undefined') {
            for (var i = 0; i < settings.static.groups.length; i++) {
              if (typeof settings.static.groups[i].label !== 'undefined') {
                settings.static.groups[i].items = settings.static.groups[i].items.map(preprocessStatic);
              } else {
                settings.static.groups[i] = settings.static.groups[i].map(preprocessStatic);
              }
            }
          } else {
            settings.static = settings.static.map(preprocessStatic);
          }
        }
        _setup(settings);
      }

      return {
        show: function show() {
          _show();
          _align();
        },
        hide: hide,
        search: search,
        clearCache: function clearCache() {
          cache[input.data('ac-id')] = {};
        }
      };
    }.bind(_input)();
  }

  $.fn.autocomplete = function acJQuery(settings) {
    var ac;
    this.each(function acJQueryEach() {
      ac = Factory(this, settings);
    });
    return ac;
  };

  $.fn.autocomplete.defaults = {
    cache: true,
    hidingClass: 'hidden',
    highlight: true,
    loadingString: 'Loading...',
    maxResults: 20,
    minLength: 3,
    rtl: false
  };

  var timer = false;
  $.fn.autocomplete.ajax = function acAjax(ops) {
    if (timer) {
      clearTimeout(timer);
    }
    if (xhr) {
      xhr.abort();
    }
    timer = setTimeout(function acajaxDelay() {
      xhr = $.ajax(ops);
    }, 200);
  };
})(jQuery);
