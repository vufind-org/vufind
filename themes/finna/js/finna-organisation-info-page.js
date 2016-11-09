/*global VuFind*/
finna.organisationInfoPage = (function() {
    var updateURL = false;
    var parent = null;
    var holder = null;
    var service = null;
    var infoWidget = null;
    var organisationList = {};
    var map = null;
    var mapHolder = null;
    var consortiumInfo = false;
    var consortium = false;

    var loadOrganisationList = function(buildings, id) {
        service.getOrganisations('page', parent, buildings, function(response) {
            if (response) {
                holder.find('.loading').toggleClass('loading', false);

                var cnt = 0;
                $.each(response['list'], function(ind, obj) {
                    organisationList[obj.id] = obj;
                    if (obj.type == 'library' || obj.type == 'other') {
                        cnt++;
                    }
                });

                infoWidget.organisationListLoaded(response);
                if (cnt > 0) {
                    initMap();
                    holder.find('.office-quick-information').show();

                    // if theres only one service point, hide searchbox and ignore initSearch
                    if (cnt == 1) {
                        holder.find('.office-search .searchbox-office,.show-all').hide();
                    } else {
                        // IE opens Delay initing autocomplete menu to prevent IE from opening it automatically at
                        initSearch();
                    }
                    holder.find('.ui-autocomplete-input')
                        .attr('placeholder', VuFind.translate('organisationInfoAutocomplete').replace('%%count%%', cnt))
                        .focus().blur();

                    if (typeof id != 'undefined' && id) {
                        updateSelectedOrganisation(id);
                    }
                } else {
                    holder.find('.map-ui').hide();
                }

                updateConsortiumNotification(response);
                if (consortiumInfo) {
                    if (cnt > 0) {
                        consortium.enableConsortiumNaviItem('service');
                    }
                    consortium.updateConsortiumInfo(response, organisationList);
                    consortium.initConsortiumNavi();
                }
                updateDefaultServicePoint(response);
                updateURL = true;
            } else {
                err();
            }
        });
    };

    var err = function() {
        var msg = $('<div/>')
            .addClass('alert alert-danger')
            .text(VuFind.translate('error_occurred'));
        holder.find('.organisation-info-page').first().replaceWith(msg);
    };

    var initMap = function() {
        $.each(organisationList, function(ind, obj) {
            // Map data (info bubble, icon)
            var bubble = holder.find('.map-bubble-template').clone();
            bubble.find('.name').text(obj.name);
            var openNow = null;
            if ('openTimes' in obj && 'openNow' in obj['openTimes']) {
                openNow = obj.openTimes.openNow;
            }

            if ('openTimes' in obj && obj.openTimes.schedules.length) {
                var scheduleTable = bubble.find('table');
                scheduleTable.find('tr').not('.template').remove();
                $.each(obj.openTimes.schedules, function(ind, scheduleObj) {
                    if (!('closed' in scheduleObj)) { //scheduleObj['times'].length) {
                        var timeObj = scheduleObj['times'][0];
                        var tr = scheduleTable.find('tr:first-child').clone();
                        tr.removeClass('template hide');
                        if ('today' in scheduleObj) {
                            tr.addClass(openNow ? 'open' : 'closed');
                        }
                        tr.find('.day').text(scheduleObj['day']);
                        tr.find('.opens').text(timeObj['opens']);
                        tr.find('.closes').text(timeObj['closes']);
                        scheduleTable.find('tbody').append(tr);
                    } else {
                        var tr = scheduleTable.find('tr:first-child').clone();
                        tr.removeClass('template hide');
                        if ('today' in scheduleObj) {
                            tr.addClass('today');
                        }
                        tr.find('.day').text(scheduleObj['day']);
                        tr.find('.time').hide();
                        tr.find('.time.closed-today').show().removeClass('hide');
                        scheduleTable.find('tbody').append(tr);
                    }
                });
            }

            obj['map'] = {info: bubble.html()};
        });

        var defaultId = Object.keys(organisationList)[0];
        map.draw(organisationList, defaultId);

        // Expand map
        holder.find('.expand-map').click (function() {
            mapHolder.toggleClass("expand", true);
            map.resize();
            $(this).hide();
            holder.find('.contract-map').show();
        });
        holder.find('.contract-map').click (function() {
            mapHolder.toggleClass("expand", false);
            map.resize();
            $(this).hide();
            holder.find('.expand-map').show();
        });
    };

    var initSearch = function() {
        var officeSearch = holder.find('#office-search');
        officeSearch.autocomplete({
            source: function (request, response) {
                var term = request.term.toLowerCase();
                var result = [];
                $.each(organisationList, function(id, obj) {
                    if ((obj.type == 'library' || obj.type == 'other')
                        && obj.name.toLowerCase().indexOf(term) !== -1
                    ) {
                        result.push({value: id, label: obj.name});
                    }
                });
                result = result.sort(function(a,b) {
                    return a.label > b.label ? 1 : -1;
                });
                response(result);
            },

            select: function(event, ui) {
                holder.find('#office-search').val(ui.item.label);
                var hash = ui.item.value;
                updateWindowHash(hash);
                return false;
            },

            focus: function (event, ui) {
                if ($(window).width() < 768) {
                    $('html, body').animate({
                        scrollTop: officeSearch.offset().top - 5
                    }, 100);
                }
                return false;
            },
            open: function(event, ui) {
                if (navigator.userAgent.match(/(iPod|iPhone|iPad)/)) {
                    holder.find('.ui-autocomplete').off('menufocus hover mouseover');
                }
            },
            minLength: 0,
            delay: 100,
            appendTo: ".autocomplete-container",
            autoFocus: false
        });
        officeSearch.on('click', function() {
            officeSearch.autocomplete("search", $(this).val());
        });
        officeSearch.find('li').on('touchstart', function() {
            officeSearch.autocomplete("search", $(this).val());
        });
        holder.find('.btn-office-search').on('click', function() {
            officeSearch.autocomplete("search", '');
            officeSearch.focus();
            return false;
        });
    };

    var updateWindowHash = function(hash) {
        // Create a fake hidden div with id=hash and absolute position
        // so that window scroll position is preserved when the hash is updated below.
        holder.find('div.hash').remove();
        $('<div/>')
            .css({
                position:'absolute',
                visibility:'hidden',
                top: $(document).scrollTop() + 'px'
            })
            .addClass('hash')
            .attr( 'id', hash )
            .appendTo(holder);

        // Set hash first to empty value, so that onhashchange is triggered when
        // the same menu item is re-selected.
        window.location.hash = '';
        window.location.hash = hash;
    };

    var hideMapMarker = function() {
        holder.find('#marker-tooltip').hide();
    };


    var updateConsortiumNotification = function(data) {
        if ('consortium' in data) {
            if ('finna' in data.consortium
                && 'notification' in data.consortium.finna
               ) {
                   holder.find('.consortium-notification')
                       .html(data.consortium.finna.notification).removeClass('hide');
               }
        }
    };

    var updateDefaultServicePoint = function(data) {
        if ('consortium' in data) {
            if ('finna' in data.consortium
                && 'service_point' in data.consortium.finna
                && data.consortium.finna.service_point !== null
                && window.location.hash == ''
            ) {
               window.location.hash = data.consortium.finna['service_point'];
            }
        }
    };

    var setOfficeInformationLoader = function(mode) {
        holder.find('.office-information-loader').toggle(mode);
    };

    var updateSelectedOrganisation = function(id) {
        setOfficeInformationLoader(true);
        holder.find('.error, .info-element').hide();
        infoWidget.showDetails(id, '', true);
        $('#office-search').val('');
        
        var notification = holder.find('.office-search-notifications .notification');
        if (id in organisationList) {
            var data = organisationList[id];
            map.hideMarker();
            if ('address' in data && 'coordinates' in data.address) {
                map.selectMarker(id);
                notification.hide();
            } else {
                map.reset();
                notification.show().delay(2000).fadeOut(500);
                setOfficeInformationLoader(false);
            }
            return;
        }
    };

    var updateGeneralInfo = function(data, rssAvailable) {
        holder.find('.office-quick-information').toggleClass('hide', false);
        var contactHolder = holder.find('.contact-details-' + (rssAvailable ? 'rss' : 'no-rss'));
        contactHolder.show();
        finna.feed.init(contactHolder);

        holder.find('.office-quick-information .service-title').text(data.name);
        if ('address' in data) {
            holder.find('.office-links.address').html(data.address);
            var address = holder.find('.address-contact');
            address.show().find('> p').html(data.address);
        }
        if ('email' in data) {
            var email = data['email'];
            holder.find('.email').attr('href', 'mailto:' + email).show();
            holder.find('.email span.email').text(email.replace('@','(at)'));

            holder.find('.email-contact').show();
        }
        if ('homepage' in data) {
            holder.find('.office-website > a').attr('href', data['homepage']);
            holder.find('.office-website').show();
        }

        if ('routeUrl' in data) {
            holder.find('.office-links.route').attr('href', data['routeUrl']).show();
        }

        if ('slogan' in data.details) {
            holder.find('.office-description.slogan').text(data.details.slogan).show();
        }

        var longDesc = holder.find('.office-description.description-long');
        if ('description' in data.details) {
            longDesc.html(data.details.description).show();
        }

        if ('links' in data.details) {
            var links = data.details['links'];
            if (links.length) {
                $.each(links, function(ind, obj) {
                    if (obj.name == 'Facebook') {
                        var btn = holder.find('.social-button');
                        btn.find('> a').attr('href', obj['url']);
                        btn.show();
                    }
                });
            }
        }

        var openToday = false;

        if ('schedules' in data.openTimes) {
            $.each(data.openTimes.schedules, function(ind, obj) {
                if ('today' in obj && 'times' in obj && obj.times.length) {
                    openToday = obj.times[0];

                    var timeOpen = holder.find('.time-open');
                    timeOpen.find('.opens').text(openToday.opens);
                    timeOpen.find('.closes').text(openToday.closes);
                    timeOpen.show();
                }
            });
        }

        var hasSchedules
            = 'openTimes' in data && 'schedules' in data.openTimes
            && data.openTimes.schedules.length > 0;

        if (hasSchedules) {
            holder.find('.open-or-closed > span.library-is-' + (data.openNow ? 'open' : 'closed')).show();
        }

        var img = holder.find('.building-image');
        if ('pictures' in data.details) {
            var src = data.details.pictures[0].url;
            img.show();
            if (img.attr('src') != src) {
                img.attr('src', src);
                img.fadeTo(0, 0);
                img.on('load', function () {
                    $(this).stop(true, true).fadeTo(300, 1);
                });
            } else {
                img.fadeTo(300, 1);
            }
            holder.find('.building-name').text(data.name).show();
        } else {
            img.hide();
        }

        if ('buildingYear' in data.details) {
            var year = holder.find('.building-year');
            year.find('> span').text(data.details.buildingYear);
            year.show();
        }

        if ('phone' in data.details) {
            var phones = holder.find('.phone-numbers');
            phones.find('> p').html(data.details.phone);
            phones.show();
        }


        $('.office-quick-information').show();
        $('.office-information').show();
        setOfficeInformationLoader(false);
    };

    var updateServices = function(data) {
        if ('allServices' in data.details) {
            holder.find('.services').show();
            var serviceHolder = holder.find('.service-list').empty();
            $(data.details.allServices).each(function(ind, obj) {
                var li = $('<li/>');
                li.append($('<strong/>').text(obj[0]));
                if (obj.length > 0) {
                    li.append($('<p/>').html(obj[1]));
                }
                li.appendTo(serviceHolder);
            });
        }
    };

    var updateRSSFeeds = function(data) {
        var rssAvailable = false;
        if ('rss' in data.details) {
            $(data.details.rss).each(function(ind, obj) {
                var url = obj['url'];
                var id = obj['id'];
                if (id != 'news' && id != 'events') {
                    return false;
                }
                var feedHolder = holder.find('.feed-container.' + id + '-feed');
                feedHolder
                    .empty().show()
                    .data('url', encodeURIComponent(obj['url']))
                    .data('feed', 'organisation-info-' + encodeURIComponent(id))
                    .closest('.rss-container').show();

                finna.feed.loadFeedFromUrl(feedHolder);
                rssAvailable = true;
            });
        }
        return rssAvailable;
    };

    var getService = function() {
        return service;
    };

    var getOrganisationFromURL = function() {
        if (window.location.hash != "") {
            return parseInt(window.location.hash.replace('#',''));
        }
        return false;
    };

    /**
     * Initialize organisation page
     *
     * @param options Array of options:
     *   library        int    Organisation id
     *   buildings      string Comma separated list of buildings to show on map
     *   consortiumInfo 0|1    Show consortium info?
     */
    var init = function(options) {
        holder = $('section[role="main"]');

        setOfficeInformationLoader(false);

        parent = finna.common.getField(options, 'id');
        consortiumInfo = finna.common.getField(options, 'consortiumInfo') === 1;
        var buildings = finna.common.getField(options, 'buildings');
        var mapTileUrl = '//map-api.finna.fi/v1/rendered/{z}/{x}/{y}.png';
        var attribution = 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>';

        if (typeof parent == 'undefined') {
            return;
        }

        mapHolder = holder.find('.map-widget');
        map = finna.organisationMap();
        map.init(mapHolder[0], mapTileUrl, attribution);

        $(map).on('marker-click', function(ev, id) {
            if (updateURL) {
                window.location.hash = id;
            }
            hideMapMarker();
        });

        $(map).on('marker-mouseout', function(ev) {
            hideMapMarker();
        });

        $(map).on('marker-mouseover', function(ev, data) {
            var tooltip = holder.find('#marker-tooltip');
            var name = organisationList[data.id].name;
            tooltip.removeClass('hide').html(name).css({
                'left': data.x,
                'top': data.y - 35
            });
            tooltip.css({'margin-left': -(tooltip.outerWidth())/2 + 20}).show();
        });

        holder.find('.map-control-buttons .show-all').click(
            function() {
                map.reset();
                return false;
            }
        );

        service = finna.organisationInfo();
        infoWidget = finna.organisationInfoWidget();

        var widgetHolder = holder.find('.organisation-info');
        widgetHolder.on('detailsLoaded', function(ev, id) {
            var info = service.getDetails(id);
            updateServices(info);
            var rssAvailable = updateRSSFeeds(info);
            updateGeneralInfo(info, rssAvailable);
        });

        infoWidget.init(widgetHolder, service);

        if (consortiumInfo) {
            consortium = new finna.organisationInfoPageConsortium();
            consortium.init(parent, holder);
        }

        window.onhashchange = function() {
            if (id = getOrganisationFromURL()) {
                updateSelectedOrganisation(id);
            }

            // Blur so that mobile keyboard is closed
            holder.find('#office-search').blur();
        };

        var library = null;
        if (hash = getOrganisationFromURL()) {
            library = hash;
        }

        loadOrganisationList(buildings, library);
    };

    var my = {
        init: init
    };

    return my;

})(finna);
