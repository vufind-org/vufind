finna.primoAdvSearch = (function() {
    var initForm = function() {
        $(".primo-add-search").on("click", function() {
            var fieldCount = $(".primo-advanced-search-fields").length;
            var last = $(".primo-advanced-search-fields").last();
            var newField = last.clone();
            $.each(["input", "select"], function(ind, el) {
                var element = newField.find(el);
                var newId = element.attr("id");
                newId = newId.substr(0, newId.lastIndexOf("_")+1) + fieldCount;
                element.attr("id", newId);
                if (el == "input") {
                    element.val("");
                }
            });
            last.after(newField);

            return false;
        });
    };

    var my = {
        init: function() {
            initForm();
        }
    };

    return my;

})(finna);
