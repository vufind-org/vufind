/* global VuFind, d3 */
/* exported showVisualFacets*/

function position() {
  this.style("left", function leftStyle(d) { return d.parentlevel ? d.x + 3 + "px" : d.x + "px"; })
    .style("top", function topStyle(d) { return d.parentlevel ? d.y + 3 + "px" : d.y + "px"; })
    .style("width", function widthStyle(d) { return d.parentlevel ? Math.max(0, d.dx - 4) + "px" : Math.max(0, d.dx - 1) + "px"; })
    .style("height", function heightStyle(d) { return d.parentlevel ? Math.max(0, d.dy - 4) + "px" : Math.max(0, d.dy - 1) + "px"; });
}

function settext() {
  this.text(function createText(d) {
    // Is this a top-level box?
    var onTop = (typeof d.parentfield === "undefined");

    // Case 1: top with no children -- no text!
    if (!d.children && onTop) {
      return "";
    }

    // Case 2: top-level field with contents:
    if (onTop) {
      return d.name + " (" + d.count + ")";
    }

    // Case 3: "More Topics" special-case collapsed block:
    if (d.name === VuFind.translate('More Topics')) {
      return VuFind.translate('more_topics_unescaped', { '%%count%%': d.count});
    }

    // Csae 4 (default): Standard second-level field
    return d.name + " (" + d.count + ")";
  });
}

function setscreenreader() {
  this.attr("class", "sr-only")
    .text(function createTextForScreenReader(d) {
      if (typeof d.parentfield !== "undefined") {
        return VuFind.translate('visual_facet_parent') + " " + d.parentlevel;
      } else {
        return "";
      }
    });
}

function settitle() {
  this.attr("title", function createTitle(d) {
    // Case 1: Top-level field
    if (typeof d.parentfield === "undefined") {
      return d.name + " (" + d.count + " " + VuFind.translate('items') + ")";
    }
    // Case 2: "More Topics" special-case collapsed block:
    if (d.name === VuFind.translate('More Topics')) {
      return d.count + " " + VuFind.translate('More Topics');
    }

    // Case 3: Standard second-level field
    if (typeof d.field !== "undefined") {
      var on_topic = VuFind.translate('on_topic_unescaped', {'%%count%%': d.count});
      return d.name + " (" + on_topic + ")";
    }
  });
}

