/*global VuFind*/
finna.itemStatus = (function() {

  var checkItemStatus = function (id) {
    var item = $('.hiddenId[value="' + id + '"]').closest('.record-container');
    item.find(".ajax-availability").removeClass('hidden');
    item.find(".availability-load-indicator").removeClass('hidden');

    // Hide all holdings fields by default:
    item.find('.locationDetails').addClass('hidden');
    item.find('.no-holdings').addClass('hidden');
    item.find('.callnumber').addClass('hidden');
    item.find('.location').addClass('hidden');
    item.find('.hideIfDetailed').addClass('hidden');
    item.find('.status').addClass('hidden');

    if (typeof item.data('xhr') !== 'undefined') {
      item.data('xhr').abort();
    }
    // Callback for AJAX loaded holdings item on search results page.
    var statusCallback =
       function (holder) {
           initTitleHolds(holder);
           holder.find('a.login').unbind('click').click(function() {
               var followUp = $(this).attr('href');
               Lightbox.addCloseAction(function() {
                   window.location = followUp;
               });
               $('#modal .modal-title').html(VuFind.translate('login'));
               Lightbox.titleSet = true;
               return Lightbox.get('MyResearch', 'UserLogin');
           });
       };

    var xhr = $.ajax({
      dataType: 'json',
      url: VuFind.getPath() + '/AJAX/JSON?method=getItemStatuses',
      data: {id:[id]},
      success: function(response) {
        if(response.status == 'OK') {
          $.each(response.data, function(i, result) {
            item.find('.status').empty().append(result.availability_message);
            item.find('.dedup-select').removeAttr('selected').
              find('option[value="' + result.record_number + '"]').attr('selected', '1');

            if (typeof(result.full_status) != 'undefined'
              && result.full_status.length > 0
              && item.find('.callnumAndLocation').length > 0
            ) {
              // Full status mode is on -- display the HTML:
              var details = item.find('.locationDetails');
              details.empty().append(result.full_status);
              details.removeClass('hidden');
              finna.layout.initTruncate(details);

              details.find('.holdings-container.collapsible > .header').click(function () {
                 $(this).next('.holdings').toggleClass('collapsed');
                  $(this).find('.fa.arrow:first')
                      .removeClass('fa-arrow-right fa-arrow-down')
                      .addClass('fa-arrow-' + ($(this).next('.holdings').hasClass('collapsed') ? 'right' : 'down'));
              });
              statusCallback(item);
            } else if (typeof(result.missing_data) != 'undefined'
              && result.missing_data
            ) {
              // No data is available:
              item.find('.no-holdings').removeClass('hidden');
            } else if (result.locationList) {
              // We have multiple locations -- build appropriate HTML:
              var locationListHTML = "";
              for (var x=0; x<result.locationList.length; x++) {
                locationListHTML += '<div class="groupLocation">';
                if (result.locationList[x].availability) {
                  locationListHTML += '<i class="fa fa-ok text-success"></i> <span class="text-success">'
                    + result.locationList[x].location + '</span> ';
                } else if (typeof(result.locationList[x].status_unknown) !== 'undefined'
                  && result.locationList[x].status_unknown
                ) {
                  if (result.locationList[x].location) {
                    locationListHTML += '<i class="fa fa-ok text-warning"></i> <span class="text-warning">' 
                      + result.locationList[x].location + '</span> ';
                  }
                } else {
                  locationListHTML += '<i class="fa fa-remove text-error"></i> <span class="text-error"">'
                    + result.locationList[x].location + '</span> ';
                }
                if (result.locationList[x].callnumbers) {
                  locationListHTML += '<span class="groupCallnumber">';
                  locationListHTML += '(' + VuFind.translate('shelf_location') + ': ' + result.locationList[x].callnumbers + ')';
                  locationListHTML += '</span>';
                }
                locationListHTML += '</div>';
              }
              var details = item.find('.locationDetails');
              details.empty().append(locationListHTML);
              details.wrapInner('<div class="truncate-field" data-rows="5"></div>');
              details.removeClass('hidden');
              finna.layout.initTruncate(details);
            } else {
              // Default case -- load call number and location into appropriate containers:
              item.find('.callnumber').empty().append(result.callnumber+'<br/>');
              item.find('.location').empty().append(
                result.reserve == 'true'
                ? result.reserve_message
                : result.location
              );
              item.find('.callnumber').removeClass('hidden');
              item.find('.location').removeClass('hidden');
            }
          });
        } else {
          // display the error message on each of the ajax status place holder
          item.find('.locationDetails').empty().append(response.data);
          item.find('.locationDetails').removeClass('hidden');
        }
        item.find(".availability-load-indicator").addClass('hidden');
      }
    });
    item.data('xhr', xhr);
  };

    var initDedupRecordSelection = function (holder) {
        if (typeof holder === 'undefined') {
            holder = $(document);
        }

        holder.find('.dedup-select').change(function() {
            var id = $(this).val();
            var source = $(this).find('option:selected').data('source');
            $.cookie('preferredRecordSource', source);

            var recordContainer = $(this).closest('.record-container');
            var oldRecordId = recordContainer.find('.hiddenId')[0].value;

            // Update IDs of elements
            recordContainer.find('.hiddenId').val(id);

            // Update IDs of elements
            recordContainer.find('[id="' + oldRecordId + '"]').each(function() {
                $(this).attr('id', id);
            });

            // Update links as well
            recordContainer.find('a').each(function() {
                if (typeof $(this).attr('href') !== 'undefined') {
                    $(this).attr('href', $(this).attr('href').replace(oldRecordId, id));
                }
            });

            recordContainer.find('.locationDetails').addClass('hidden');
            recordContainer.find('.callnumber').removeClass('hidden');
            recordContainer.find('.location').removeClass('hidden');
            checkItemStatus(id);
        });
    };

    var initItemStatuses = function(holder) {
        if (typeof holder === 'undefined') {
            holder = $(document);
        }
        holder.find('.ajaxItem').each(function(ind, e) {
            $(this).one('inview', function() {
                var id = $(this).find('.hiddenId')[0].value;
                checkItemStatus(id);
            });
        });
    };

  var initTitleHolds = function (holder) {
      if (typeof holder == "undefined") {
          holder = $(document);
      }
      holder.find('.placehold').unbind('click').on('click', function() {
          var parts = $(this).attr('href').split('?');
          parts = parts[0].split('/');
          var params = deparam($(this).attr('href'));
          params.id = parts[parts.length-2];
          params.hashKey = params.hashKey.split('#')[0]; // Remove #tabnav
          return Lightbox.get('Record', parts[parts.length-1], params, false, function(html) {
              Lightbox.checkForError(html, Lightbox.changeContent);
          });
      });
  };

  var my = {
    initItemStatuses: initItemStatuses,
    initDedupRecordSelection: initDedupRecordSelection,
    init: function() {
        if (!$(".results").hasClass("result-view-condensed")) {
            initItemStatuses();
            initDedupRecordSelection();
        }
    }
  };

  return my;
})(finna);
