/*global VuFind, finna */
finna.userListEmbed = (function userListEmbed() {
  var my = {
    init: function init() {
      $('.public-list-embed.show-all').not(':data(inited)').each(function initEmbed() {
        var embed = $(this);
        embed.data('inited', '1');

        var showMore = embed.find('.show-more');
        var spinner = embed.find('.fa-spinner');
        embed.find('.btn.load-more').click(function initLoadMore() {
          var resultsContainer = embed.find('.search-grid');
          spinner.removeClass('hide').show();

          var btn = $(this);

          var id = btn.data('id');
          var offset = btn.data('offset');
          var indexStart = btn.data('start-index');
          var view = btn.data('view');

          btn.hide();
          $.getJSON(
            VuFind.path + '/AJAX/JSON?method=getUserList',
            {
              id: id,
              offset: offset,
              indexStart: indexStart,
              view: view,
              method: 'getUserList' 
            }
          )
            .done(function onListLoaded(response) {
              showMore.remove();
              $(response.data.html).find('.result').each(function appendResult(/*index*/) {
                resultsContainer.append($(this));
              });
              
              finna.myList.init();
              finna.imagePaginator.reindexPaginators();
            })
            .fail(function onLoadListFail() {
              btn.show();
              spinner.hide();
            });
          
          return false;
        });
      });
    }
  };

  return my;
})();
