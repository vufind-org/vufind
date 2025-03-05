/*global VuFind, Chart, bootstrap */
VuFind.register('pubdateVis', function pubdateVis() {

  let _zooming = false;
  let _initiallyHideControls = false;
  const _stepSizes = [1, 2, 5, 10, 25, 50, 100, 200, 500, 1000];
  const _graphMargin = 5;
  const _cssColorSettings = {
    // background of box
    'background-color': '#fff',
    // box fill color
    'fill': '#eee',
    // box outline color
    'stroke': '#265680',
    // selection color
    'outline-color': '#0873c655'
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
    const dataMin = data.data[0][0];
    if (data.selectionMin === undefined) {
      data.selectionMin = dataMin;
    }
    const initSelectionMin = parseInt(data.selectionMin, 10);
    const totalSelectionMin = (_zooming ? initSelectionMin : dataMin) - _graphMargin;
    minSelectionInput.value = initSelectionMin;

    const dataMax = data.data[data.data.length - 1][0];
    if (data.selectionMax === undefined) {
      data.selectionMax = dataMax;
    }
    const initSelectionMax = parseInt(data.selectionMax, 10);
    const totalSelectionMax = (_zooming ? initSelectionMax : dataMax) + _graphMargin;
    maxSelectionInput.value = initSelectionMax;

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

    // only show selection in chart and controls if some range was selected or initiallyHideControls is false
    let showChartSelection = !_initiallyHideControls;
    const controlsCollapse = new bootstrap.Collapse(controls, {toggle: !_initiallyHideControls});


    /**
     * Show controls
     */
    function showControls() {
      if (_initiallyHideControls) {
        controlsTrigger.tabIndex = -1;
        showChartSelection = true;
        controlsCollapse.show();
      }
    }

    /**
     * Hide controls
     */
    function hideControls() {
      if (_initiallyHideControls) {
        controlsTrigger.tabIndex = 0;
        showChartSelection = false;
        controlsCollapse.hide();
      }
    }

    /**
     * Show controls if mobile view
     */
    function showControlsIfMobile() {
      // always show controls on mobile
      if (window.innerWidth < 768) {
        showControls();
      }
    }

    if (_initiallyHideControls) {
      controlsTrigger.tabIndex = 0;
      // show controls if hidden trigger element was focused because the slider is aria-hidden
      controlsTrigger.addEventListener('focus', () => {
        showControls();
        minSelectionInput.focus();
      });
      showControlsIfMobile();
      addEventListener("resize", showControlsIfMobile);

      // show controls if the filter is already set
      if (hasFilter) {
        showControls();
      }
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

    let stepSize = 1;
    let range = years.length;
    for (let i = 0; i < _stepSizes.length; i++) {
      stepSize = _stepSizes[i];
      if (range / stepSize < 15) break;
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
              callback: function(value, index) {
                let year = years[index];
                return year % stepSize === 0 ? year : null;
              },
              stepSize: 1,
              autoSkip: false,
            },
            grid: {
              display: true,
              drawTicks: false,
              offset: false,
            }
          },
          y: {
            display: false,
          },
        },
        onClick: (e) => {
          const canvasPosition = Chart.helpers.getRelativePosition(e, chart);
          const dataX = chart.scales.x.getValueForPixel(canvasPosition.x);
          const year = years[dataX];
          minSelectionInput.value = year;
          maxSelectionInput.value = year;
          minSelectionInput.dispatchEvent(new Event('input'));
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
    _initiallyHideControls = VuFind.config.get('pub-vis:initially-hide-controls');

    const facetFields = encodeURIComponent(VuFind.config.get('pub-vis:facet-fields'));
    const searchParams = VuFind.config.get('pub-vis:search-params');

    fetch(VuFind.path + '/AJAX/json?method=getVisData&facetFields=' + facetFields + '&' + searchParams).then(
      response => response.json()
    ).then(
      data => {
        if (data.data.facets === undefined) {
          throw data.data;
        }
        return Object.entries(data.data.facets).forEach(([key, val]) => initRangeSlider(key, val));
      }
    ).catch((error) => {
      console.error("Could not create pubDateVis recommendation: " + error);
    });
  }

  return { init };
});
