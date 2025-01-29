/*exported processGBSBookInfo, processOLBookInfo, processHTBookInfo */

// functions to get rights codes for previews
function getHathiOptions() {
  return $('[class*="hathiPreviewSpan"]').attr("class").split('__')[1].split(',');
}
function getGoogleOptions() {
  var opts_temp = $('[class*="googlePreviewSpan"]').attr("class").split('__')[1].split(';');
  var options = {};
  for (var key in opts_temp) {
    if (Object.prototype.hasOwnProperty.call(opts_temp, key)) {
      var arr = opts_temp[key].split(':');
      options[arr[0]] = arr[1].split(',');
    }
  }
  return options;
}
function getOLOptions() {
  return $('[class*="olPreviewSpan"]').attr("class").split('__')[1].split(',');
}

function getHTPreviews(keys) {
  var skeys = keys.replace(/(ISBN|LCCN|OCLC)/gi, '$1:').toLowerCase();
  var bibkeys = skeys.split(/\s+/);
  // fetch 20 books at time if there are more than 20
  // since hathitrust only allows 20 at a time
  // as per https://vufind.org/jira/browse/VUFIND-317
  var batch = [];
  for (var i = 0; i < bibkeys.length; i++) {
    batch.push(bibkeys[i]);
    if ((i > 0 && i % 20 === 0) || i === bibkeys.length - 1) {
      var script = 'https://catalog.hathitrust.org/api/volumes/brief/json/'
                + batch.join('|') + '&callback=processHTBookInfo';
      $.getScript(script);
      batch = [];
    }
  }
}

function applyPreviewUrl($link, url) {
  // Update the preview button:
  $link.attr('href', url).removeClass('hidden')
    .attr('rel', 'noopener'); // Performance improvement

  // Update associated record thumbnail, if any:
  $link.parents('.result,.record')
    .find('.recordcover[data-linkpreview="true"]').parents('a').attr('href', url)
    .off('click') // disable custom click handling
    .attr('data-lightbox-image', null) // disable opening in lightbox
    .attr('target', '_blank') // open consistently with preview button
    .attr('rel', 'noopener');
}

function processBookInfo(booksInfo, previewClass, viewOptions) {
  for (var bibkey in booksInfo) {
    if (booksInfo[bibkey]) {
      if (viewOptions.indexOf(booksInfo[bibkey].preview) >= 0) {
        applyPreviewUrl(
          $('.' + previewClass + '.' + bibkey), booksInfo[bibkey].preview_url
        );
      }
    }
  }
}

function processGBSBookInfo(booksInfo) {
  var viewOptions = getGoogleOptions();
  if (viewOptions.link && viewOptions.link.length > 0) {
    processBookInfo(booksInfo, 'previewGBS', viewOptions.link);
  }
  if (viewOptions.tab && viewOptions.tab.length > 0) {
    // check for "embeddable: true" in bookinfo
    for (var bibkey in booksInfo) {
      if (booksInfo[bibkey]) {
        if (viewOptions.tab.indexOf(booksInfo[bibkey].preview) >= 0
        && (booksInfo[bibkey].embeddable)) {
          // make tab visible
          $('ul.nav-tabs li.hidden[data-tab="preview"]').removeClass('hidden');
        }
      }
    }
  }
}

function processOLBookInfo(booksInfo) {
  processBookInfo(booksInfo, 'previewOL', getOLOptions());
}

function processHTBookInfo(booksInfo) {
  for (var b in booksInfo) {
    if (Object.prototype.hasOwnProperty.call(booksInfo, b)) {
      var bibkey = b.replace(/:/, '').toUpperCase();
      var $link = $('.previewHT.' + bibkey);
      var items = booksInfo[b].items;
      for (var i = 0; i < items.length; i++) {
        // check if items possess an eligible rights code
        if (getHathiOptions().indexOf(items[i].rightsCode) >= 0) {
          applyPreviewUrl($link, items[i].itemURL);
        }
      }
    }
  }
}

/**
 * Array.indexOf is not universally supported
 * We need to set it for users who don't have it.
 *
 * developer.mozilla.org/en-US/docs/JavaScript/Reference/Global_Objects/Array/indexOf
 */
function setIndexOf() {
  Array.prototype.indexOf = function indexOfPolyfill(searchElement /*, fromIndex */ ) {
    "use strict";
    if (this == null) {
      throw new TypeError();
    }
    var t = Object(this);
    var len = t.length;
    if (len === 0) {
      return -1;
    }
    var n = 0;
    if (arguments.length > 1) {
      n = Number(arguments[1]);
      if (n !== n) { // shortcut for verifying if it's NaN
        n = 0;
      } else if (n !== 0 && n !== Infinity && n !== -Infinity) {
        n = (n > 0 || -1) * Math.floor(Math.abs(n));
      }
    }
    if (n >= len) {
      return -1;
    }
    var k = n >= 0 ? n : Math.max(len - Math.abs(n), 0);
    for (; k < len; k++) {
      if (k in t && t[k] === searchElement) {
        return k;
      }
    }
    return -1;
  };
}

function getBibKeyString() {
  var skeys = '';
  $('.previewBibkeys').each(function previewBibkeysEach(){
    skeys += $(this).attr('class');
  });
  return skeys.replace(/previewBibkeys/g, '').replace(/^\s+|\s+$/g, '');
}

function getBookPreviews() {
  var skeys = getBibKeyString();
  var bibkeys = skeys.split(/\s+/);
  var script;

  // fetch Google preview if enabled
  if ($('[class*="googlePreviewSpan"]').length > 0) {
    // checks if query string might break URI limit - if not, run as normal
    if (bibkeys.length <= 150) {
      script = 'https://encrypted.google.com/books?jscmd=viewapi&bibkeys='
        + bibkeys.join(',') + '&callback=processGBSBookInfo';
      $.getScript(script);
    } else {
      // if so, break request into chunks of 100
      var keyString = '';
      // loop through array
      for (var i = 0; i < bibkeys.length; i++){
        keyString += bibkeys[i] + ',';
        // send request when there are 100 requests ready or when there are no
        // more elements to be sent
        if ((i > 0 && i % 100 === 0) || i === bibkeys.length - 1) {
          script = 'https://encrypted.google.com/books?jscmd=viewapi&bibkeys='
            + keyString + '&callback=processGBSBookInfo';
          $.getScript(script);
          keyString = '';
        }
      }
    }
  }

  // fetch OpenLibrary preview if enabled
  if ($('[class*="olPreviewSpan"]').length > 0) {
    script = '//openlibrary.org/api/books?bibkeys='
      + bibkeys.join(',') + '&callback=processOLBookInfo';
    $.getScript(script);
  }

  // fetch HathiTrust preview if enabled
  if ($('[class*="hathiPreviewSpan"]').length > 0) {
    getHTPreviews(skeys);
  }
}

$(function previewDocReady() {
  if (!Array.prototype.indexOf) {
    setIndexOf();
  }
  getBookPreviews();
});
