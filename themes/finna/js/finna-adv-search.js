finna.advSearch = (function() {

    var initForm = function() {
        var form = $('.main.template-dir-search #advSearchForm');
        var container = form.find('.ranges-container .slider-container').closest('.row');
        var field = container.find('input[name="daterange[]"]').eq(0).val();
        var fromField = container.find('#' + field + 'from');
        var toField = container.find('#' + field + 'to');
        form.submit(function(event) {
            if (typeof form[0].checkValidity == 'function') {
                // This is for Safari, which doesn't validate forms on submit
                if (!form[0].checkValidity()) {
                    event.preventDefault();
                    return;
                }
            } else {
                // JS validation for browsers that don't support form validation
                fromField.removeClass('invalid');
                toField.removeClass('invalid');
                if (fromField.val() && toField.val() && parseInt(fromField.val(), 10) > parseInt(toField.val(), 10)) {
                    fromField.addClass('invalid');
                    toField.addClass('invalid');
                    event.preventDefault();
                    return;
                } 
            }
            // Convert data range from/to fields into a "[from TO to]" query
            container.find('input[type="hidden"]').attr('disabled', 'disabled');
            var from = fromField.val() || '*';
            var to = toField.val() || '*';
            if (from != '*' || to != '*') {
                var filter = field + ':"[' + from + " TO " + to + ']"';

                $("<input>")
                    .attr("type", "hidden")
                    .attr("name", "filter[]")
                    .attr("value", filter)
                    .appendTo($(this));
            }
        });

        fromField.change(function() {
            toField.attr('min', fromField.val());
        });
        toField.change(function() {
            fromField.attr('max', toField.val());
        });
    };

    var my = {
        init: function() {
            initForm();
        }
    };

    return my;

})(finna);
