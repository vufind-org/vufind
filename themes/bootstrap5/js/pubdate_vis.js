/*global VuFind, Chart, bootstrap */
VuFind.register('pubdateVis', function pubdateVis() {

  let _zooming = false;
  const _graphMargin = 5;
  const _cssColorSettings = {
    // background of box
    'background-color': '#fff',
    // box fill color
    'fill': '#eee',
    // box outline color
    'stroke': '#265680',
    // selection color
    'outline-color': '#c38835'
  };


  /**
   * Init range slider
   * @param {string} facetName Facet name
   * @param {object} data Facet data
   */
  function initRangeSlider(facetName, data) {
    // check if there is data to display, if there isn't hide the box
    if (data.data === undefined || data.data.length === 0) {
      return;
    }

    // sort data by year
    data.data.sort((a, b) => a[0] - b[0]);

    // set up the hasFilter variable
    const hasFilter = data.selectionMin !== undefined && data.selectionMax !== undefined;

    const form = document.getElementById('datevis' + facetName + 'xForm');
    form.classList.remove('hidden');

    const chartCanvas = document.getElementById('datevis' + facetName + 'x-canvas');
    const sliderElement = document.getElementById('datevis' + facetName + '-slider');
    const controlsTrigger = document.getElementById('datevis' + facetName + '-controls-trigger');
    const controls = document.getElementById('datevis' + facetName + '-controls');
    const minSelectionInput = document.getElementById('datevis' + facetName + '-from');
    const maxSelectionInput = document.getElementById('datevis' + facetName + '-to');

    // check if the min and max value have been set otherwise set them to the border of the data
    if (data.selectionMin === undefined) {
      data.selectionMin = data.data[0][0];
    }
    const initSelectionMin = parseInt(data.selectionMin, 10);
    const totalSelectionMin = initSelectionMin - _graphMargin;
    minSelectionInput.value = initSelectionMin;

    if (data.selectionMax === undefined) {
      data.selectionMax = data.data[data.data.length - 1][0];
    }
    const initSelectionMax = parseInt(data.selectionMax, 10);
    const totalSelectionMax = initSelectionMax + _graphMargin;
    maxSelectionInput.value = initSelectionMax;

    if (_zooming && hasFilter) {
      // filter values out of range
      data.data = data.data.filter((element) => element[0] >= totalSelectionMin && element[0] <= totalSelectionMax);
    }

    // get an array with all years in range and set count to 0 if missing in data
    const years = Array.from(
      {length: totalSelectionMax - totalSelectionMin + 1},
      (_, i) => totalSelectionMin + i
    );
    const dataset = years.map(
      (year) => {
        let existingData = data.data.find(value => value[0] === year);
        return existingData ? existingData[1] : 0;
      }
    );

    // setup slider
    const slider = VuFind.dateRangeSlider.create(sliderElement, {
      start: [initSelectionMin, initSelectionMax],
      range: {
        'min': [totalSelectionMin],
        'max': [totalSelectionMax]
      },
    }, minSelectionInput, maxSelectionInput);

    // only show selection in chart and controls if some range was selected
    let showChartSelection = false;
    const controlsCollapse = new bootstrap.Collapse(controls, {toggle: false, show: false});

    /**
     * Show controls
     */
    function showControls() {
      controlsTrigger.tabIndex = -1;
      showChartSelection = true;
      controlsCollapse.show();
    }

    /**
     * Hide controls
     */
    function hideControls() {
      controlsTrigger.tabIndex = 0;
      showChartSelection = false;
      controlsCollapse.hide();
    }

    // show controls if hidden trigger element was focused because the slider is aria-hidden
    controlsTrigger.addEventListener('focus', () => {
      showControls();
      minSelectionInput.focus();
    });


    /**
     * Show controls if mobile view
     */
    function showControlsIfMobile() {
      // always show controls on mobile
      if (window.screen.width < 768) {
        showControls();
      }
    }
    showControlsIfMobile();
    addEventListener("resize", showControlsIfMobile);

    // show controls if the filter is already set
    if (hasFilter) {
      showControls();
    }

    // custom plugin that draws an overlay for the selected data in the chart
    const drawSelectionPlugin = {
      id: 'drawSelection',
      afterDatasetsDraw: (chart) => {
        const metaData = chart.getDatasetMeta(0).data;
        const values = slider.get();
        const minIndex = years.findIndex(value => value === values[0]);
        const maxIndex = years.findIndex(value => value === values[1]);
        if (showChartSelection && minIndex >= 0 && maxIndex >= 0) {
          const startElement = metaData[minIndex];
          const endElement = metaData[maxIndex];
          const startX = startElement.x - startElement.width / 2;
          const endX = endElement.x + endElement.width / 2;
          const width = endX - startX;
          const ctx = chart.ctx;
          const chartArea = chart.chartArea;
          ctx.save();
          ctx.fillStyle = _cssColorSettings['outline-color'];
          ctx.fillRect(startX, chartArea.top, width, chartArea.height);
          ctx.restore();
        }
      }
    };

    // draw background color on chart if it is not white
    const backgroundColor = _cssColorSettings['background-color'];
    if (backgroundColor !== '#fff' && backgroundColor !== 'rgb(255, 255, 255)') {
      drawSelectionPlugin.beforeDraw = (chart) => {
        const ctx = chart.ctx;
        ctx.save();
        ctx.globalCompositeOperation = 'destination-over';
        ctx.fillStyle = backgroundColor;
        ctx.fillRect(0, 0, chart.width, chart.height);
        ctx.restore();
      };
    }

    // init chart
    const chart = new Chart(chartCanvas, {
      type: 'bar',
      data: {
        labels: years,
        datasets: [{
          data: dataset,
          backgroundColor: _cssColorSettings.fill,
          borderColor: _cssColorSettings.stroke,
          borderWidth: 2,
          borderSkipped: false,
          categoryPercentage: 1.0,
          barPercentage: 1.0,
        }]
      },
      options: {
        layout: {
          padding: 0
        },
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
        },
        scales: {
          x: {
            position: 'top',
            ticks: {
              padding: 0,
              autoSkip: true,
              maxTicksLimit: 10,
            },
            grid: {
              display: false
            }
          },
          y: {
            display: false,
          },
        }
      },
      plugins: [drawSelectionPlugin]
    });

    // adjust slider padding so that the slider positions match the chart
    const paddingLeft = chart.scales.x.getPixelForValue(0);
    const paddingRight = chart.width - chart.scales.x.getPixelForValue(years.length - 1);
    sliderElement.parentElement.style.paddingLeft = paddingLeft + 'px';
    sliderElement.parentElement.style.paddingRight = paddingRight + 'px';

    // show controls and update chart if selection changed
    let chartUpdatePending = false;
    sliderElement.addEventListener('updated-slider', () => {
      showControls();
      if (!chartUpdatePending) {
        requestAnimationFrame(() => {
          chart.update('none');
          chartUpdatePending = false;
        });
        chartUpdatePending = true;
      }
    });

    // init clear selection button
    form.querySelectorAll('.clear-btn').forEach((clearButton) => clearButton
      .addEventListener('click', () => {
        if (hasFilter) {
          window.location.href = data.removalURL;
        } else {
          minSelectionInput.value = initSelectionMin;
          maxSelectionInput.value = initSelectionMax;
          minSelectionInput.dispatchEvent(new Event('input'));
          hideControls();
        }
      })
    );
  }

  /**
   * Init pubDateVisAjax recommendation module
   */
  function init() {
    // Get colors from CSS
    const dateVisColorSettings = getComputedStyle(document.getElementById('dateVisColorSettings'));
    for (let rule in _cssColorSettings) {
      if (dateVisColorSettings[rule]) {
        const match = dateVisColorSettings[rule].match(/rgb[a]?\([^)]+\)|#[a-fA-F0-9]+/);
        if (null != match) {
          _cssColorSettings[rule] = match[0];
        }
      }
    }

    // Add transparency to outline-color if not present already
    if (dateVisColorSettings['outline-color']) {
      const matchRgb = dateVisColorSettings['outline-color'].match(/rgb\(([^)]+)\)/);
      const matchShortHex = dateVisColorSettings['outline-color'].match(/#[a-fA-F0-9]{1,6}/);
      if (null != matchRgb) {
        _cssColorSettings['outline-color'] = 'rgba(' + matchRgb[1] + ',0.5)';
      } else if (null != matchShortHex) {
        _cssColorSettings['outline-color'] = matchShortHex[0] + '80';
      }
    }

    _zooming = VuFind.config.get('pub-vis:zooming');

    const facetFields = encodeURIComponent(VuFind.config.get('pub-vis:facet-fields'));
    const searchParams = VuFind.config.get('pub-vis:search-params');

    fetch(VuFind.path + '/AJAX/json?method=getVisData&facetFields=' + facetFields + '&' + searchParams).then(
      response => response.json()
    ).then(
      data => Object
        .entries(data.data.facets)
        .forEach(([key, val]) => initRangeSlider(key, val))
    );
  }

  return { init };
});
