/*global userIsLoggedIn, VuFind */
VuFind.register('account', function Account() {
  var LOADING = -1;

  var checkedOutStatus = LOADING;
  var fineStatus = LOADING;
  var holdStatus = LOADING;

  var _render = function _render() {
    var accountIcon = 'fa fa-user-circle';
    // CHECKED OUT COUNTS
    if (checkedOutStatus === null) {
      $('.myresearch-menu .checkedout-status').addClass('hidden');
    } else {
      var html = '';
      if (checkedOutStatus !== LOADING) {
        if (checkedOutStatus.ok > 0) {
          html += '<span class="badge ok">' + checkedOutStatus.ok + '</span>';
        }
        if (checkedOutStatus.warn > 0) {
          html += '<span class="badge warn">' + checkedOutStatus.warn + '</span>';
          accountIcon = 'fa fa-book text-warning';
        }
        if (checkedOutStatus.overdue > 0) {
          html += '<span class="badge overdue">' + checkedOutStatus.overdue + '</span>';
          accountIcon = 'fa fa-book text-danger';
        }
      }
      $('.myresearch-menu .checkedout-status').html(html);
      $('.myresearch-menu .checkedout-status').removeClass('hidden');
    }
    // HOLDS
    if (holdStatus === 'PICKUP') {
      $('.myresearch-menu .holds-status')
        .removeClass('hidden fa-spin fa-spinner')
        .removeClass('fa-clock-o warn')
        .addClass('fa-bell ok');
      accountIcon = 'fa fa-bell text-success';
    } else if (holdStatus === 'INTRANSIT') {
      $('.myresearch-menu .holds-status')
        .removeClass('hidden fa-spin fa-spinner')
        .removeClass('fa-bell ok')
        .addClass('fa-clock-o warn');
    } else if (holdStatus !== LOADING) {
      $('.myresearch-menu .holds-status').addClass('hidden');
    }
    // FINES
    if (fineStatus !== null && fineStatus > 0) {
      $('.myresearch-menu .fines-status').html(
        '<span class="badge overdue">$' + (fineStatus / 100).toFixed(2) + '</span>'
      );
      accountIcon = 'fa fa-exclamation-triangle text-danger';
    } else if (fineStatus === LOADING) {
      $('.myresearch-menu .fines-status').html(
        '<i class="fa fa-spin fa-spinner" aria-hidden="true"></i>'
      );
    } else {
      $('.myresearch-menu .fines-status').addClass('hidden');
    }
    $('#account-icon').attr('class', accountIcon);
  };

  var _ajaxCheckedOut = function _ajaxCheckedOut() {
    $.ajax({
      url: VuFind.path + '/AJAX/JSON?method=getUserTransactions',
      dataType: 'json'
    })
    .done(function getCheckedOutDone(response) {
      checkedOutStatus = JSON.parse(response.data);
      _save();
      _render();
    })
    .fail(function getCheckedOutFail() {
      holdStatus = null;
    });
  };

  var _ajaxFines = function _ajaxFines() {
    $.ajax({
      url: VuFind.path + '/AJAX/JSON?method=getUserFines',
      dataType: 'json'
    })
    .done(function getFinesDone(response) {
      fineStatus = response.data;
      _save();
      _render();
    })
    .fail(function getFinesFail() {
      holdStatus = null;
    });
  };

  var _ajaxHolds = function _ajaxHolds() {
    $.ajax({
      url: VuFind.path + '/AJAX/JSON?method=getUserHolds',
      dataType: 'json'
    })
    .done(function getFinesDone(response) {
      holdStatus = parseInt(response.data);
      _save();
      _render();
    })
    .fail(function getFinesFail() {
      holdStatus = null;
    });
  };
  var _performAjax = function _performAjax() {
    _ajaxCheckedOut();
    _ajaxFines();
    _ajaxHolds();
  };

  var _save = function _save() {
    sessionStorage.setItem('account', JSON.stringify({
      checkedOut: checkedOutStatus,
      fines: fineStatus,
      holds: holdStatus
    }));
  };
  var load = function load() {
    if (!userIsLoggedIn) {
      sessionStorage.setItem('account-logged-in', false);
      return false;
    }
    $('.myresearch-menu .status').removeClass('hidden');
    var prevLoginStatus = sessionStorage.getItem('account-logged-in');
    var data = sessionStorage.getItem('account');
    if (data && prevLoginStatus !== null && prevLoginStatus === 'true') {
      var json = JSON.parse(data);
      checkedOutStatus = json.checkedOut;
      fineStatus = json.fines;
      holdStatus = json.holds;
      _render();
    } else {
      _performAjax();
      sessionStorage.setItem('account-logged-in', true);
    }
  };

  return {
    checkedOutStatus: checkedOutStatus,
    fineStatus: fineStatus,
    holdStatus: holdStatus,

    init: load
  };
});
