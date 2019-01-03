/*global userIsLoggedIn, VuFind */
VuFind.register('account', function Account() {
  var LOADING = -1;
  var MISSING = -2;
  var _sessionDataKey = 'account-statuses';

  var checkedOutStatus = LOADING;
  var fineStatus = LOADING;
  var holdStatus = LOADING;

  var _save = function _save() {
    sessionStorage.setItem(_sessionDataKey, JSON.stringify({
      checkedOut: checkedOutStatus,
      fines: fineStatus,
      holds: holdStatus
    }));
  };

  // Clearing save forces AJAX update next page load
  var _clearSave = function _clearSave() {
    sessionStorage.removeItem(_sessionDataKey);
  };

  var _render = function _render() {
    function formatNumber(num) {
      return num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    var accountIcon = 'fa fa-user-circle';
    // CHECKED OUT COUNTS
    if (checkedOutStatus === MISSING) {
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
    if (holdStatus === MISSING) {
      $('.myresearch-menu .holds-status').attr('class', 'holds-status hidden');
    } else if (holdStatus === LOADING) {
      $('.myresearch-menu .holds-status').attr('class', 'holds-status fa fa-spin fa-spinner');
    } else if (holdStatus.available > 0) {
      $('.myresearch-menu .holds-status').attr('class', 'holds-status fa fa-bell ok');
      accountIcon = 'fa fa-bell text-success';
    } else if (holdStatus.in_transit > 0) {
      $('.myresearch-menu .holds-status').attr('class', 'holds-status fa fa-clock-o warn');
    }
    // FINES
    if (fineStatus === MISSING) {
      $('.myresearch-menu .fines-status').addClass('hidden');
    } else if (fineStatus === LOADING) {
      $('.myresearch-menu .fines-status').html(
        '<i class="fa fa-spin fa-spinner" aria-hidden="true"></i>'
      );
    } else {
      $('.myresearch-menu .fines-status').html(
        '<span class="badge overdue">$' + formatNumber(fineStatus / 100) + '</span>'
      );
      accountIcon = 'fa fa-exclamation-triangle text-danger';
    }
    $('#account-icon').attr('class', accountIcon);
  };

  var _ajaxCheckedOut = function _ajaxCheckedOut() {
    $.ajax({
      url: VuFind.path + '/AJAX/JSON?method=getUserTransactions',
      dataType: 'json'
    })
      .done(function getCheckedOutDone(response) {
        checkedOutStatus = response.data;
      })
      .fail(function getCheckedOutFail() {
        checkedOutStatus = MISSING;
      })
      .always(function getFinesFail() {
        _save();
        _render();
      });
  };

  var _ajaxFines = function _ajaxFines() {
    $.ajax({
      url: VuFind.path + '/AJAX/JSON?method=getUserFines',
      dataType: 'json'
    })
      .done(function getFinesDone(response) {
        fineStatus = response.data;
      })
      .fail(function getFinesFail() {
        fineStatus = MISSING;
      })
      .always(function getFinesFail() {
        _save();
        _render();
      });
  };

  var _ajaxHolds = function _ajaxHolds() {
    $.ajax({
      url: VuFind.path + '/AJAX/JSON?method=getUserHolds',
      dataType: 'json'
    })
      .done(function getFinesDone(response) {
        holdStatus = response.data;
      })
      .fail(function getFinesFail() {
        holdStatus = MISSING;
      })
      .always(function getFinesFail() {
        _save();
        _render();
      });
  };

  var _fetchData = function _fetchData() {
    _ajaxCheckedOut();
    _ajaxFines();
    _ajaxHolds();
  };

  var load = function load() {
    if (!userIsLoggedIn) {
      return false;
    }
    // Update information when certain actions are performed
    $('#cancelHold, #renewals').submit(_clearSave);

    $('.myresearch-menu .status').removeClass('hidden');
    var data = sessionStorage.getItem(_sessionDataKey);
    if (data) {
      var json = JSON.parse(data);
      if (json.checkedOut === MISSING || json.checkedOut === LOADING) {
        _ajaxCheckedOut();
      } else {
        checkedOutStatus = json.checkedOut;
      }
      if (json.fines === MISSING || json.fines === LOADING) {
        _ajaxFines();
      } else {
        fineStatus = json.fines;
      }
      if (json.holds === MISSING || json.holds === LOADING) {
        _ajaxHolds();
      } else {
        holdStatus = json.holds;
      }
      _render();
    } else {
      _fetchData();
    }
  };

  return {
    checkedOutStatus: checkedOutStatus,
    fineStatus: fineStatus,
    holdStatus: holdStatus,

    init: load
  };
});
