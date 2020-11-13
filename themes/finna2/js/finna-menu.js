/*global VuFind, finna*/
finna.menu = (function finnaMenu() {
  function initAccountChecks() {
    VuFind.account.register("profile", {
      selector: ".profile-status",
      ajaxMethod: "getAccountNotifications",
      render: function render($element, status, ICON_LEVELS) {
        if (!status.notifications) {
          $element.addClass("hidden");
          return ICON_LEVELS.NONE;
        }
        $element.html('<i class="fa fa-exclamation-triangle" title="' + VuFind.translate('account_has_alerts') + '" aria-hidden="true"></i>');
        return ICON_LEVELS.DANGER;
      }
    });
  }

  function toggleSubmenu(a) {
    a.trigger('beforetoggle');
    a.toggleClass('collapsed');
    a.parent().find('ul').first().toggleClass('in', !a.hasClass('collapsed'));
    a.attr("aria-expanded", !a.hasClass("collapsed"));

    if (a.hasClass('sticky-menu')) {
      $('.nav-tabs-personal').toggleClass('move-list');
      if (!$('.nav-tabs-personal').hasClass('move-list')) {
        window.scroll(0, 0);
      }
    }
  }

  function initMenuLists() {
    $('.menu-parent').on('togglesubmenu.finna', function onToggleSubmenu() {
      toggleSubmenu($(this));
    });

    $('.menu-parent > .caret').on('click', function clickLink(e) {
      e.preventDefault();
      $(this).parent().trigger('togglesubmenu');
    });

    if ($('.mylist-bar').children().length === 0) {
      $('#open-list').one('beforetoggle.finna', function loadList() {
        var link = $(this);
        link.data('preload', false);
        $.ajax({
          type: 'GET',
          dataType: 'json',
          async: false,
          url: VuFind.path + '/AJAX/JSON?method=getMyLists',
          data: {'active': null}
        }).done(function onGetMyListsDone(data) {
          $('.mylist-bar').append(data.data);
          link.closest('.finna-movement').trigger('reindex');
          $('.add-new-list-holder').hide();
        });
      });
    } else {
      $('#open-list').removeClass('collapsed').siblings('ul').first().addClass('in');
    }
  }

  function init() {
    initMenuLists();
    initAccountChecks();
  }

  var my = {
    init: init
  };

  return my;
})();
