finna.advSearch = (function() {

    var initForm = function() {
        $(".main.template-dir-search #advSearchForm").on("submit", function() {
            // Convert data range from/to fields into a "[from TO to]" query
            var container = $(this).find('.ranges-container .slider-horizontal').closest('.row');
            var field = container.find('input[name="daterange[]"]').eq(0).val();
            var fromField = container.find('input[name="' + field + 'from"]');
            var toField = container.find('input[name="' + field + 'to"]');
            var from = fromField.val();
            var to = toField.val();
            if (from != "" && to != "") {
                var filter = field + ':"[' + from + " TO " + to + ']"';

                $("<input>")
                    .attr("type", "hidden")
                    .attr("name", "filter[]")
                    .attr("value", filter)
                    .appendTo($(this));
            } else {
                container.find('input[type="hidden"]').attr('disabled', 'disabled');
            }
            // Prevent original fields from getting submitted
            fromField.attr("disabled", "disabled");
            toField.attr("disabled", "disabled");
        });
    };

    var my = {
        init: function() {
            initForm();
        }
    };

    return my;

})(finna);
