/**
 origin: {finc}
 called by view helper/controller:
 usage: {}
 modified for finc:
 * finc-specific file to load staff view content in modal instead of tab via button in toolbar #21993
 configured in: {}
 */

/*global VuFind */
$(document).ready(function() {
  $('.staff-view-btn').on('click', function(event){
    event.preventDefault();

    let link = $(this);
    if (link.data('status') !== 'loading') {
      link.data('status', 'loading');
      link.append('<span id="staffview_spinner"><i class="fa fa-spinner fa-spin" aria-hidden="true"></i><span>');
      $.ajax({
        url: VuFind.path + '/Record/'+link.data('id')+'/AjaxTab',
        dataType:'html',
        method: "POST",
        data: {tab: "details"},
        success: function(data, textStatus, jqXHR) {
          if (data && data.length > 0) {
            VuFind.lightbox.render(
              '<h2>' + link.text() + '</h2>' + data
            );
          } else {
            VuFind.lightbox.alert(VuFind.translate('error_occurred'), 'danger');
          }
          link.data('status', '');
          $('#staffview_spinner').remove();
        },
        error: function(XMLHttpRequest, textStatus, errorThrown) {
          VuFind.lightbox.alert("Status: " + textStatus + "<br >Error: " + errorThrown, 'danger');
          link.data('status', '');
          $('#staffview_spinner').remove();
        }
      });
    }
  });
});
