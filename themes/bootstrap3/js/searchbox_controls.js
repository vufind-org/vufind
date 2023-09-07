/*global Autocomplete, VuFind */

function extractClassParams(el) {
  const str = el.className;

  if (typeof str === "undefined") {
    return [];
  }

  let params = {};
  const classes = str.split(/\s+/);
  for (let i = 0; i < classes.length; i++) {
    if (classes[i].indexOf(':') > 0) {
      const pair = classes[i].split(':');
      params[pair[0]] = pair[1];
    }
  }

  return params;
}

function setupAutocomplete() {
  // If .autocomplete class is missing, autocomplete is disabled and we should bail out.
  const searchbox = document.querySelector('#searchForm_lookfor.autocomplete');

  if (searchbox === null) {
    return;
  }

  const typeahead = new Autocomplete({
    rtl: document.body.classList.contains("rtl"),
    maxResults: 10,
    loadingString: VuFind.translate("loading_ellipsis"),
  });

  let cache = {};
  typeahead(searchbox, function vufindACHandler(query, callback) {
    const classParams = extractClassParams(searchbox);
    const searcher = classParams.searcher;
    const type = classParams.type
      ? classParams.type
      : document.getElementById("searchForm_type").value;

    const cacheKey = searcher + "|" + type;
    if (typeof cache[cacheKey] === "undefined") {
      cache[cacheKey] = {};
    }

    if (typeof cache[cacheKey][query] !== "undefined") {
      callback(cache[cacheKey][query]);
      return;
    }

    let hiddenFilters = [];
    document.getElementById("searchForm")
      .querySelectorAll('input[name="hiddenFilters[]"]')
      .forEach(function hiddenFiltersEach(input) {
        hiddenFilters.push(input.value);
      });

    $.ajax({
      url: VuFind.path + "/AJAX/JSON",
      data: {
        q: query,
        method: "getACSuggestions",
        searcher: searcher,
        type: type,
        hiddenFilters,
      },
      dataType: "json",
      success: function autocompleteJSON(json) {
        const highlighted = json.data.suggestions.map(
          (item) => ({
            text: item.replaceAll("&", "&amp;")
              .replaceAll("<", "&lt;")
              .replaceAll(">", "&gt;")
              .replaceAll(query, `<b>${query}</b>`),
            value: item,
          })
        );
        cache[cacheKey][query] = highlighted;
        callback(highlighted);
      }
    });
  });

  // Bind autocomplete auto submit
  if (searchbox.classList.contains("ac-auto-submit")) {
    searchbox.addEventListener("ac-select", (event) => {
      const value = typeof event.detail === "string"
        ? event.detail
        : event.detail.value;
      searchbox.setAttribute("value", value);
      document.getElementById("searchForm").submit();
    });
  }
}

function setupSearchResetButton() {
  const queryInput = document.getElementById("searchForm_lookfor");
  const resetButton = document.getElementById("searchForm-reset");

  if (queryInput.value !== "") {
    resetButton.classList.remove("hidden");
  }

  queryInput.addEventListener("input", function resetOnInput() {
    if (queryInput.value === "") {
      resetButton.classList.add("hidden");
    } else {
      resetButton.classList.remove("hidden");
    }
  });

  resetButton.addEventListener("click", function resetOnClick() {
    resetButton.classList.add("hidden");
    queryInput.setAttribute("value", "");
    queryInput.focus();
    queryInput.ac.hide();
  });
}

VuFind.register("searchbox_controls", function SearchboxControls() {
  function init() {
    setupAutocomplete();
    setupSearchResetButton();
  }

  return {
    init: init
  };
});
