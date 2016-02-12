// Modified from /themes/bootstrap3/js/autocomplete.js

/*global console*/
/**
 * vufind.typeahead.js 0.10
 * ~ @crhallberg (original version)
 * ~ @samuli (modifications)
 */
(function ( $ ) {
    var xhr = false;

    $.fn.autocomplete = function(settings) {
        var options = $.extend( {}, $.fn.autocomplete.options, settings );

        // Use input position from focus event to avoid trouble with Windows Phone
        // changing offset on keyboard
        var autocompleteTop = 0; 
        
        function align(input, element) {
            var position = input.offset();
            element.css({
                position: 'absolute',
                top: autocompleteTop,
                left: position.left,
                minWidth: input.width(),
                maxWidth: Math.max(input.width(), input.closest('form').width()),
                zIndex: 50
            });
        }

        function show() {
            $.fn.autocomplete.element.removeClass(options.hidingClass);
        }
        function hide() {
            var element = $.fn.autocomplete.element;
            element.find('.item').removeClass('selected');
            $.fn.autocomplete.element.addClass(options.hidingClass);
        }

        function populate(item, input, eventType) {
            var type = item.data('type');
            var value = item.text();
            var form = input.closest('form');
            if (type == 'facet' || type == 'filter') {
                var filters = item.data('filters').split('&');
                $.each(filters, function (i, filter) {
                    filter = filter.split('#').join(':');
                    $('<input/>')
                        .attr('type', 'hidden').attr('name', 'filter[]')
                        .val(filter)
                        .appendTo(form);
                });
            } else if (type == 'handler') {
                var handler = item.data('handler');
                var form = input.closest('form');
                form.find('input[name=type]').val(handler);
            } else {
                input.val(value);
            }
            hide();
            input.trigger('autocomplete:select', {item: item, value: value, eventType: eventType});
        }

        function createList(data, input) {
            var shell = $('<div/>');
            var length = data.length;
            input.data('length', length);
            for (var i=0; i<length; i++) {
                // To prevent conflicts, show filters only when
                // preserve-filters option is not checked
                if (data[i].type == 'filter' && getPreserveFiltersMode(input)) {
                    continue;
                }
                var content = data[i].label;
                if (options.highlight && data.type == 'suggestion') {
                    // escape term for regex
                    // https://github.com/sindresorhus/escape-string-regexp/blob/master/index.js
                    var escapedTerm = input.val().replace(/[|\\{}()[\]^$+*?.]/g, '\\$&');
                    var regex = new RegExp('('+escapedTerm+')', 'ig');
                    content = content.replace(regex, '<b>$1</b>');
                }
                var item = $('<div/>');

                item.attr('data-index', i+0)
                    .attr('data-value', data[i].val)
                    .attr('data-type', data[i].type)
                    .addClass('item')
                    .addClass(data[i].css)
                    .html(content)
                    .mouseover(function() {
                        $.fn.autocomplete.element.find('.item.selected').removeClass('selected');
                        $(this).addClass('selected');
                        input.data('selected', $(this).data('index'));
                    });

                if ("handler" in data[i]) {
                    item.attr('data-handler', data[i]['handler']);
                    item.attr('data-title', VuFind.translate(data[i]['handler']));
                }
                var type = data[i]['type'];
                if (type == 'phrase') {
                    item.attr('data-title', VuFind.translate('autocomplete_phrase'));
                    var query = $('.searchForm_lookfor').val().trim();
                    if (query.indexOf('"') != -1) {
                        item.hide();
                    }
                } else if (type == 'facet' || type == 'filter') {
                    item.attr('data-filters', data[i]['filters']);
                }

                if (typeof data[i].description !== 'undefined') {
                    item.append($('<small/>').text(data[i].description));
                }
                shell.append(item);
            }

            $.fn.autocomplete.element.html(shell);

            if (!shell.children().length) {
                hide();
                return;
            }

            $(['suggestion', 'facet', 'filter', 'phrase', 'handler']).each(function(ind, obj) {
                var label = 'autocomplete_section_' + obj;
                var translated = VuFind.translate(label);
                var wrapper = $('<div/>').addClass('group ' + obj);
                if (label != translated) {
                    wrapper.attr('data-title', translated);
                }
                var items = shell.find('.item.' + obj + ':visible');
                if (items.length) {
                    items.wrapAll(wrapper);
                }
            });

            shell.find('.item').first().addClass('first');
            shell.find('.item').last().addClass('last');

            $.fn.autocomplete.element.find('.item').mousedown(function() {
                populate($(this), input, {mouse: true});
            });
            align(input, $.fn.autocomplete.element);
        }

        function search(input, element) {
            if (xhr) { xhr.abort(); }
            if (input.val().length >= options.minLength) {
                element.html('<i class="item loading">'+options.loadingString+'</i>');
                show();
                align(input, $.fn.autocomplete.element);
                var term = [];
                term.push(input.val());
                term.push(getSearchHandler(input));
                term.push(getPreserveFiltersMode(input) ? "1" : "0");
                term.push(getPreserveFiltersMode(input) ? "1" : "0");
                term = term.join('###');
                var cid = input.data('cache-id');
                if (options.cache && typeof $.fn.autocomplete.cache[cid][term] !== "undefined") {
                    if ($.fn.autocomplete.cache[cid][term].length === 0) {
                        hide();
                    } else {
                        createList($.fn.autocomplete.cache[cid][term], input, element);
                    }
                } else if (typeof options.handler !== "undefined") {
                    options.handler(input.val(), function(data) {
                        if (data.length === 0 && options.suggestions) {
                            hide();
                        } else {
                            var searcher = extractClassParams(input);
                            var filters = handlers = phrase = null;
                            if (!("onlySuggestions" in searcher) || searcher['onlySuggestions'] != '1') {
                                filters = "filters" in searcher ? searcher['filters'] : null;
                            }
                            handlers = "handlers" in searcher ? searcher['handlers'] : null;
                            phrase = "phrase" in searcher ? searcher['phrase'] : null;

                            data = parseResponse(data, filters, handlers, phrase);
                            createList(data, input, element);
                        }
                        $.fn.autocomplete.cache[cid][term] = data;
                    });
                } else {
                    console.error('handler function not provided for autocomplete');
                }
                input.data('selected', -1);
            } else {
                hide();
            }
        }

        var parseResponse = function (data, filters, handlers, phraseSearch) {
            var datums = suggestions = [];
            if (data.length) {
                // Suggestions
                suggestions = $.map(data[0], function(obj, i) {
                    datums.push({label: obj, css: 'suggestion', type: 'suggestion'});
                return obj;
                });
            }

            // Filters
            if (filters) {
                $.each(filters.split('||'), function (i, filter) {
                    var f = filter.split('|');
                    var label = VuFind.translate(f[0]);
                    datums.push({
                        label: label, filters: f[1],
                        type: 'filter', css: 'filter filter-' + f[0]
                    });
                });
            }

            // Facets
            if (data.length > 1) {
                $.each(data[1], function (facet, data) {
                    $.map(data, function (item, i) {
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
                $.each(handlers.split('|'), function (i, handler) {
                    if (handler == 'AllFields') {
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

        function getPreserveFiltersMode(input) {
            return $(input).closest('form').find('.applied-filter[name=dfApplied]').is(':checked');
        };

        function getSearchHandler(input) {
            var form = $(input).closest('form');
            var handler = form.find('input[name=type]').not('.applied-filter');
            if (handler.length) {
                return handler.val();
            }
            return form.find('.applied-filter[name=type]').val();
        };
        
        function updateAutocompleteTop(input) {
            autocompleteTop = input.offset().top + input.outerHeight();            
        }

        function setup(input, element) {
            if (typeof element === 'undefined') {
                element = $('<div/>')
                    .addClass('autocomplete-results hidden')
                    .html('<i class="item loading">'+options.loadingString+'</i>');
                align(input, element);
                $(document.body).append(element);
            }

            updateAutocompleteTop(input);
            input.data('selected', -1);
            input.data('length', 0);

            if (options.cache) {
                var cid = Math.floor(Math.random()*1000);
                input.data('cache-id', cid);
                $.fn.autocomplete.cache[cid] = {};
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
                updateAutocompleteTop(input);
                search(input, element);
            });
            input.keyup(function(event) {
                // Ignore navigation keys
                // - Ignore control functions
                if (event.ctrlKey) {
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
                var element = $.fn.autocomplete.element;
                var position = $(this).data('selected');
                switch (event.which) {
                    // arrow keys through items
                case 38:
                    event.preventDefault();
                    element.find('.item.selected').removeClass('selected');
                    if (position > 0) {
                        position--;
                        element.find('.item:eq('+position+')').addClass('selected');
                        $(this).data('selected', position);
                    } else {
                        $(this).data('selected', -1);
                    }
                    break;
                case 40:
                    event.preventDefault();
                    if ($.fn.autocomplete.element.hasClass(options.hidingClass)) {
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
                typeof options.data    === "undefined" &&
                    typeof options.handler === "undefined" &&
                    typeof options.preload === "undefined" &&
                    typeof options.remote  === "undefined"
            ) {
                return input;
            }

            $(window).resize(function() {
                updateAutocompleteTop(input);
                hide();
            });

            return element;
        }

        return this.each(function() {

            var input = $(this);

            if (typeof settings === "string") {
                if (settings === "show") {
                    show();
                    align(input, $.fn.autocomplete.element);
                } else if (settings === "hide") {
                    hide();
                } else if (settings === "clear cache" && options.cache) {
                    var cid = parseInt(input.data('cache-id'));
                    $.fn.autocomplete.cache[cid] = {};
                }
                return input;
            } else {
                if (!$.fn.autocomplete.element) {
                    $.fn.autocomplete.element = setup(input);
                } else {
                    setup(input, $.fn.autocomplete.element);
                }
            }

            return input;

        });
    };

    var timer = false;
    if (typeof $.fn.autocomplete.cache === 'undefined') {
        $.fn.autocomplete.cache = {};
        $.fn.autocomplete.element = false;
        $.fn.autocomplete.options = {
            ajaxDelay: 200,
            cache: true,
            hidingClass: 'hidden',
            highlight: true,
            loadingString: 'Loading...',
            minLength: 3,
            suggestions: true
        };
        $.fn.autocomplete.ajax = function(ops) {
            if (timer) clearTimeout(timer);
            if (xhr) { xhr.abort(); }
            timer = setTimeout(
                function() { xhr = $.ajax(ops); },
                $.fn.autocomplete.options.ajaxDelay
            );
        };
    }

}( jQuery ));
