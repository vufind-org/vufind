/*global VuFind, finna */
finna.dateRangeVis = (function finnaDateRangeVis() {
  var plotExtra = 2; // Widen plotted interval by +- years
  var loading = true;
  var plotted = false;
  var plotDelayId = false;
  var visData = false;
  var visDateStart = false;
  var visDateEnd = false;
  var visMove = false;
  var visRangeSelected = false;
  var holder = null;
  var searchParams = null;
  var facetField = null;
  var openTimelineCallback = null;

  function padZeros(_number, _length) {
    var number = _number;
    var length = typeof length === 'undefined' ? 4 : _length;
    // Room for any leading negative sign
    var negative = false;
    if (number < 0) {
      negative = true;
      number = Math.abs(number);
    }
    var str = '' + number;
    while (str.length < length) {
      str = '0' + str;
    }
    return (negative ? '-' : '') + str;
  }

  // Move dates: params either + or -
  function moveVis(start, end) {
    var ops = {
      '+': function movePlus(a) { return a + visMove; },
      '-': function moveMinus(a) { return a - visMove; }
    };
    visDateStart = ops[start](visDateStart);
    visDateEnd = ops[end](visDateEnd);
  }

  function getGraphOptions(start, end) {
    var options = {
      series: {
        bars: {
          show: true,
          color: '#00a3b5',
          fillColor: '#00a3b5'
        }
      },
      colors: ['#00a3b5'],
      legend: { noColumns: 2 },
      xaxis: {
        min: start,
        max: end,
        tickDecimals: 0,
        font: {
          size: 13,
          family: "'helvetica neue', helvetica,arial,sans-serif",
          color: '#464646',
          weight: 'regular'
        }
      },
      yaxis: { min: 0, ticks: [] },
      grid: {
        backgroundColor: null,
        borderWidth: 0,
        axisMargin: 0,
        margin: 0,
        clickable: true
      }
    };

    options.selection = {mode: 'x', color: '#00a3b5;', borderWidth: 0};
    return options;
  }

  function plotData(delay) {
    if (!visData) {
      return;
    }

    if (typeof delay !== 'undefined') {
      clearInterval(plotDelayId);
      plotDelayId = setTimeout(plotData, delay);
      return;
    }

    var start = visDateStart;
    var end = visDateEnd;

    var options = getGraphOptions(start - plotExtra, end + plotExtra + 1);
    var vis = holder.find('.date-vis');

    // Draw the plot
    var graph = $.plot(vis, [visData], options);
    var fromElement = holder.find('.year-from');
    var toElement = holder.find('.year-to');
    var plotInited = false;

    // Bind events
    vis.unbind('plotclick').bind('plotclick', function onPlotClick(event, pos/*, item*/) {
      if (!visRangeSelected) {
        var year = Math.floor(pos.x);
        graph.setSelection({ x1: year, x2: year + 1});
        fromElement.val(year);
        toElement.val(year);
        // Trigger change event to update limits
        fromElement.change();
        toElement.change();
      }
      visRangeSelected = false;
    });

    vis.unbind('plotselecting plotselected').bind('plotselecting plotselected', function onPlotSelect(event, ranges) {
      if (!plotInited || ranges === null) {
        return;
      }
      var from = Math.floor(ranges.xaxis.from);
      var to = Math.floor(ranges.xaxis.to);
      if (from !== -9999) {
        fromElement.val(from);
      }
      if (to !== -9999) {
        toElement.val(to);
      }
      // Trigger change event to update limits
      fromElement.change();
      toElement.change();

      visRangeSelected = true;
      if (event.type === 'plotselected') {
        $('body').click();
      }
    });

    // Set pre-selections
    var from = fromElement.val();
    var to = toElement.val();

    if (from || to) {
      from = from ? from : visData.min;
      to = to ? to : visData.max;
      graph.setSelection({ x1: from, x2: parseInt(to, 10) + 1});
    }
    plotInited = true;
    plotted = true;
  }

  function timelineAction(backend, action) {
    if (loading) {
      return;
    }

    // Navigation: prev, next, out or in
    if (typeof action != 'undefined') {
      // Require numerical values
      if (!isNaN(visDateStart) && !isNaN(visDateEnd)) {
        visMove = Math.ceil((visDateEnd - visDateStart) * 0.2);
        if (visMove < 1) {
          visMove = 1; // Require >= 1 year movements
        }
        // Changing the dates using the moveVis function above
        if (action === 'prev') {
          moveVis('-', '-');
        } else if (action === 'next') {
          moveVis('+', '+');
        } else if (action === 'zoom-out') {
          moveVis('-', '+');
        } else if (action === 'zoom-in') {
          // Only allow zooming in if years differ
          if (visDateStart !== visDateEnd) {
            moveVis('+', '-');
          }
        }
        // Make sure start <= end
        if (visDateStart > visDateEnd) {
          visDateStart = visDateEnd;
        }

        // Create the string of date params
        var newSearchParams = searchParams + '&filter[]=' + facetField
          + ':"[' + padZeros(visDateStart) + '+TO+' + padZeros(visDateEnd) + ']"';
        visData = null;
        finna.dateRangeVis.loadVis(backend, action, newSearchParams);
      }
    }
  }

  function showVis() {
    // Display timeline when facet animation is complete
    if (openTimelineCallback) {
      var fn = openTimelineCallback;
      openTimelineCallback = null;
      setTimeout(fn, 500);
    } else {
      plotData();
    }
  }

  function loadVis(backend, action, params) {
    // Load and display timeline (called at initial open and after timeline navigation)

    // Check if daterange filter is active and widen selected data range.
    var paramsProcessed = params
      .split("&")
      .map(
        function splitParams(param /* field=value */) {
          return param.split("=");
        })
      .map(function splitParam(param) {
        var field = decodeURIComponent(param[0]);
        if (field.substring(0, 1) === '?') {
          field = field.substring(1);
        }
        // Replace '+' since decodeURIComponent doesn't handle it
        var value = decodeURIComponent(param[1].replace(/\+/g, '%20'));
        if (field === 'filter[]') {
          var valueParts = value.split(':');
          if (valueParts[0] === facetField) {
            // Daterange filter active: widen selected date range by configured amount of years
            var regex = new RegExp('\\[(\\d+|\\*)\\sTO\\s(\\d+|\\*)\\]');
            value = value.replace(
              regex,
              function urlReplace(match, $1, $2/*, offset, original*/) {
                var from = $1;
                if (from !== '*') {
                  from = parseInt($1, 10) - plotExtra;
                }
                var to = $2;
                if (to !== '*') {
                  to = parseInt($2, 10) + plotExtra;
                }
                return '[' + from + " TO " + to + ']';
              }
            );
          }
        }
        return {'name': field, 'value': (value)};
      });

    var url = VuFind.path + '/AJAX/JSON?' + $.param(paramsProcessed) + '&method=getDateRangeVisual&backend=' + backend;

    holder.find('.content').addClass('loading');
    loading = true;

    $.getJSON(url)
      .done(function onContentGetDone(data) {
        loading = false;
        var vis = holder.find('.date-vis');
        vis.closest('.content').removeClass('loading');
        $.each(data.data, function limitsEach(key, val) {
          // Get data limits
          var dataMin = parseInt(val.min, 10);
          var dataMax = parseInt(val.max, 10);

          val.min = dataMin;
          val.max = dataMax;

          // Left & right limits have to be processed separately
          // depending on movement direction: when reaching the left limit while
          // zooming in or moving back, we use the max value for both
          if ((action === 'prev' || action === 'zoom-in') && val.min > val.max) {
            val.max = val.min;
            // Otherwise, we need the min value
          } else if (action === 'next' && val.min > val.max) {
            val.min = val.max;
          }

          if (visDateStart === false) {
            visDateStart = dataMin;
          }

          if (visDateEnd === false) {
            visDateEnd = dataMax;
          }

          visDateEnd = Math.min(visDateEnd, new Date().getFullYear());

          // Check for values outside the selected range and remove them
          for (var i = 0; i < val.data.length; i++) {
            if (val.data[i][0] < visDateStart - 5 || val.data[i][0] > visDateEnd + 5) {
              // Remove this
              val.data.splice(i, 1);
              i--;
            }
          }
          visData = val;
          plotData();
        });
      })
      .fail(function onContentGetFail(/*response, textStatus*/) {
        holder.find('.date-vis').closest('.content').removeClass('loading');
      });
  }

  function updateFieldLimits(evt) {
    var params = evt.data;
    params.from.attr('max', params.to.val());
    params.to.attr('min', params.from.val());

    var within = params.form.find('input[type=radio][name=type]:checked').val() === 'within';
    if (within && (params.from.val() !== '' || params.to.val() !== '')) {
      params.from.attr('required', 'required');
      params.to.attr('required', 'required');
    } else {
      params.from.removeAttr('required');
      params.to.removeAttr('required');
    }
  }

  function initForm(form, backend, _facetField) {
    facetField = _facetField;
    form.find('a.submit').on('click',
      function onFormSubmitClick() {
        $(this).closest('form').submit();
        return false;
      }
    );

    var fromElement = form.find('.year-from');
    var toElement = form.find('.year-to');

    var params = {
      form: form,
      from: fromElement,
      to: toElement
    };
    var typeElements = form.find('input[type=radio][name=type]');
    fromElement.change(params, updateFieldLimits);
    toElement.change(params, updateFieldLimits);
    typeElements.change(params, updateFieldLimits);
    updateFieldLimits({data: params});

    form.submit(function formSubmit(e) {
      e.preventDefault();

      if (typeof form[0].checkValidity == 'function') {
        // This is for Safari, which doesn't validate forms on submit
        if (!form[0].checkValidity()) {
          return;
        }
      }

      var isSolr = backend === 'solr';
      var type = null;
      if (isSolr) {
        var typeElement = form.find('input[type=radio][name=type]:checked');
        if (typeElement.length) {
          type = typeElement.val();
        }
      }

      // Get dates, build query
      var from = fromElement.val();
      var to = toElement.val();
      var action = $(this).attr('action');
      if (action.indexOf('?') < 0) {
        action += '?'; // No other parameters, therefore add ?
      } else {
        action += '&'; // Other parameters found, therefore add &
      }

      var query = action;

      fromElement.removeClass('invalid');
      toElement.removeClass('invalid');
      query += 'filter[]=' + facetField + ':';

      // Require numerical values
      if (!isNaN(from) && !isNaN(to)) {
        if (from === '' && to === '') { // both dates empty; use removal url
          query = action;
        } else if (from === '') { // only end date set
          if (type === 'within') {
            fromElement.addClass('invalid');
            return false;
          }
          to = parseInt(to, 10);
          query += '"[';
          query += isSolr ? '*' : (to >= 1900 ? 1900 : to - 100);
          query += '+TO+' + padZeros(to) + ']"';
        } else if (to === '') { // only start date set
          if (type === 'within') {
            toElement.addClass('invalid');
            return false;
          }
          from = parseInt(from, 10);
          query += '"[' + padZeros(from) + ' TO ';
          query += isSolr ? '*' : (from <= 2100 ? 2100 : from + 100);
          query += ']"';
        } else if (parseInt(from, 10) > parseInt(to, 10)) {
          fromElement.addClass('invalid');
          toElement.addClass('invalid');
          return false;
        } else { // both dates set
          query += '"[' + padZeros(from) + ' TO ' + padZeros(to) + ']"';
          if (type === 'within') {
            query += '&search_daterange_mv_type=' + type;
          }
        }

        // Perform the new search
        window.location = query;
      }
      return;
    });
  }

  function initTimelineNavigation(backend, _holder) {
    _holder.find('.navigation div:not(.expand-modal)').on(
      'click',
      { callback: timelineAction },
      function onHolderDivNonExpandClick(e) {
        e.data.callback(backend, $(this).attr('class').split(' ')[0]);
      }
    );
    _holder.find('.navigation div.expand-modal').on(
      'click',
      function onHolderDivExpandClick(/*e*/) {
        $(this).closest('.list-group-item.daterange').toggleClass('expand');
        $('i', this).toggleClass('fa-condense');
        plotData();
      }
    );
  }

  function initVis(backend, facet, params, baseParams, h, start, end, plotImmediately) {
    facetField = facet;
    holder = h;

    // Save default timeline parameters
    searchParams = baseParams;

    var field = holder.find('.year-from');
    if (field.length) {
      var startVal = field.val();
      if (startVal !== '') {
        visDateStart = parseInt(startVal, 10);
      }
    }

    if (visDateStart === false && typeof start != undefined && start !== false) {
      visDateStart = start;
    }

    field = holder.find('.year-to');
    if (field.length) {
      var endVal = field.val();
      if (endVal !== '') {
        visDateEnd = parseInt(endVal, 10);
      }
    }

    initTimelineNavigation(backend, h);
    h.closest('.daterange-facet').find('.year-form').each(function eachYearFormInit() {
      initForm($(this), backend, facet);
    });

    openTimelineCallback = function openTimelineCallbackFunc() {
      loadVis(backend, 'prev', params);
    };
    if ((typeof plotImmediately !== 'undefined' && plotImmediately)
      || !$('.daterange-facet .list-group-item').hasClass('collapsed')
    ) {
      openTimelineCallback();
    }
  }

  function initFacetBar() {
    $('.daterange-facet.facet-group').on('shown.bs.collapse', function onShownCollapse(/*e*/) {
      if (!plotted) {
        showVis();
      }
    });
  }

  function initResizeListener() {
    $(window).on('resize.screen.finna', function onResizeScreen(/*e, data*/) {
      plotData();
    });
  }

  function init() {
    initResizeListener();
    initFacetBar();
  }

  var my = {
    init: init,
    loadVis: loadVis,
    initVis: initVis
  };

  return my;

})();
