function confirmRenewRequest(link, action) {
  $('#submitType').attr('name', action);
  $(link).parents('form').trigger("submit");
}

function confirmPurgeRequest(link, action) {
  $('#submitType').attr('name', action);
  $(link).parents('form').trigger("submit");
}

$(function setupRequests() {
  $('#confirm_renew_selected_yes').on("click", function renewSelectedRequests(e) {
    e.preventDefault();
    confirmRenewRequest(this, 'renewSelected');
  });
  $('#confirm_renew_all_yes').on("click", function renewAllRequests(e) {
    e.preventDefault();
    confirmRenewRequest(this, 'renewAll');
  });
  $('.confirm_renew_no').on("click", function doNotRenewRequest(e) {
    e.preventDefault();
  });

  // Purge loan history:
  $('#confirm_purge_selected_yes').on("click", function renewSelectedRequests(e) {
    e.preventDefault();
    confirmPurgeRequest(this, 'purgeSelected');
  });
  $('#confirm_purge_all_yes').on("click", function renewAllRequests(e) {
    e.preventDefault();
    confirmPurgeRequest(this, 'purgeAll');
  });
  $('.confirm_purge_no').on("click", function doNotRenewRequest(e) {
    e.preventDefault();
  });
});
