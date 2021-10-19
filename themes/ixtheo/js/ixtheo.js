var IxTheo = {
  ConvertToListEntries: function(bundle_id, items) {
    var bundle_div = $("#" + bundle_id.replace(":", "_")).find("div");
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
    bundle_div.html(entry_list);
  },

  GetPDAInformation: function(isbn, ajaxUrl, pdaSubscribeUrl, pdaSubscribeText) {
    // Suppress entire field if no isbn is present
    if (isbn == "0") {
      $("#pda_row").remove();
    }
    // Try to determine status
    $.ajax({
      type: "GET",
      url: ajaxUrl + isbn,
      dataType: "json",
      success: function(json) {
	$(document).ready(function() {
	  var received_isbn = json['isbn'];
	  var pda_status = json['pda_status'];
	  $("#pda_place_holder").each(function() {
	    if ((received_isbn == isbn) && (pda_status == "OFFER_PDA")) {
	      $(this).replaceWith('<a href="' + pdaSubscribeUrl + '">' + pdaSubscribeText + '</a>');
	    } else
	      $("#pda_row").remove();
	  });
	});
      }, // end success
      error: function(xhr, ajaxOptions, thrownError) {
	$("#pda_place_holder").each(function() {
	  $(this).replaceWith('Invalid server response!!!!!');
	})
      }
    }); // end ajax
  },

  GetSubscriptionBundleItems: function(bundle_id) {
    $.ajax({
      url: VuFind.path + '/AJAX/JSON',
      data: {
	method: 'getSubscriptionBundleEntries',
	bundle_id: bundle_id
      },
      dataType: 'json',
      success: function displaySubscriptionItems(items) {
	$(document).ready(function() {
	  if (items.length > 0)
	    IxTheo.ConvertToListEntries(bundle_id, items);
	});
      },
      error: function(xhr, ajaxOptions, thrownError) {
	if (window.console && window.console.log) {
	  console.log("Status: " + xhr.status + ", Error: " + thrownError);
	}
      }
    });
  },

  ShowMoreButtonFavoriteList: function() {
    let maxElements = 3;
    let countListItems = 0;
    let showMoreButton = false;
    let mainBlock = $('.savedLists:first');
    setTimeout(function() {
      mainBlock.find('li').each(function() {
	countListItems++;
	if (countListItems > maxElements) {
	  $(this).hide();
	  showMoreButton = true;
	}
      })
      if (showMoreButton === true) {
	$('<span class="ix-favoritesListMoreButton">' + VuFind.translate('more') + '</span>').insertAfter(mainBlock.find('ul'));
      }
      mainBlock.removeClass('tf-d-none');
      $('.ix-favoritesListMoreButton').click(function() {
	$('.ix-favoritesListModal').click();
      })
    }, 500);
  }
};


$(document).ready(function() {
  var previous_handler;
  $("#searchForm_type").on('focus', function() {
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

  IxTheo.ShowMoreButtonFavoriteList();

});
