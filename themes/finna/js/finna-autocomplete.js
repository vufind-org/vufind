finna.autocomplete = (function() {
    var getPreserveFiltersMode = function() {
        return $(".searchFormKeepFilters").is(":checked");
    };

    var setupAutocomplete = function () {
        // Search autocomplete
        $('.autocomplete-finna').each(function(i, op) {
            var searcher = extractClassParams(op);
            $(op).autocomplete({
                loadingString: VuFind.translate('loading')+'...',
                suggestions: searcher['suggestions'] != '0',
                handler: function(query, cb) {
                    if (searcher['suggestions'] == '0') {
                        cb([]);
                        return;
                    }

                    var hiddenFilters = [];
                    $(op).closest('.searchForm').find('input[name="hiddenFilters[]"]').each(function() {
                        hiddenFilters.push($(this).val());
                    });
                    if (getPreserveFiltersMode()) {
                        // Include applied filters as hidden filters
                        $(op).closest('.searchForm')
                            .find('.applied-filter')
                            .not("[name='dfApplied']").not("[name='type']")
                            .each(function() {
                                hiddenFilters.push($(this).val());
                            });
                    }

                    var form = $(op).closest('.searchForm');
                    var data = {
                        q:query,
                        method:'getACSuggestions',
                        searcher:searcher['searcher'],
                        hiddenFilters:hiddenFilters
                    };

                    if (form.find(".select-type").length) {
                        // Multiple search handlers
                        data.type = form.find('input[name=type]').val();
                    } else {
                        // Only AllFields
                        var handler = form.find('.applied-filter[name=type]:checked');
                        if (handler.length) {
                            // Use current handler if "preserve filters" option is checked.
                            data.type = handler.val();
                        }
                    }
                    if ('onlySuggestions' in searcher && searcher['onlySuggestions'] == '1') {
                        data.onlySuggestions = 1;
                    }
                    if ('tab' in searcher) {
                        data.tab = searcher['tab'];
                    }
                    $.fn.autocomplete.ajax({
                        url: VuFind.getPath() + '/AJAX/JSON',
                        data: data,
                        dataType:'json',
                        success: function(json) {
                            if (json.status == 'OK' && json.data.length > 0) {
                                cb(json.data);
                            } else {
                                cb([]);
                            }
                        }
                    });
                }
            });
        });
    };

    var my = {
        init: function() {
            setupAutocomplete();
        }
    };

    return my;
})(finna);
