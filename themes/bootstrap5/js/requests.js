function confirmCancelRequest(link, action) {
  $('#cancelConfirm').val(1);
  $('#submitType').attr('name', action);
  $(link).parents('form').trigger("submit");
}

$(function setupRequests() {
  $('#confirm_cancel_selected_yes').on("click", function cancelSelectedRequests(e) {
    e.preventDefault();
    confirmCancelRequest(this, 'cancelSelected');
  });
  $('#confirm_cancel_all_yes').on("click", function cancelAllRequests(e) {
    e.preventDefault();
    confirmCancelRequest(this, 'cancelAll');
  });
  $('.confirm_cancel_no').on("click", function doNotCancelRequest(e) {
    e.preventDefault();
  });
  $('#update_selected').on("click", function updateSelected() {
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
  $('form[name="updateForm"] .checkbox input[type=checkbox]').on('change', checkCheckboxes);
  $('#update_selected').removeClass('hidden');
  checkCheckboxes();
});
