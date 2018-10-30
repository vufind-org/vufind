$(document).ready(function() {
   var previous;
   $("#searchForm_type").on('focus', function () {
       previous = this.value;
   }).change(function adjustSearchSort(e) {
     if (previous == 'BibleRangeSearch') {
         var default_sort = $("#sort_options_1").data('default_sort');
         $("#sort_options_1").off(); // Prevent automatic reloading
         $("#sort_options_1").removeAttr('disabled'); //Handle leftover of forcing relevance search for bibrange
         $("#sort_options_1").val(default_sort).change();
         $("#sort_options_1").on();
         return false;       
     }
   });
});
