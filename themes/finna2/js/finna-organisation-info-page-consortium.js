/*global VuFind, finna, Donut */
finna.organisationInfoPageConsortium = (function organisationInfoPageConsortium() {
  var holder = false;
  var parent = false;

  function initSectorUsageInfo(id, callback) {
    // Resolve building sector
    var url = 'https://api.finna.fi/v1/search?';
    var params = {
      'filter[]': 'building:0/' + id + '/',
      'limit': 1,
      'field[]': 'sectors'
    };
    url += $.param(params) + '&callback=?';

    $.getJSON(url)
      .done(function onSearchDone(response) {
        if (response.status === 'OK' && response.resultCount > 0 && 'sectors' in response.records[0]) {
          // General usage info for sector
          var sector = $(response.records[0].sectors).last()[0].value;
          // Use same info for academic libraries
          if (sector === '1/lib/poly/') {
            sector = '1/lib/uni/';
          }
          var usageInfo = VuFind.translate('usageInfo-' + sector);
          callback(true, finna.common.decodeHtml(usageInfo));
        } else {
          callback(false);
        }
      })
      .fail(function onSearchFail(/*response, textStatus, err*/) {
        callback(false);
      });
  }

  function enableConsortiumNaviItem(id) {
    holder.find('.consortium-navigation .scroll.' + id).addClass('active');
  }

  function updateConsortiumInfo(data, organisationList) {
    var infoField = holder.find('.consortium-info');
    var usageInfo = holder.find('.consortium-usage-rights').removeClass('hide');
    holder.find('.consortium-navigation').removeClass('hide');

    var consortiumHomepage = null;
    var consortiumHomepageLabel = null;

    // Info
    if ('consortium' in data) {
      var consortiumData = data.consortium;

      consortiumHomepage = finna.common.getField(consortiumData, 'homepage');
      consortiumHomepageLabel = finna.common.getField(consortiumData, 'homepageLabel');

      var desc = finna.common.getField(consortiumData, 'description');
      if (desc) {
        infoField.find('.description').html(desc);
      }
      var logo = null;
      if ('logo' in consortiumData) {
        logo = finna.common.getField(consortiumData.logo, 'small');
        $('<img/>').attr('src', logo).attr('alt', '').prependTo(infoField.find('.consortium-logo').removeClass('hide'));
      } else {
        infoField.addClass('no-logo');
        var homePage = infoField.find('.homepage').detach();
        homePage.appendTo(infoField);
      }

      var consortiumName = finna.common.getField(consortiumData, 'name');
      if (consortiumName) {
        infoField.removeClass('hide').find('.name').text(consortiumName);
        enableConsortiumNaviItem('building');
        holder.find('.consortium-navigation-list .scroll.building').html('<a href="#consortium-info-section" class="sr-only">' + consortiumName + '</a>' + '<span aria-hidden="true">' + consortiumName + '</span>');
      }

      var usageRights = null;
      var usageHolder = usageInfo.find('.usage-rights-text');
      var finnaData = finna.common.getField(consortiumData, 'finna');
      if (finnaData) {
        usageRights = finna.common.getField(finnaData, 'usage_info');
        if (usageRights) {
          usageHolder.html(usageRights);
        }

        var usagePerc = finna.common.getField(finnaData, 'usage_perc');
        if (usagePerc) {
          // Gauge
          $('.gauge-meter').removeClass('hide');

          var opts = {
            lines: 0,
            angle: 0.1,
            lineWidth: 0.09,
            limitMax: 'true',
            colorStart: '#00A2B5',
            colorStop: '#00A2B5',
            strokeColor: '#e5e5e5',
            generateGradient: true
          };
          var target = holder.find('.finna-coverage-gauge')[0];

          var gauge = new Donut(target).setOptions(opts);
          gauge.maxValue = 100;
          gauge.animationSpeed = 20;

          var gaugeVal = usagePerc;
          gauge.set(gaugeVal);
          holder.find('.gauge-value .val').text(Math.round(gaugeVal));
        }
        enableConsortiumNaviItem('usage');

        var linksHolder;
        var template;

        var finnaLink = finna.common.getField(finnaData, 'finnaLink');
        if (finnaLink) {
          linksHolder = holder.find('.consortium-info-row .finna-link');
          linksHolder.removeClass('hide');
          $('.links-panel').removeClass('hide');
          template = linksHolder.find('li.template').removeClass('template');
          $(finnaLink).each(function initFinnaLink(ind, obj) {
            var li = template.clone();
            var a = li.find('a');
            a.attr('href', obj.value).text(obj.name);
            li.appendTo(linksHolder.find('ul'));
          });
          template.remove();
        }

        var links = finna.common.getField(finnaData, 'links');
        if (links) {
          linksHolder = holder.find('.consortium-usage-rights .links');
          linksHolder.removeClass('hide');
          template = linksHolder.find('li.template').removeClass('template');
          $(links).each(function initLink(ind, obj) {
            var li = template.clone();
            var a = li.find('a');
            a.attr('href', obj.value).text(obj.name);
            li.appendTo(linksHolder.find('ul'));
          });
          template.remove();
        }
      }
      if (!usageRights) {
        initSectorUsageInfo(parent, function onDoneInitSectorUsage(success, info) {
          usageHolder.find('.fa-spinner').remove();
          var noInfo = usageHolder.find('.no-info');
          noInfo.removeClass('hide');
          if (success && info) {
            noInfo.after($('<p/>').html(info));
          }
        });
      }
    }

    if (consortiumHomepage) {
      var label = consortiumHomepageLabel ? consortiumHomepageLabel : consortiumHomepage;
      var linkHolder = holder.find('.consortium-info .homepage').removeClass('hide');
      $('<a/>').attr('href', consortiumHomepage).text(label).appendTo(linkHolder);
    }

    // Organisation list
    var list = false;
    var listHolder = infoField.find('.organisation-list');
    var ul = listHolder.find('ul');
    $.each(organisationList, function initOrganisation(id, obj) {
      if (obj.type === 'facility') {
        var name = obj.name;
        if ('shortName' in obj) {
          name = obj.shortName;
        }
        var li = $('<li/>');
        var homepage = finna.common.getField(obj, 'homepage');
        if (name && homepage) {
          list = true;
          $('<a/>').attr('href', homepage).text(name).appendTo(li);
          li.appendTo(ul);
        }
      }
    });

    if (list) {
      listHolder.addClass('truncate-field');
      finna.layout.initTruncate(listHolder.parent());
    }
  }

  function initConsortiumNavi() {
    var active = holder.find('.consortium-navigation .scroll.active');
    if (active.length > 1) {
      active.removeClass('hide');

      var sections = holder.find('.navi-section');
      holder.find('.consortium-navigation-list .scroll').each(function initConsortiumNaviScroll(ind) {
        $(this).click(function onClickConsortiumNavi() {
          $('html, body').animate({
            scrollTop: $(sections[ind]).offset().top - 45
          }, 200);
        });
      });
    } else {
      holder.find('.consortium-navigation').hide();
    }
  }

  var my = {
    enableConsortiumNaviItem: enableConsortiumNaviItem,
    initConsortiumNavi: initConsortiumNavi,
    updateConsortiumInfo: updateConsortiumInfo,
    init: function init(_parent, _holder) {
      parent = _parent;
      holder = _holder;
    }
  };

  return my;
})();
