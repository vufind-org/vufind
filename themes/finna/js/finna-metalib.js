finna.metalib = (function() {
    var page = 1;
    var loading = false;
    var inited = false;
    var currentPath = null;
    var searchSet = null;

    var search = function(fullPath, saveHistory) {
        if (loading) {
            return;
        }

        loading = true;
        var historySupport = window.history && window.history.pushState;
        var useAJAXLoad =  !inited || historySupport;
        var replace = {
          'method': 'metalib'
        };

        // Tranform current url into an 'Ajaxified' version.
        // Update url parameters 'page' and 'set' if needed.
        var parts = fullPath.split('&');
        var url = parts.shift();
        if (useAJAXLoad) {
            url = url.replace('/MetaLib/Search?', '/AJAX/JSON?');
        }

        page = 1;
        var set = '';
        for (var i=0; i<parts.length; i++) {
            var param = parts[i].split('=');
            var key = param[0];
            var val = param[1];

            // remove old filters
            if (decodeURIComponent(key) == 'filter[]') {
                if (decodeURIComponent(val).substr(0, 12) == 'metalib_set:') {
                    set = decodeURIComponent(val).substr(12);
                    set = set.replace(new RegExp('"', 'g'), '');
                }
                continue;
            }
            
            // take set out
            if (key == 'set') {
                set = decodeURIComponent(val);
                continue;
            }
            
            if (key == 'page') {
                page = val;
            }

            // add parameters that are included as such
            if (!(key in replace)) {
                url += '&' + key + '=' + val;
            }
        }
        // add modified parameters
        $.each(replace, function(key, val) {
            url += '&' + key + '=' + val;
        });
        url += '&set=' + encodeURIComponent(set);
        
        if (window.location.hash) {
            url += window.location.hash;
        }
        currentPath = url;

        if (!useAJAXLoad) {
            var tmp = url.replace('/AJAX/JSON?', '/MetaLib/Search?');
            top.location = tmp;
            return false;
        }

        var holder = $('.container .results .ajax-results');
        holder.find('.holder').empty();

        var parent = this;
        toggleLoading(holder, true);

        var jqxhr = $.getJSON(url)
        .done(function(response) {
            toggleLoading(holder, false);
            loading = false;
            var hash = response.data['searchHash'];
            initTabNavigation(hash);
            var html = '';
            if (response.data['failed']) {
                html += response.data['failed'];
            }
            if (response.data['content']) {
                html += response.data['content'];
                if (response.data['paginationBottom']) {
                    html += response.data['paginationBottom'];
                }
            }
            holder.find('.holder').html(html);

            if (response.data['successful']) {
                $('.sidebar .database-list').html(response.data['successful']);
            }
            
            $('.search-controls .pagination > div').html(response.data['paginationTop']);
            $('.searchtools-background').html(response.data['searchTools']);
            $('.finna-main-header .container .row').html(response.data['header']);

            initPagination();
            initSearchTools();
            finna.layout.init();
            finna.openUrl.initLinks();
            finna.layout.initMobileNarrowSearch();
            scrollToRecord();
        })
        .fail(function(response) {
            holder.find('.holder').addClass("alert alert-danger").html(response.responseJSON.data);
        });

        // Save history if supported
        if (saveHistory && historySupport) {
            var state = {page: page};
            if (searchSet) {
                state.set = searchSet;
            }
            var title = '';
            // Restore ajaxified URL before saving history
            var tmp = url.replace('/AJAX/JSON?', '/MetaLib/Search?');
            tmp = tmp.replace('&method=metaLib', '');
            window.history.pushState(state, title, tmp);
        }
    };

    var toggleLoading = function(holder, mode) {
        var loader = holder.find('.loading');
        var set = $("form.search-sets input:checked").parent().text();
        loader.toggle(mode);
        loader.find(".page").text(page);
        loader.find(".set").text(set);
    };

    var initPagination = function() {
        $('ul.pagination a, ul.paginationSimple a').click(function() {
            if (!loading) {
                search($(this).attr('href'), true);
            }
            return false;
        });
    };

    var initSetChange = function(home) {
        $(".search-sets input").click(function() {
            if (home) {
                updateSearchForm();
            } else {
                var parts = currentPath.split('&');
                var url = parts.shift();
                for (var i=0; i<parts.length; i++) {
                    var param = parts[i].split('=');
                    var key = param[0];
                    var val = param[1];
                    if (key == 'page' || key == 'set') {
                        continue;
                    }
                    url += '&' + key + '=' + val;
                }
                url += currentPath.indexOf('?') == -1 ? '?' : '&';
                url += 'set=' + $(this).val();
                url = url.replace('/AJAX/JSON?', '/MetaLib/Search?');
                location = url;
            }
        });
    };

    var updateSearchForm = function() {
        var set = $("form.search-sets input:checked").val();
        var form = $("form[name='searchForm']");
        var input = form.find("input[name='set']");
        if (!input.length) {
            $("<input>").attr({type: "hidden", name: "set"}).appendTo(form);
        }
        form.find("input[name='set']").attr("value", set);
    };

    var initHistoryNavigation = function() {
        window.onpopstate = function(e){
            if (e.state){
                search(document.location.href, false);
            }
        };
    };

    var initTabNavigation = function(hash) {
        $(".nav-tabs li:not(.active) a").click(function() {
            var href = $(this).attr('href');
            href += href.indexOf('?') == -1
               ? '?' : '&';
            href += ('search[]=MetaLib:' + hash);
            $(this).attr('href', href);
        });
    };

    var initSearchTools = function() {
        // Email search link
        $('.mailSearch').click(function() {
            return Lightbox.get('Search','Email', {url:document.URL});
        });

    };

    var scrollToRecord = function() {
        if (window.location.hash) {
            var rec
                = $(".result input[value='" + window.location.hash.substr(1) + "']").closest(".result");
            if (rec.length) {
                $(document).scrollTop(rec.offset().top);
            }
        }
    };

    var my = {
        init: function(set, path) {
            searchSet = set;

            initSetChange();
            initHistoryNavigation();
            search(path, true);
            inited = true;
        },
        initHome: function() {
            updateSearchForm();
            initSetChange(true);
        },
    };

    return my;

})(finna);
