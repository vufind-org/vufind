finna.autocomplete = (function() {
    var getPreserveFiltersMode = function() {
        return $(".searchFormKeepFilters").is(":checked");
    };

    var setupAutocomplete = function () {
        $('.searchForm').on('submit', function() {
            $('.autocomplete-finna').autocomplete.element.hide();
        });

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

                    var data = {
                        q:query,
                        method:'getACSuggestions',
                        searcher:searcher['searcher']
                    }

                    var form = $(op).closest('.searchForm');
                    var hiddenFilters = [];
                    form.find('input[name="hiddenFilters[]"]').each(function() {
                        hiddenFilters.push($(this).val());
                    });
                    if (getPreserveFiltersMode()) {
                        // Include applied filters as hidden filters
                        form
                            .find('.applied-filter')
                            .not("[name='dfApplied']").not("[name='type']").not('.daterange, .saved-search')
                            .each(function() {
                                hiddenFilters.push($(this).val());
                            });
                        var daterange = form.find('.applied-filter.daterange');
                        if (daterange.length) {
                            data[daterange.attr('name')] = daterange.val();
                        }
                    }
                    data.hiddenFilters = hiddenFilters;

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
