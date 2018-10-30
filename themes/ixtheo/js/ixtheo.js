$(document).ready(function() {
   var previous_handler;
   $("#searchForm_type").on('focus', function () {
       previous_handler = this.value;
   }).change(function adjustSearchSort(e) {
     if (previous_handler == 'BibleRangeSearch') {
         var default_sort = $("#sort_options_1").data('default_sort');
         $("#sort_options_1").off(); // Prevent automatic reloading
         $("#sort_options_1").removeAttr('disabled'); //Handle leftover of forcing relevance search for bibrange
         $("#sort_options_1").val(default_sort).change();
         $(":input[name='sort']").val(default_sort);
         $("#sort_options_1").on();
         return false;
     }
   });
});
