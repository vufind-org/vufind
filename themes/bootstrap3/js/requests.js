function confirmCancelRequest(link, action) {
  $('#cancelConfirm').val(1);
  $('#submitType').attr('name', action);
  $(link).parents('form').submit();
}

$(document).ready(function setupRequests() {
  $('#confirm_cancel_selected_yes').click(function cancelSelectedRequests(e) {
    e.preventDefault();
    confirmCancelRequest(this, 'cancelSelected');
  });
  $('#confirm_cancel_all_yes').click(function cancelAllRequests(e) {
    e.preventDefault();
    confirmCancelRequest(this, 'cancelAll');
  });
  $('.confirm_cancel_no').click(function doNotCancelRequest(e) {
    e.preventDefault();
  });
  $('#update_selected').click(function updateSelected() {
    // Change submitType to indicate that this is not a cancel request:
    $('#submitType').attr('name', 'updateSelected');
  });

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
