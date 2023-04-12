function confirmCancelRequest(btn, action) {
  $('#cancelConfirm').val(1);
  $('#submitType').attr('name', action);
  $(btn).parents('form').submit();
}

$(document).ready(function setupRequests() {
  // storage retrieval request
  $('#srr_cancel_selected .confirm__confirm').click(function cancelSelectedRequests(e) {
    e.preventDefault();
    confirmCancelRequest(this, 'cancelSelected');
  });
  $('#srr_cancel_all .confirm__confirm').click(function cancelSelectedRequests(e) {
    e.preventDefault();
    confirmCancelRequest(this, 'cancelAll');
  });

  // #todo: cover all requests
  // #todo: remove unnecessary cancels

  $('.confirm_cancel_no').click(function doNotCancelRequest(e) {
    e.preventDefault();
  });
  $('#update_selected').click(function updateSelected() {
    // Change submitType to indicate that this is not a cancel request:
    $('#submitType').attr('name', 'updateSelected');
  });

  // #todo: is this needed?

  var checkCheckboxes = function CheckCheckboxes() {
    var checked = $('form[name="updateForm"] .result .checkbox input[type=checkbox]:checked');
    if (checked.length > 0) {
      $('#update_selected').removeAttr('disabled');
      $('#cancelSelected').removeAttr('disabled');
    } else {
      $('#update_selected').attr('disabled', 'disabled');
      $('#cancelSelected').attr('disabled', 'disabled');
    }
  };
  $('form[name="updateForm"] .result .checkbox input[type=checkbox]').on('change', checkCheckboxes);
  $('#update_selected').removeClass('hidden');
  checkCheckboxes();
});
