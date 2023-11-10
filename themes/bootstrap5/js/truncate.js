/* global VuFind, getFocusableNodes */

VuFind.register('truncate', function Truncate() {
  function initTruncate(_container, _element, _fill) {
    const defaultSettings = {
      'btn-class': '',
      'in-place-toggle': false,
      'label': null,
      'less-icon': 'truncate-less',
      'less-label': VuFind.translate('less_ellipsis'),
      'more-icon': 'truncate-more',
      'more-label': VuFind.translate('more_ellipsis'),
      'rows': 3,
      'top-toggle': Infinity,
      'wrapper-class': '', // '' will glean from element, false or null will exclude a class
      'wrapper-tagname': null, // falsey values will glean from element
      'label-icons': 'before' // 'after' = icon after label, 'before' = icon before label, false = no icons
    };

    var zeroHeightContainers = [];

    $(_container).not('.truncate-done').each(function truncate() {
      var container = $(this);
      var settings = Object.assign({}, defaultSettings, container.data('truncate'));

      var element = typeof _element !== 'undefined'
        ? container.find(_element)
        : (typeof settings.element !== 'undefined')
          ? container.find(settings.element)
          : false;
      var fill = typeof _fill === 'undefined' ? function fill(m) { return m; } : _fill;
      var maxRows = parseFloat(settings.rows);
      var moreLabel, lessLabel;
      moreLabel = lessLabel = settings.label;
      if (moreLabel === null) {
        moreLabel = settings['more-label'];
        lessLabel = settings['less-label'];
      }
      var btnClass = settings['btn-class'] ? ' ' + settings['btn-class'] : '';
      var topToggle = settings['top-toggle'];
      var inPlaceToggle = (element && settings['in-place-toggle'])
        ? settings['in-place-toggle']
        : false;

      var parent, numRows, shouldTruncate, truncatedHeight;
      var wrapperClass = settings['wrapper-class'];
      var wrapperTagName = settings['wrapper-tagname'];
      var toggleElements = [];

      if (element) {
        // Element-based truncation
        parent = element.parent();
        numRows = container.find(element).length || 0;
        shouldTruncate = numRows > maxRows;

        if (wrapperClass === '') {
          wrapperClass = element.length ? element.prop('class') : '';
        }
        if (!wrapperTagName) {
          wrapperTagName = element.length && element.prop('tagName').toLowerCase();
        }

        if (shouldTruncate) {
          element.each(function hideRows(i) {
            if (i === maxRows) {
              $(this).addClass('truncate-start');
            }
            if (i >= maxRows) {
              $(this).hide();
              toggleElements.push(this);
            }
          });
        }
      } else {
        // Height-based truncation
        parent = container;
        var rowHeight;
        if (container.children().length > 0) {
          // Use first child as the height element if available
          var heightElem = container.children().first();
          var display = heightElem.css('display');
          if (display === 'block' || display === 'inline-block') {
            rowHeight = parseFloat(heightElem.outerHeight());
          } else {
            rowHeight = parseFloat(heightElem.css('line-height').replace('px', ''));
          }
        } else {
          rowHeight = parseFloat(container.css('line-height').replace('px', ''));
        }
        numRows = container.height() / rowHeight;
        // Truncate only if it saves at least 1.5 rows. This accounts for the room
        // the more button takes as well as any fractional imprecision.
        shouldTruncate = maxRows === 0 || maxRows !== 0 && numRows > maxRows + 1.5;

        if (shouldTruncate) {
          truncatedHeight = maxRows * rowHeight;
          container.css('height', truncatedHeight + 'px');
        }
      }

      if (shouldTruncate) {
        var btnMore = '<button type="button" class="btn more-btn' + btnClass + '">'
          + (settings['label-icons'] === 'before' ? VuFind.icon(settings['more-icon']) + ' ' : '')
          + '<span>' + moreLabel + '</span>'
          + (settings['label-icons'] === 'after' ? ' ' + VuFind.icon(settings['more-icon']) : '')
          + '</button>';
        var btnLess = '<button type="button" class="btn less-btn' + btnClass + '">'
          + (settings['label-icons'] === 'before' ? VuFind.icon(settings['less-icon']) + ' ' : '')
          + '<span>' + lessLabel + '</span>'
          + (settings['label-icons'] === 'after' ? ' ' + VuFind.icon(settings['less-icon']) : '')
          + '</button>';

        wrapperClass = wrapperClass ? ' ' + wrapperClass : '';
        wrapperTagName = wrapperTagName || 'div';
        var btnWrapper = $('<' + wrapperTagName + ' class="more-less-btn-wrapper' + wrapperClass + '"></' + wrapperTagName + '>');
        var btnWrapperBtm = btnWrapper.clone().append(fill(btnMore + btnLess));
        var btnWrapperTop = (numRows > topToggle) ? btnWrapper.clone().append(fill(btnLess)) : false;

        // Attach show/hide buttons to the top and bottom or display in place
        if (btnWrapperTop) {
          if (element) {
            btnWrapperTop.prependTo(parent);
          } else {
            btnWrapperTop.insertBefore(parent);
          }
        }
        if (inPlaceToggle) {
          btnWrapperBtm.insertBefore(parent.find('.truncate-start'));
        } else if (element) {
          btnWrapperBtm.appendTo(parent);
        } else {
          btnWrapperBtm.insertAfter(parent);
        }

        btnWrapperBtm.find('.less-btn').hide();
        if (btnWrapperTop) {
          btnWrapperTop.hide();
        }

        var onClickLessBtnHandler = function onClickLessBtn(/*event*/) {
          btnWrapperBtm.find('.less-btn').hide();
          if (btnWrapperTop) {
            btnWrapperTop.hide();
          }
          btnWrapperBtm.find('.more-btn').show();
          if (element) {
            toggleElements.forEach(function hideToggles(toggleElement) {
              $(toggleElement).toggle();
            });
          } else if (truncatedHeight === 0) {
            container.hide();
          } else {
            container.css('height', truncatedHeight + 'px');
          }
          btnWrapperBtm.find('.more-btn').focus();
        };
        btnWrapperBtm.find('.less-btn').click(onClickLessBtnHandler);
        if (btnWrapperTop) {
          btnWrapperTop.find('.less-btn').click(onClickLessBtnHandler);
        }

        btnWrapperBtm.find('.more-btn').click(function onClickMoreBtn(/*event*/) {
          $(this).hide();
          btnWrapperBtm.find('.less-btn').show();
          if (btnWrapperTop) {
            btnWrapperTop.show();
            btnWrapperTop.find('.less-btn').focus();
          } else {
            btnWrapperBtm.find('.less-btn').focus();
          }
          if (element) {
            toggleElements.forEach(function showToggles(toggleElement) {
              $(toggleElement).toggle();
            });
          } else if (truncatedHeight === 0) {
            container.show();
          } else {
            container.css('height', 'auto');
          }
        });
      }

      container.addClass('truncate-done');

      // Make hidden elements unfocusable
      // - Create IntersectionObserver
      const root = container.get(0);
      const observer = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.intersectionRatio > 0) {
              entry.target.removeAttribute("tabindex");
              // restore previous tabindex
              if (entry.target.dataset && entry.target.dataset.tabindex) {
                entry.target.setAttribute("tabindex", entry.target.dataset.tabindex);
                delete entry.target.dataset.tabindex;
              }
            } else {
              // save previous tabindex
              if (entry.target.getAttribute("tabindex")) {
                entry.target.dataset.tabindex = entry.target.getAttribute("tabindex");
              }

              entry.target.setAttribute("tabindex", -1);
            }
          });
        },
        { root }
      );
      // - add all focusable elements of facets to observer
      getFocusableNodes(root).forEach((el) => observer.observe(el));

      if (truncatedHeight === 0) {
        zeroHeightContainers.push(container);
      }
    });

    // Hide zero-height containers. They are not hidden immediately to allow for
    // height calculation of nested containers.
    zeroHeightContainers.forEach(function hideContainer(container) {
      container.hide();
      container.css('height', 'auto');
    });
  }

  return {
    initTruncate: initTruncate
  };
});