function showVisualFacets(pivotdata) {
  if (!d3.select("#visualResults").empty()) {
    $('#visualResults').html('');
    $('.search-result-limit').css('display', 'none');
    $('.search-sort').css('display', 'none');
    $('.search-stats').css('visibility', 'hidden');
    $('.pagination').css('display', 'none');
    $('.pagination-simple').css('display', 'none');
    $('.bulkActionButtons').css('display', 'none');

    //  Color scheme developed using the awesome site
    //  http://colorschemedesigner.com
    //  Hue degrees (in order) -- 90, 105, 120, 135, 150
    //  Even numbered degrees are 100% brightness, 50% saturation
    //  Odd numbered degrees are 100% brightness, 25% saturation
    var color = d3.scale.ordinal()
      .range([
        "#A385FF", "#FF7975", "#C2FFE7", "#FFE775",
        "#75FF7E", "#FFD4C2", "#E0C7FF", "#D1FF75",
        "#D17DFF", "#FFB475", "#FFFF75", "#FF75C3",
        "#FFD175", "#C6E6FF", "#FFE5C2", "#FFC2FF",
        "#FFFF75", "#84A9FF", "#F5FFC2", "#FFFAC2",
        "#AAAAAA"])
      .domain(["A", "B", "C", "D", "E", "F", "G", "H", "J", "K", "L", "M", "N", "P", "Q", "R", "S", "T", "U", "V", "Z"]);

    var div = d3.select("#visualResults")
      .style("width", $("#visualResults").width() + "px")
      .style("height", "575px")
      .style("position", "absolute");

    var treemap = d3.layout.treemap()
      .size([$("#visualResults").width(), 575])
      .sticky(true)
      .mode("squarify")
      .padding(0, 0, 0, 18)
      .value(function size(d) { return d.size; });

    // Total count of items matching the search;
    // will be used below to do math to size the boxes properly.

    var totalbooks = pivotdata.total;

    $.each(pivotdata.children, function createFacet(facetindex, facetdata) {
      //Saving the original size in a "count" variable
      //that won't be resized.

      facetdata.count = facetdata.size;

      // If a first-level container contains less than 10%
      // of the total results, don't show any child containers
      // within that first-level container. You won't be able
      // to read them and they'll just clutter up the display.

      var onechild = {};
      if (facetdata.size < totalbooks * 0.1) {
        onechild.name = facetdata.name;
        onechild.size = facetdata.size;
        onechild.count = facetdata.count;
        onechild.field = facetdata.field;
        delete pivotdata.children[facetindex].children;
        pivotdata.children[facetindex].children = [];
        pivotdata.children[facetindex].children.push(onechild);
      } else {
        // Used to keep count of the total number of child
        // facets under a first-level facet. Used for
        // properly sizing multi-valued data.
        var totalbyfirstpivot = 0;
        $.each(facetdata.children, function sumChildFacet(childindex, childdata) {
          totalbyfirstpivot += childdata.size;
        });

        // Now we roll back through the "facetdata.children"
        // object (which contains all of the child facets in
        // a top-level facet) and combine the smallest X% of
        // squares into a "More topics" box.
        //
        // And then size the child boxes based on facetdata.size,
        // which, as long as our top-level field is not
        // multi-valued, is accurately sized for the number of
        // items in the first-level container.
        //
        // If a single child facet contains less than 5% of the
        // child facet results in a top-level container, roll it
        // into a "More topics" box. Unless the top-level container
        // is between 15% and 30% of the entire results; in that
        // case, only roll up topic facets that are less than 2% of
        // the box. If the top-level container is more than 30% but
        // less than 100% of the entire results, only roll up child
        // facets that are less than 1% of the facet results in that
        // container. If the top-level container is 100% of the
        // entire results, don't roll up any child facets.
        var morefacet = 0;
        var morecount = 0;
        var resizedData = [];
        $.each(facetdata.children, function createChildFacet(childindex, childdata) {
          if (childdata && (childdata.size < totalbyfirstpivot * 0.05 && facetdata.size < totalbooks * 0.15 || childdata.size < totalbyfirstpivot * 0.02 && facetdata.size < totalbooks * 0.3 || childdata.size < totalbyfirstpivot * 0.01 && facetdata.size !== totalbooks)) {
            morefacet += childdata.size;
            morecount++;
          } else if (childdata) {
            //If it's not going into the "more" facet, save the
            //count in a new variable, scale the size properly,
            //and add it to a new array
            var childobject = childdata;
            childobject.count = childdata.size;
            childobject.size = childdata.size / totalbyfirstpivot * facetdata.size;
            resizedData.push(childobject);
          }
        });

        delete pivotdata.children[facetindex].children;

        // Stop! Using this algorithm, sometimes all of the topics wind
        // up in a "More" facet, which leads to a confusing display. If
        // that happens, just display the top level, with no topic
        // boxes inside the main box.

        onechild = {};
        if (morefacet === totalbyfirstpivot) {
          onechild.name = facetdata.name;
          onechild.size = facetdata.size;
          onechild.count = facetdata.count;
          onechild.field = facetdata.field;
          pivotdata.children[facetindex].children = [];
          pivotdata.children[facetindex].children.push(onechild);
        } else {
          //If we're keeping the "More" facet, let's size it properly
          pivotdata.children[facetindex].children = resizedData;
          var more = {};
          more.name = VuFind.translate('More Topics');
          more.size = morefacet / totalbyfirstpivot * facetdata.size;
          more.field = ""; // this value doesn't matter, since parent field will be linked.
          more.count = morecount;
          more.parentfield = facetdata.field;
          more.parentlevel = facetdata.name;
          pivotdata.children[facetindex].children.push(more);
        }
      }
    });

    div.datum(pivotdata).selectAll(".node")
      .data(treemap.nodes)
      .enter().append("a")
      .attr("href", function createHref(d) {
        let url = new URL(window.location.href);
        let params = url.searchParams;
        if (d.parentlevel && d.name !== VuFind.translate('More Topics')) {
          params.append('filter[]', d.field + ':"' + d.name + '"');
          params.append('filter[]', d.parentfield + ':"' + d.parentlevel + '"');
          params.set('view', 'list');
        } else if (d.name === VuFind.translate('More Topics')) {
          params.append('filter[]', d.parentfield + ':"' + d.parentlevel + '"');
        } else if (d.name !== "theData") {
          params.append('filter[]', d.field + ':"' + d.name + '"');
        }
        return url.toString();
      })
      .append("div")
      .attr("class", function createClass(d) { return (typeof d.parentfield === "undefined") ? "node toplevel" : "node secondlevel"; })
      .attr("id", function createId(d) { return d.name.replace(/\s+/g, ''); })
      .call(position)
      .style("background", function styleBackground(d) { return d.children ? color(d.name.substr(0, 1)) : null; })
      .call(settitle)
      .style("z-index", function setZindex(d) { return (typeof d.parentfield !== "undefined") ? "1" : "0"; })
      .append("div")
      .call(settext)
      .attr("class", function createClass(d) { return d.children ? "label" : "notalabel"; } )
      .insert("div")
      .call(setscreenreader);
  }
}