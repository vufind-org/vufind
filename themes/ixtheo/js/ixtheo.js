function convertToListEntries(bundle_id, items) {
  var bundle_div = $("#" + bundle_id).find("div");
  bundle_div.empty();
  var entry_list = '<ul class="list-group">';
  $.each(items, function(index, item) {
    entry_list += ('<li class="list-group-item">' +
                     '<a href=' + VuFind.path + /Record/ + item.id + '>' + item.title + '</a>' +
                     '<a class="subscribe-record save-record modal-link" data-lightbox  id="' + item.id + '"' +
                       'href="' + VuFind.path + '/Record/' + item.id + '/Subscribe" rel="nofollow" title="Subscribe">' +
                     '<i class="fa fa-fw fa-bell"></i>' +
                   '</li>');
  });
  entry_list += '</ul>';
  bundle_div.append(entry_list);
}

function getSubscriptionBundleItems(bundle_id) {
  var query = "bundle_id:" + bundle_id;
  $.ajax({
    url: VuFind.path + '/AJAX/JSON',
    data: {
      method: 'getSubscriptionBundleEntries',
      bundle_id: bundle_id
    },
    dataType: 'json',
    success: function displaySubscriptionItems(json) {
      $(document).ready(function() {
        var data = $.parseJSON(json.data)
        if (data.items.length > 0)
          convertToListEntries(bundle_id, data.items);
      });
    },
    error: function(xhr, ajaxOptions, thrownError) {
      if (window.console && window.console.log) {
              console.log("Status: " + xhr.status + ", Error: " + thrownError);
      }
    }
  });
}

$(document).ready(function() {
  var previous_handler;
  $("#searchForm_type").on('focus', function () {
      previous_handler = this.value;
  }).change(function adjustSearchSort(e) {
    if (previous_handler == 'BibleRangeSearch') {
        var default_sort = $("#sort_options_1").data('default_sort');
        $("#sort_options_1").off(); // Prevent automatic reloading
        $("#sort_options_1").removeAttr('disabled'); //Handle leftover of forcing relevance search for bibrange
        $("#sort_options_1").val(default_sort).change();
        $(":input[name='sort']").val(default_sort);
        $("#sort_options_1").on();
        return false;
    }
  });
});
