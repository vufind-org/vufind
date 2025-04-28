/*global VuFind, finna*/
finna.menu = (function finnaMenu() {
  /**
   * Initialize account check events for current user
   */
  function initAccountChecks() {
    VuFind.account.register("profile", {
      selector: ".profile-status",
      ajaxMethod: "getAccountNotifications",
      render: function render($element, status, ICON_LEVELS) {
        if (!status.notifications) {
          $element.addClass("hidden");
          return ICON_LEVELS.NONE;
        }
        $element.html('<span title="' + VuFind.translate('account_has_alerts') + '">' + VuFind.icon('warning', 'warning-icon') + '</span>');
        return ICON_LEVELS.DANGER;
      }
    });
  }

  /**
   * Initialize finna.menu
   */
  function init() {
    initAccountChecks();
  }

  var my = {
    init: init
  };

  return my;
})();
