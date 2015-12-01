/**
 * vufind.typeahead.js 0.2
 * ~ @crhallberg
 */
(function ( $ ) {

  $.fn.autocomplete = function(settings) {

    var options = $.extend( {}, $.fn.autocomplete.options, settings );

    function show() {
      $.fn.autocomplete.element.removeClass(options.hidingClass);
    }
    function hide() {
      $.fn.autocomplete.element.addClass(options.hidingClass);
    }

    function populate(value, input, eventType) {
      input.val(value);
      hide();
      if (typeof options.onselection !== 'undefined') {
        options.onselection(value, input, eventType);
      }
    }

    function createList(data, input) {
      var shell = $('<div/>');
      for (var i=0, len=Math.min(options.maxResults, data.length); i<len; i++) {
        if (typeof data[i] === 'string') {
          data[i] = {val: data[i]};
        }
        var item = typeof data[i].href === 'undefined'
          ? $('<div/>').attr('data-value', data[i].val)
                      .html(data[i].val)
                      .addClass('item')
          : $('<a/>').attr('href', data[i].href)
                    .attr('data-value', data[i].val)
                    .html(data[i].val)
                    .addClass('item')
        if (typeof data[i].description !== 'undefined') {
          item.append($('<small/>').text(data[i].description));
        }
        shell.append(item);
      }
      $.fn.autocomplete.element.html(shell.html());
      $.fn.autocomplete.element.find('.item').mousedown(function() {
        populate($(this).attr('data-value'), input, {mouse: true})
      });
      align(input, $.fn.autocomplete.element);
    }

    $.fn.autocomplete.cache = {};
    function search(input, element) {
      if (xhr) xhr.abort();
      if (input.val().length >= options.minLength) {
        element.html('<i class="item loading">'+options.loadingString+'</i>');
        show();
        align(input, $.fn.autocomplete.element);
        var term = input.val();
        var cid = parseInt(input.data('cache-id'));
        if (options.cache && typeof $.fn.autocomplete.cache[cid][term] !== "undefined") {
          if ($.fn.autocomplete.cache[cid][term].length === 0) {
            hide();
          } else {
            createList($.fn.autocomplete.cache[cid][term], input, element);
          }
        } else if (typeof options.handler !== "undefined") {
          options.handler(input.val(), function(data) {
            $.fn.autocomplete.cache[cid][term] = data;
            if (data.length === 0) {
              hide();
            } else {
              createList(data, input, element);
            }
          });
        } else {
          console.error('handler function not provided for autocomplete');
        }
        input.data('selected', -1);
      } else {
        hide();
      }
    }

    function align(input, element) {
      var offset = input[0].getBoundingClientRect();
      element.css({
        position: 'absolute',
        top: offset.top + offset.height,
        left: offset.left,
        maxWidth: offset.width * 2,
        minWidth: offset.width,
        zIndex: 50
      });
    }

    function setup(input, element) {
      if (typeof element === 'undefined') {
        element = $('<div/>')
          .addClass('autocomplete-results hidden')
          .text('<i class="item loading">'+options.loadingString+'</i>');
        align(input, element);
        $('body').append(element);
      }

      input.data('selected', -1);

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
        search(input, element);
      });
      input.keyup(function(event) {
        if (event.ctrlKey) {
          return;
        }
        switch (event.which) {
          case 37:
          case 38:
          case 39:
          case 9:
          case 13: {
            return;
          }
          case 40: {
            if ($(this).data('selected') === -1) {
              search(input, element)
              return;
            }
          }
          default: {
            if (
              event.which === 8  ||   // backspace
              event.which === 46 ||   // delete
              (event.which >= 48 &&   // letters
               event.which <= 90) ||
              (event.which >= 96 &&   // numpad
               event.which <= 111)
            ) {
              search(input, element);
            }
          }
        }
      });
      input.keydown(function(event) {
        var element = $.fn.autocomplete.element;
        var position = $(this).data('selected');
        switch (event.which) {
          // arrow keys through items
          case 38: {
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
          }
          case 40: {
            show();
            event.preventDefault();
            if (position < options.maxResults) {
              position++;
              element.find('.item.selected').removeClass('selected');
              element.find('.item:eq('+position+')').addClass('selected');
              $(this).data('selected', position);
            }
            break;
          }
          // enter to nav or populate
          case 9:
          case 13: {
            var selected = element.find('.item.selected');
            if (selected.length > 0) {
              event.preventDefault();
              if (event.which === 13 && selected.attr('href')) {
                location.assign(selected.attr('href'));
              } else {
                populate(selected.attr('data-value'), $(this), element, {key: true});
                element.find('.item.selected').removeClass('selected');
              }
            }
            break;
          }
          // hide on escape
          case 27: {
            hide(element);
            $(this).data('selected', -1);
            break;
          }
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

  $.fn.autocomplete.element = false;
  $.fn.autocomplete.options = {
    ajaxDelay: 200,
    cache: true,
    hidingClass: 'hidden',
    highlight: true,
    loadingString: 'Loading...',
    maxResults: 20,
    minLength: 3,
    minResults: 1
  };

  var xhr = false;
  var timer = false;
  $.fn.autocomplete.ajax = function(ops) {
    if (timer) clearTimeout(timer);
    if (xhr) xhr.abort();
    timer = setTimeout(
      function() { xhr = $.ajax(ops); },
      $.fn.autocomplete.options.ajaxDelay
    );
  }

}( jQuery ));
