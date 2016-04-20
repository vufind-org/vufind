/*global VuFind*/
finna.organisationInfo = (function() {
    var organisationList = {};
    var currentWeekNum = null;
    var currentScheduleInfo = null;
    var loading = false;

    var query = function(organisation, queryParams, callback) {
        var url = VuFind.path + '/AJAX/JSON';
        var params = {method: 'getOrganisationInfo', consortium: organisation, params: queryParams};

        $.getJSON(url, params)
        .done(function(response) {
            loading = false;
            if (response.data) {
                callback(true, response.data);
                return;
            }
            callback(false, 'Error reading organisation info');
        })
        .fail(function(response, textStatus, err) {
            loading = false;
            var err = false;
            if (typeof response.responseJSON != 'undefined') {
                err = response.responseJSON.data;
            }
            callback(false, err);
        });
    };

    var loadOrganisationList = function(holder) {
        holder.find('.week-navi.prev-week').fadeTo(0,0);

        var consortium = holder.data('consortium');
        if (typeof consortium == 'undefined') {
            return;
        }

        toggleSpinner(holder, true);
        holder.find('.error,.info-element').hide();

        var response = query(consortium, {action: 'list'}, function(success, response) {
            if (!success) {
                holder.html('<!-- Organisation info could not be loaded: ' + response + ' -->');
            }
            listLoaded(holder, response);
        });
    };

    var listLoaded = function(holder, data) {
        var id = getField(data, 'id');

        var list = getField(data, 'list');
        $.each(list, function(ind, obj) {
            organisationList[obj.id] = obj;
        });


        var found = false;
        var menu = holder.find('.organisation');
        $.each(list, function(ind, obj) {
            if (id == obj['id']) {
                found = true;
            }
            $('<option/>', {value: obj['id'], text: obj['name']}).appendTo(menu);
        });

        if (!found) {
            id = menu.find('option').eq(0).val();
        }
        menu.val(id);
        menu.on('change', function() {
            showDetails(holder, $(this).val(), $(this).find('option:selected').text());
        });

        var organisation = holder.find('.organisation option:selected');
        showDetails(holder, organisation.val(), organisation.text());

        toggleSpinner(holder, false);
        holder.find('.content').removeClass('hide');

        attachWeekNaviListener(holder);
    };

    var attachWeekNaviListener = function(holder) {
        holder.find('.week-navi').unbind('click').click(function() {
            if (loading) {
                return;
            }
            loading = true;
            holder.find('.week-text .num').text(currentWeekNum + parseInt($(this).data('dir')));
            $(this).attr('data-classes', $(this).attr('class'));
            $(this).removeClass('fa-arrow-right fa-arrow-left');
            $(this).addClass('fa-spinner fa-spin');

            loadDetails(
                holder,
                holder.find('.organisation').val(),
                false, $(this).data('dir')
            );
        });
    };

    var showDetails = function(holder, id, name) {
        holder.find('.error,.info-element').hide();
        holder.find('.is-open').hide();

        var data = organisationList[id];
        openNow = getField(data, 'openNow');

        if (openNow !== null) {
            holder.find('.is-open').hide();
            holder.find('.is-open' + (openNow ? '.open' : '.closed')).show();
        }

        if (email = getField(data, 'email')) {
            holder.find('.email').attr('href', 'mailto:' + email).show();
        }

        if (homepage = getField(data, 'homepage')) {
            $('a.details').attr('href', homepage);
            $('.details-link').show();
        }

        if (routeUrl = getField(data, 'routeUrl')) {
            holder.find('.route').attr('href', routeUrl).show();
        }

        if (mapUrl = getField(data, 'mapUrl')) {
            var address = '';
            if (street = getField(data.address, 'street')) {
                address += street + ', ';
            }
            if (zip = getField(data.address, 'zip')) {
                address += zip;
            }
            if (city = getField(data.address, 'city')) {
                address += ' ' + city;
            }

            var map = holder.find('.map');
            map.find('> a').attr('href', mapUrl);
            map.find('.map-address').text(address);
            map.show();
        }

        loadDetails(holder, id, true);
    };

    var loadDetails = function(holder, id, fullDetails, dir) {
        var periodStart = holder.data('period-start');
        var params = {action: 'details', id: id};

        if (fullDetails) {
            details = getCachedDetails(id);
            if (details && details.periodStart) {
                if (details.periodStart == periodStart) {
                    detailsLoaded(holder, id, details, false);
                    return;
                }
            }
        }

        var schedulesHolder = holder.find('.schedules');

        var params = {action: 'details', id: id, fullDetails: fullDetails ? 1 : 0};
        if (periodStart) {
            params = $.extend(params, {periodStart: periodStart});
        }
        if (dir) {
            params = $.extend(params, {dir: dir});
        }

        query(holder.data('consortium'), params, function(success, obj) {
            if (!success) {
                holder.find('.error').show();
                return;
            }
            detailsLoaded(holder, id, obj, fullDetails);
        });
    };

    var detailsLoaded = function(holder, id, response, cache) {
        if (cache) {
            cacheDetails(id, response);
        }

        toggleSpinner(holder, false);

        holder.find('.week-navi-holder .week-navi').each(function() {
            if (classes = $(this).data('classes')) {
                $(this).attr('class', classes);
            }
        });

        var schedulesHolder = holder.find('.schedules');
        if (html = getField(response, 'html')) {
            schedulesHolder.find('.content').html(html);
        } else {
            holder.find('.no-schedules').show();
        }

        holder.find('.week-navi-holder').toggle(html != null);

        schedulesHolder.stop(true, false).fadeTo(200, 1);

        if (info = getField(response, 'description')) {
            if (!currentScheduleInfo || info != currentScheduleInfo) {
                currentScheduleInfo = info;
                holder.find('.schedules-info .truncate-field, .more-link, .less-link').remove();
                var infoHolder = holder.find('.schedules-info');
                var truncateField = $('<div/>').addClass("truncate-field").attr('data-rows', 5).attr('data-row-height', 20);
                $('<div/>').text(info).appendTo(truncateField);
                infoHolder.show().append(truncateField);
            }
        } else {
            currentScheduleInfo = null;
        }

        if (periodStart = getField(response, 'periodStart')) {
            holder.data('period-start', periodStart);
        }

        if (weekNum = getField(response, 'weekNum')) {
            currentWeekNum = parseInt(weekNum);
            updateCurrentWeek(holder);
        }

        var prevBtn = holder.find('.week-navi.prev-week');
        if (currentWeek = getField(response, 'currentWeek')) {
            prevBtn.unbind('click').fadeTo(200, 0);
        } else {
            prevBtn.fadeTo(200, 1);
            attachWeekNaviListener(holder);
        }

        if (phone = getField(response, 'phone', id)) {
            holder.find('.phone').attr('data-original-title', phone).show();
        }

        var links = getField(response, 'links', id);
        if (links && links.length) {
            holder.find('.facebook').attr('href', links[0]['url']).show();
        }

        var img = holder.find('.facility-image');
        if (pictures = getField(response, 'pictures', id)) {
            var src = pictures[0].url;
            img.show();
            if (img.attr('src') != src) {
                img.fadeTo(0, 0);
                img.on('load', function () {
                    $(this).stop(true, true).fadeTo(300, 1);
                });
                img.attr('src', src).attr('alt', name);
                img.closest('.info-element').show();
            } else {
                img.fadeTo(300, 1);
            }
        } else {
            img.hide();
        }

        if (services = getField(response, 'services', id)) {
            $.each(services, function(ind, obj) {
                holder.find('.services .service-' + obj).show();
            });
        }

        finna.layout.initTruncate(holder);
    };

    var updateCurrentWeek = function(holder) {
        if (currentWeekNum) {
            holder.attr('data-week-num', currentWeekNum);
            holder.find('.week-navi-holder .week-text .num').text(currentWeekNum);
        }
    };

    var getField = function(obj, field, organisationId) {
        if (typeof obj[field] != 'undefined') {
            return obj[field];
        }
        if (organisationId) {
            if (cache = getCachedDetails(organisationId)) {
                if (typeof cache[field] != 'undefined') {
                    return cache[field];
                }
            }
        }
        return null;
    };

    var getCachedDetails = function(id) {
        if (typeof organisationList[id]['details'] != 'undefined') {
            return organisationList[id]['details'];
        }
        return null;
    };

    var cacheDetails = function(id, details) {
        organisationList[id]['details'] = details;
    };

    var toggleSpinner = function(holder, mode) {
        var spinner = holder.find('.loader');
        if (mode) {
            spinner.fadeIn();
        } else {
            spinner.hide();
        }
    };

    var my = {
        init: function() {
            loadOrganisationList($('.organisation-info'));
        }
    };
    return my;

})(finna);
