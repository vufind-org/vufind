/*exported loadVis */

function PadDigits(number, totalDigits) {
  var n = number <= 0 ? 1 : number;
  n = n.toString();
  var pd = '';
  if (totalDigits > n.length)
  {
    for (var i = 0; i < (totalDigits - n.length); i++)
    {
      pd += '0';
    }
  }
  return pd + n;
}

function loadVis(facetFields, searchParams, baseURL, zooming) {
  // Get colors from CSS
  var cssColorSettings = {
    // background of box
    'background-color': '#fff',
    // box fill color
    'fill': '#eee',
    // box outline color
    'stroke': '#265680',
    // selection color
    'outline-color': '#c38835'
  };
  var $dateVisColorSettings = $('#dateVisColorSettings');
  for (var rule in cssColorSettings) {
    if ($dateVisColorSettings.css(rule)) {
      var match = $dateVisColorSettings.css(rule).match(/rgb[a]?\([^)]+\)|#[a-fA-F0-9]+/);
      if (null != match) {
        cssColorSettings[rule] = match[0];
      }
    }
  }
  // options for the graph, TODO: make configurable
  var options = {
    series: {
      bars: {
        show: true,
        align: "center",
        fill: true,
        fillColor: cssColorSettings.fill
      }
    },
    colors: [cssColorSettings.stroke],
    legend: { noColumns: 2 },
    xaxis: { tickDecimals: 0 },
    yaxis: { min: 0, ticks: [] },
    selection: {mode: "x", color: cssColorSettings['outline-color'], minSize: 0},
    grid: { backgroundColor: cssColorSettings['background-color'] }
  };

  // AJAX call
  var graphMargin = 5;

  var url = baseURL + '/AJAX/json?method=getVisData&facetFields=' + encodeURIComponent(facetFields) + '&' + searchParams;
  $.getJSON(url, function getVisDataJSON(data) {
    $.each(data.data.facets, function getVisDataEach(key, val) {
      //check if there is data to display, if there isn't hide the box
      if (val.data === undefined || val.data.length === 0) {
        return;
      }
      $("#datevis" + key + "xWrapper").removeClass('hidden');

      // plot graph
      var placeholder = $("#datevis" + key + "x");

      //set up the hasFilter variable
      var hasFilter = true;

      //set the has filter
      if (val.min === 0 && val.max === 0) {
        hasFilter = false;
      }

      //check if the min and max value have been set otherwise set them to the ends of the graph
      if (val.min === 0) {
        val.min = val.data[0][0] - graphMargin;
      }
      if (val.max === 0) {
        val.max = parseInt(val.data[val.data.length - 1][0], 10) + graphMargin;
      }

      if (zooming && hasFilter) {
        //check the first and last elements of the data array against min and max value (+padding)
        //if the element exists leave it, otherwise create a new marker with a minus one value
        if (val.data[val.data.length - 1][0] !== parseInt(val.max, 10) + graphMargin) {
          val.data.push([parseInt(val.max, 10) + graphMargin, -1]);
        }
        if (val.data[0][0] !== val.min - graphMargin) {
          val.data.push([val.min - graphMargin, -1]);
        }
        //check for values outside the selected range and remove them by setting them to null
        for (var i = 0; i < val.data.length; i++) {
          if (val.data[i][0] < val.min - graphMargin || val.data[i][0] > parseInt(val.max, 10) + graphMargin) {
            //remove this
            val.data.splice(i, 1);
            i--;
          }
        }

      } else {
        //no zooming means that we need to specifically set the margins
        //do the last one first to avoid getting the new last element
        val.data.push([parseInt(val.data[val.data.length - 1][0], 10) + graphMargin, -1]);
        //now get the first element
        val.data.push([val.data[0][0] - graphMargin, -1]);
      }


      var plot = $.plot(placeholder, [val], options);
      if (hasFilter) {
        // mark pre-selected area
        plot.setSelection({ x1: val.min, x2: val.max});
      }
      // selection handler
      placeholder.bind("plotselected", function plotselected(event, ranges) {
        var from = Math.floor(ranges.xaxis.from);
        var to = Math.ceil(ranges.xaxis.to);
        window.location.href = val.removalURL + '&daterange[]=' + key + '&' + key + 'to=' + PadDigits(to, 4) + '&' + key + 'from=' + PadDigits(from, 4);
      });

      if (hasFilter) {
        var newdiv = document.createElement('span');
        newdiv.setAttribute('id', 'clearButton' + key);
        newdiv.className = "dateVisClear";

        var link = document.createElement("a");
        link.textContent = document.getElementById("clearButtonText").innerText;
        link.setAttribute("href", val.removalURL);
        newdiv.append(link);

        placeholder.before(newdiv);
      }
    });
  });
}