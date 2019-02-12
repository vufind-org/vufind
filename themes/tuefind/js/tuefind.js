// helper function to set focus on a specified input field, also sets cursor position to end of field content
function tuefindSetFocus(input_selector) {
    $(input_selector).focus();
    if ($(input_selector).length) {
       // now we are sure that element exists
       // don't assign input_field earlier, JS might crash if element doesnt exist
       var input_field = $(input_selector);
       input_field[0].setSelectionRange(input_field.val().length, input_field.val().length);
    }
}

// function to register onload handler function
// multiple handlers can be chained, if necessary
function tuefindRegisterOnLoad(functionOnLoad) {
    if(window.attachEvent) {
        window.attachEvent('onload', functionOnLoad);
    } else {
        if(window.onload) {
            var currentOnLoad = window.onload;
            var newOnLoad = function(evt) {
                currentOnLoad(evt);
                functionOnLoad(evt);
            };
            window.onload = newOnLoad;
        } else {
            window.onload = functionOnLoad;
        }
    }
}

// onload handler for tuefind
function tuefindOnLoad() {
    // advanced search: set focus on first input field of first search group
    if (window.location.href.match(/\/Search\/Advanced/i)) {
        tuefindSetFocus('#search_lookfor0_0');
    // keywordchainsearch: set focus on 2nd input field
    } else if (window.location.href.match(/\/Keywordchainsearch\//i)) {
        tuefindSetFocus('#kwc_input');
    // alphabrowse: set focus on "starting from" edit field
    } else if (window.location.href.match(/\/Alphabrowse\//i)) {
        tuefindSetFocus('#alphaBrowseForm_from');
    }
}

tuefindRegisterOnLoad(tuefindOnLoad);


$(document).ready(function() {
  // Make sure that a selection of the search handler is transparently adjusted for a potential reload
  // i.e. when changing the sort order
  var saved_search_handler = sessionStorage.getItem("tuefind_saved_search_handler");
  if (saved_search_handler != null) {
    $("#searchForm_type option:selected").removeAttr('selected');
    $("#searchForm_type").val(saved_search_handler);
    // We also have to set the handler explicitly adjusting the selection
    $("#searchForm_type [value=" + saved_search_handler + "]").attr('selected', 'selected');
    // Make sure the type is adjusted for the next form submission
    $("[name=type]").val(saved_search_handler);
  }
  $("#searchForm_type").on('focus', function () {
     previous_search_handler = this.value;
  }).change(function adjustSearchHandler(e) {
     current_search_handler = $("#searchForm_type").val();
     sessionStorage.setItem("tuefind_saved_search_handler", current_search_handler);
     $("#searchForm_type [value=" + previous_search_handler + "]").removeAttr('selected');
     $("#searchForm_type [value=" + current_search_handler + "]").attr('selected', 'selected');
     $("[name=type]").val(current_search_handler);
  });
});


function tuefindGetFulltextSnippets(url, doc_id, query) {
  // Try to determine status
  $.ajax({
    type: "GET",
    url: url + "fulltextsnippetproxy/load?search_query="+query + "&doc_id=" + doc_id,
    dataType: "json",
    success: function(json) {
      $(document).ready(function() {
         var snippets = json['snippets'];
         $("#snippet_place_holder").each(function() {
            if (snippets) {
              $(this).replaceWith(snippets.join('</br>') + '</br>');
            }
            else
              $("#fulltext_snippets").remove();
         });
      });
    }, // end success
    error: function (xhr, ajaxOptions, thrownError) {
        $("#snippet_place_holder").each(function() {
          $(this).replaceWith('Invalid server response!!!!!');
        })
    }
  });
}
