function confirmRenewRequest(link, action) {
  $('#submitType').attr('name', action);
  $(link).parents('form').submit();
}

function confirmPurgeRequest(link, action) {
  $('#submitType').attr('name', action);
  $(link).parents('form').submit();
}

$(document).ready(function setupRequests() {
  $('#confirm_renew_selected_yes').click(function renewSelectedRequests(e) {
    e.preventDefault();
    confirmRenewRequest(this, 'renewSelected');
  });
  $('#confirm_renew_all_yes').click(function renewAllRequests(e) {
    e.preventDefault();
    confirmRenewRequest(this, 'renewAll');
  });
  $('.confirm_renew_no').click(function doNotRenewRequest(e) {
    e.preventDefault();
  });

  // Purge loan history:
  $('#confirm_purge_selected_yes').click(function renewSelectedRequests(e) {
    e.preventDefault();
    confirmPurgeRequest(this, 'purgeSelected');
  });
  $('#confirm_purge_all_yes').click(function renewAllRequests(e) {
    e.preventDefault();
    confirmPurgeRequest(this, 'purgeAll');
  });
  $('.confirm_purge_no').click(function doNotRenewRequest(e) {
    e.preventDefault();
  });
});
