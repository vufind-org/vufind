/*global VuFind, finna*/
finna.menu = (function finnaMenu() {

  var listHolder = null;
  var loading = false;

  function toggleList() {
    $('#favorites-collapse').toggleClass('in');
    $('#open-list').toggleClass('collapsed');
    $('.nav-tabs-personal').toggleClass('move-list');

    if (!$('.nav-tabs-personal').hasClass('move-list')) {
      window.scroll(0, 0);
    }
  }

  function loadLists() {
    $('#open-list .fa').toggleClass('hidden');

    $.ajax({
      type: 'POST',
      dataType: 'json',
      url: VuFind.path + '/AJAX/JSON?method=getMyLists',
      data: {'active': null}
    })
      .done(function onGetMyListsDone(data) {
        listHolder.html(data.data);
        $('#open-list').toggleClass('collapsed');
        $('#open-list .fa').toggleClass('hidden');
        $('.add-new-list-holder').hide();
        $('.nav-tabs-personal').toggleClass('move-list');

        $('#open-list > .caret').unbind('click').click(function toggleFavourites(event) {
          event.preventDefault();
          toggleList();
        });
      })
      .fail(function onGetMyListsDone() {
        $('#open-list .fa').toggleClass('hidden');
        $('.ajax-error').toggleClass('hidden');
        loading = false;
      });
  }


  function init() {
    listHolder = $('.mylist-bar');

    if (listHolder.children().length === 0) {
      $('#open-list').addClass('collapsed');

      $('#open-list > .caret').unbind('click').click(function onCaretClick(event) {
        event.preventDefault();

        if (!$('.ajax-error').hasClass('hidden')) {
          $('.ajax-error').addClass('hidden');
        }

        if (!loading) {
          loadLists();
          loading = true;
        }
      });
    } else {
      $('#open-list > .caret').unbind('click').click(function toggleFavourites(event) {
        event.preventDefault();
        toggleList();
      });
    }

    $('#open-loans > .caret').unbind('click').click(function getLists(event) {
      event.preventDefault();
      $('#myLoans').toggleClass('in');
      $('#open-loans').toggleClass('collapsed');
    });
  }

  var my = {
    init: init
  };

  return my;
})();
