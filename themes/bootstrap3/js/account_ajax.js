/*global VuFind */
VuFind.register('account', function Account() {
  var LOADING = 0;

  var checkedOutStatus = LOADING;
  var fineStatus = LOADING;
  var holdStatus = LOADING;

  var render = function render() {
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
        }
        if (checkedOutStatus.overdue > 0) {
          html += '<span class="badge overdue">' + checkedOutStatus.overdue + '</span>';
        }
      }
      $('.myresearch-menu .checkedout-status').html(html);
      $('.myresearch-menu .checkedout-status').removeClass('hidden');
    }
    // FINES
    if (fineStatus === 'EXIST') {
      $('.myresearch-menu .fines-status')
        .removeClass('hidden fa-spin fa-spinner')
        .addClass('fa-exclamation-triangle overdue');
    } else if (fineStatus !== LOADING) {
      $('.myresearch-menu .fines-status').addClass('hidden');
    }
    // HOLDS
    if (holdStatus === 'PICKUP') {
      $('.myresearch-menu .holds-status')
        .removeClass('hidden fa-spin fa-spinner')
        .removeClass('fa-clock-o warn')
        .addClass('fa-bell ok');
    } else if (holdStatus === 'INTRANSIT') {
      $('.myresearch-menu .holds-status')
        .removeClass('hidden fa-spin fa-spinner')
        .removeClass('fa-bell ok')
        .addClass('fa-clock-o warn');
    } else if (holdStatus !== LOADING) {
      $('.myresearch-menu .holds-status').addClass('hidden');
    }
  };

  var _ajaxCheckedOut = function _ajaxCheckedOut() {
    $.ajax({
      url: VuFind.path + '/AJAX/JSON?method=getUserTransactions',
      dataType: 'json'
    })
    .done(function getCheckedOutDone(response) {
      if (response.status === 405) {
        holdStatus = null;
      } else {
        checkedOutStatus = JSON.parse(response.data);
        _save();
        render();
      }
    })
    .fail(function getCheckedOutFail(response) {
      holdStatus = null;
    });
  };

  var _ajaxFines = function _ajaxFines() {
    $.ajax({
      url: VuFind.path + '/AJAX/JSON?method=getUserFines',
      dataType: 'json'
    })
    .done(function getFinesDone(response) {
      if (response.status === 405) {
        holdStatus = null;
      } else {
        fineStatus = response.data;
        _save();
        render();
      }
    })
    .fail(function getFinesFail(response) {
      holdStatus = null;
    });
  };

  var _ajaxHolds = function _ajaxHolds() {
    $.ajax({
      url: VuFind.path + '/AJAX/JSON?method=getUserHolds',
      dataType: 'json'
    })
    .done(function getFinesDone(response) {
      if (response.status === 405) {
        holdStatus = null;
      } else {
        holdStatus = response.data;
        _save();
        render();
      }
    })
    .fail(function getFinesFail(response) {
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
  var _load = function _load() {
    $('.myresearch-menu .status').removeClass('hidden');
    var data = sessionStorage.getItem('account');
    if (data) {
      var json = JSON.parse(data);
      checkedOutStatus = json.checkedOut;
      fineStatus = json.fines;
      holdStatus = json.holds;
      render();
    } else {
      _performAjax();
    }
  };

  return {
    checkedOutStatus: checkedOutStatus,
    fineStatus: fineStatus,
    holdStatus: holdStatus,

    update: _performAjax,
    init: _load
  };
});
