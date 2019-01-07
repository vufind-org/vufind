/*global userIsLoggedIn, VuFind */
VuFind.register('account', function Account() {
  var LOADING = -1 * Math.PI;
  var MISSING = -2 * Math.PI;
  var _sessionDataKey = 'account-statuses';

  // Types of statuses to fetch via AJAX:
  var _statusTypes = ['checkedOut', 'fines', 'holds'];
  // AJAX methods to use for the various types:
  var _lookupMethods = {
    checkedOut: 'getUserTransactions',
    fines: 'getUserFines',
    holds: 'getUserHolds'
  };
  // Holding area for retrieved statuses:
  var _statuses = {};

  var _save = function _save() {
    sessionStorage.setItem(_sessionDataKey, JSON.stringify(_statuses));
  };

  // Clearing save forces AJAX update next page load
  var _clearSave = function _clearSave() {
    sessionStorage.removeItem(_sessionDataKey);
  };

  var _getStatus = function _getStatus(key) {
    return ("undefined" === typeof _statuses[key]) ? LOADING : _statuses[key];
  };

  var _render = function _render() {
    var accountIcon = 'fa fa-user-circle';
    // CHECKED OUT COUNTS
    var checkedOut = _getStatus('checkedOut');
    if (checkedOut === MISSING) {
      $('.myresearch-menu .checkedout-status').addClass('hidden');
    } else {
      var html = '';
      if (checkedOut !== LOADING) {
        if (checkedOut.ok > 0) {
          html += '<span class="badge ok" data-toggle="tooltip" title="' + VuFind.translate('Checked Out Items') + '">' + checkedOut.ok + '</span>';
        }
        if (checkedOut.warn > 0) {
          html += '<span class="badge warn" data-toggle="tooltip" title="' + VuFind.translate('renew_item_due_tooltip') + '">' + checkedOut.warn + '</span>';
          accountIcon = 'fa fa-book text-warning';
        }
        if (checkedOut.overdue > 0) {
          html += '<span class="badge overdue" data-toggle="tooltip" title="' + VuFind.translate('renew_item_overdue_tooltip') + '">' + checkedOut.overdue + '</span>';
          accountIcon = 'fa fa-book text-danger';
        }
      }
      $('.myresearch-menu .checkedout-status').html(html);
      $('.myresearch-menu .checkedout-status').removeClass('hidden');
      $('[data-toggle="tooltip"]').tooltip();
    }
    // HOLDS
    var holds = _getStatus('holds');
    if (holds === LOADING) {
      $('.myresearch-menu .holds-status').attr('class', 'holds-status fa fa-spin fa-spinner');
    } else if (holds.available > 0) {
      $('.myresearch-menu .holds-status').attr('class', 'holds-status fa fa-bell text-success');
      accountIcon = 'fa fa-bell text-success';
    } else if (holds.in_transit > 0) {
      $('.myresearch-menu .holds-status').attr('class', 'holds-status fa fa-clock-o text-warning');
    } else {
      $('.myresearch-menu .holds-status').attr('class', 'holds-status hidden');
    }
    // FINES
    var fines = _getStatus('fines');
    if (fines === MISSING) {
      $('.myresearch-menu .fines-status').addClass('hidden');
    } else if (fines === LOADING) {
      $('.myresearch-menu .fines-status').html(
        '<i class="fa fa-spin fa-spinner" aria-hidden="true"></i>'
      );
    } else {
      $('.myresearch-menu .fines-status').html(
        '<span class="badge overdue">' + fines + '</span>'
      );
      accountIcon = 'fa fa-exclamation-triangle text-danger';
    }
    $('#account-icon').attr('class', accountIcon);
  };

  var _ajaxLookup = function _ajaxLookup(statusKey) {
    $.ajax({
      url: VuFind.path + '/AJAX/JSON?method=' + _lookupMethods[statusKey],
      dataType: 'json'
    })
      .done(function ajaxLookupDone(response) {
        _statuses[statusKey] = response.data;
      })
      .fail(function ajaxLookupFail() {
        _statuses[statusKey] = MISSING;
      })
      .always(function ajaxLookupAlways() {
        _save();
        _render();
      });
  };

  var _fetchData = function _fetchData() {
    for (var i = 0; i < _statusTypes.length; i++) {
      var currentKey = _statusTypes[i];
      _ajaxLookup(currentKey);
    }
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
      for (var i = 0; i < _statusTypes.length; i++) {
        var currentKey = _statusTypes[i];
        if ("undefined" === typeof json[currentKey] || json[currentKey] === MISSING || json[currentKey] === LOADING) {
          _ajaxLookup(currentKey);
        } else {
          _statuses[currentKey] = json[currentKey];
        }
      }
      _render();
    } else {
      _fetchData();
    }
  };

  return { init: load };
});
