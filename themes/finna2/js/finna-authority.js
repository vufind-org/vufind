/*global VuFind, finna */
finna.authority = (function finnaAuthority() {
  function toggleAuthorityInfoCollapse(mode)
  {
    var authorityRecommend = $('.authority-recommend');
    var tabs = authorityRecommend.find('ul.nav-tabs');
    var authoritybox = authorityRecommend.find('.authoritybox');
    if (typeof mode !== 'undefined') {
      authoritybox.toggleClass('hide', mode);
    } else {
      authoritybox.toggleClass('hide');
    }
    var collapsed = authoritybox.hasClass('hide');
    var recordSummary = authoritybox.find('.recordSummary');

    if (!collapsed && !recordSummary.hasClass('truncate-field')) {
      recordSummary.addClass('truncate-field');
      finna.layout.initTruncate(authoritybox);
    }

    tabs.toggleClass('collapsed', mode);
    authorityRecommend.find('li.toggle').toggleClass('collapsed', mode);
    $.cookie('collapseAuthorityInfo', collapsed, {path: VuFind.path});
  }

  function initAuthorityRecommendTabs()
  {
    $('div.authority-recommend .nav-tabs li').not('.toggle').click(function onTabClick() {
      var self = $(this);
      var id = self.data('id');
      var parent = self.closest('.authority-recommend');
      var authoritybox = parent.find('.authoritybox');
      var collapsed = authoritybox.hasClass('hide');

      if (self.hasClass('active') && !collapsed) {
        return;
      }

      parent.find('.nav-tabs li').toggleClass('active', false);
      self.addClass('active');

      var spinner = parent.find('li.spinner');
      spinner.toggleClass('hide', false).show();

      $.getJSON(
        VuFind.path + '/AJAX/JSON',
        {
          method: 'getAuthorityFullInfo',
          id: id,
          searchId: parent.data('search-id')
        }
      )
        .done(function onGetAuthorityInfoDone(response) {
          authoritybox.html(typeof response.data.html !== 'undefined' ? response.data.html : '--');
          toggleAuthorityInfoCollapse(false);
          if (!authoritybox.hasClass('hide')) {
            finna.layout.initTruncate(authoritybox);
          }
          spinner.hide();
        })
        .fail(function onGetAuthorityInfoFail() {
          authoritybox.text(VuFind.translate('error_occurred'));
          spinner.hide();
          toggleAuthorityInfoCollapse(false);
        });
    });
    $('div.authority-recommend .nav-tabs li.toggle').click(function onToggle() {
      toggleAuthorityInfoCollapse();
    });
  }

  function initInlineInfoLinks()
  {
    $('div.authority').each(function initAuthority() {
      var $authority = $(this);
      $authority.find('a.show-info').click(function onClickShowInfo() {
        var $authorityInfo = $authority.find('.authority-info .content');
        if (!$authority.hasClass('loaded')) {
          $authority.addClass('loaded');
          $.getJSON(
            VuFind.path + '/AJAX/JSON',
            {
              method: 'getAuthorityInfo',
              id: $authority.data('authority'),
              type: $authority.data('type'),
              source: $authority.data('source')
            }
          )
            .done(function onGetAuthorityInfoDone(response) {
              $authorityInfo.html(typeof response.data.html !== 'undefined' ? response.data.html : '--');
            })
            .fail(function onGetAuthorityInfoFail() {
              $authorityInfo.text(VuFind.translate('error_occurred'));
            });
        }
        $authority.addClass('open');
        return false;
      });

      $authority.find('a.hide-info').click(function onClickHideInfo() {
        $authority.removeClass('open');
        return false;
      });
    });
  }

  function initAuthorityResultInfo(_holder) {
    var holder = typeof _holder === 'undefined' ? $(document) : _holder;

    holder.find('.authority-record-info').each(function getAuthorityRecordInfo() {
      $(this).one('inview', function onInView() {
        var $elem = $(this);
        if ($elem.hasClass('loaded')) {
          return;
        }
        var $item = $(this).parents('.record-container');
        if ($item.length === 0) {
          return;
        }
        $elem.addClass('loaded');
        $elem.addClass('loading');
        $elem.removeClass('hidden');
        $elem.append('<span class="js-load">' + VuFind.translate('loading') + '...</span>');
        var id = $item.find('.hiddenId')[0].value;
        $.getJSON(
          VuFind.path + '/AJAX/JSON',
          {
            method: 'getRecordInfoByAuthority',
            id: id,
            context: 'search'
          }
        )
          .done(function onGetAuthorityRecordCountDone(response) {
            if (response.data.html.length > 0) {
              $elem.html(response.data.html);
            } else {
              $elem.text('');
            }
            $elem.removeClass('loading');
          })
          .fail(function onGetAuthorityRecordCountFail() {
            $elem.text(VuFind.translate('error_occurred'));
            $elem.removeClass('loading');
          });
      });
    });
  }

  var my = {
    init: function init() {
      initInlineInfoLinks();
    },
    initAuthorityRecommendTabs: initAuthorityRecommendTabs,
    initAuthorityResultInfo: initAuthorityResultInfo
  };

  return my;
})();
