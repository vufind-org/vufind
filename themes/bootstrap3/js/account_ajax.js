/*global VuFind */
VuFind.register('account', function Account() {
  var LOADING = 0;
  var OK = 1;
  var WARN = 2;
  var ALERT = 3;

  var checkedOutStatus = LOADING;
  var fineStatus = LOADING;
  var holdStatus = LOADING;

  var _iconUpdate = function _iconUpdate($el, status) {
    var okStr = 'fa-check ok';
    var warnStr = 'fa-clock-o warn';
    var alertStr = 'fa-exclamation-triangle overdue';
    var _resetIcon = function _resetIcon($el) {
      $el
        .removeClass('fa-spin fa-spinner')
        .removeClass(okStr)
        .removeClass(warnStr)
        .removeClass(alertStr);
      return $el;
    }
    switch(status) {
      case OK:
        _resetIcon($el).addClass(okStr);
        break;
      case WARN:
        _resetIcon($el).addClass(warnStr);
        break;
      case ALERT:
        _resetIcon($el).addClass(alertStr);
        break;
    }
  }
  var update = function update() {
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
      $('.myresearch-menu .checkedout-status').html(html);
    }
    _iconUpdate($('.myresearch-menu .fines-status'), fineStatus);
    _iconUpdate($('.myresearch-menu .holds-status'), holdStatus);
  };

  var _ajaxCheckedOut = function _ajaxCheckedOut() {
    $.ajax({
      url: VuFind.path + '/AJAX/JSON?method=getUserTransactions',
      dataType: 'json'
    })
    .done(function getCheckedOutDone(response) {
      if (response.status === 405) {
        $('.myresearch-menu .checkedout-status').addClass('hidden');
      } else {
        checkedOutStatus = JSON.parse(response.data);
        _save();
        update();
      }
    })
    .fail(function getCheckedOutFail(response) {
      $('.myresearch-menu .checkedout-status').addClass('hidden');
    });
  };
  var _ajaxFines = function _ajaxFines() {
    $.ajax({
      url: VuFind.path + '/AJAX/JSON?method=getUserFines',
      dataType: 'json'
    })
    .done(function getFinesDone(response) {
      if (response.status === 405) {
        $('.myresearch-menu .fines-status').addClass('hidden');
      } else {
        switch (response.data) {
          case 'CLEAR':
            fineStatus = OK;
            break;
          case 'EXIST':
            fineStatus = WARN;
            break;
          case 'OVERDUE':
            fineStatus = ALERT;
            break;
        }
        _save();
        update();
      }
    })
    .fail(function getFinesFail(response) {
      $('.myresearch-menu .fines-status').addClass('hidden');
    });
  };
  var _ajaxHolds = function _ajaxHolds() {
    holdStatus = WARN;
    _save();
    update();
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
    var data = sessionStorage.getItem('account');
    if (data) {
      var json = JSON.parse(data);
      checkedOutStatus = json.checkedOut;
      fineStatus = json.fines;
      holdStatus = json.holds;
      update();
    } else {
      _performAjax();
    }
    $('.myresearch-menu .status').removeClass('hidden');
  };

  return {
    checkedOutStatus: checkedOutStatus,
    fineStatus: fineStatus,
    holdStatus: holdStatus,

    update: update,
    init: _load
  };
});
