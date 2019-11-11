$(document).ready(function() {
  function confirmCancelRequest(link, action) {
    $('#cancelConfirm').val(1);
    $('#submitType').attr('name', action);
    $(link).parents('form').submit();
  }
  $('#confirm_cancel_selected_yes').click(function(e) {
    e.preventDefault();
    confirmCancelRequest(this, 'cancelSelected');
  });
  $('#confirm_cancel_all_yes').click(function(e) {
    e.preventDefault();
    confirmCancelRequest(this, 'cancelAll');
  });
  $('.confirm_cancel_no').click(function(e) {
    e.preventDefault();
  });
});