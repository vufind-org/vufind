/* global VuFind */

VuFind.register('resultcount', function resultCount() {
  function init() {
    $('ul.nav-tabs li.show-counts a').each(function queryResultCount(){
      var $this = $(this);
      if ($this.attr('href') !== undefined) {
        var queryString = $this.attr('href');
        var source = $this.data('source');
        $.ajax({
          url: VuFind.path + '/AJAX/JSON?method=getResultCount',
          dataType: 'json',
          data: {querystring: queryString, source: source},
          success: function appendResultCount(response){
            $this.append(' (' + response.data.total.toLocaleString() + ')');
          }
        });
      }
    });
  }
  return {
    init: init
  };
});
