VuFind.register('lightbox_facets', function LightboxFacets() {
  var ajaxUrl;

  var lightboxFacetSorting = function lightboxFacetSorting() {
    var sortButtons = $('.js-facet-sort');
    var lastsort, lastlimit;
    function sortAjax(sort) {
      var list = $('#facet-list-'+sort);
      if (list.find('.js-facet-item').length === 0) {
        list.find('.js-facet-next-page').text(VuFind.translate('loading')+'...');
        $.ajax(ajaxUrl + '&layout=lightbox&facetsort='+sort)
          .done(function facetSortTitleDone(data) {
            list.prepend($('<span>'+data+'</span>').find('.js-facet-item'));
            list.find('.js-facet-next-page').text(VuFind.translate('more'));
          });
      }
      $('.full-facet-list').addClass('hidden');
      list.removeClass('hidden');
      sortButtons.removeClass('active');
    }
    sortButtons.click(function facetSortButton() {
      sortAjax(this.dataset.sort);
      $(this).addClass('active');
      return false;
    });
  };

  var setup = function setup(url) {
    ajaxUrl = url;
    lightboxFacetSorting();
    $('.js-facet-next-page').click(function facetLightboxMore() {
      var button = $(this);
      var page = parseInt(this.dataset.page);
      if (button.attr('disabled')) {
        return false;
      }
      button.attr('disabled', 1);
      button.text(VuFind.translate('loading')+'...');
      $.ajax(ajaxUrl + '&layout=lightbox&facetpage='+page+'&facetsort='+this.dataset.sort)
        .done(function facetLightboxMoreDone(data) {
          var htmlDiv = $('<div>'+data+'</div>');
          var list = htmlDiv.find('.js-facet-item');
          button.before(list);
          if (list.length && htmlDiv.find('.js-facet-next-page').length) {
            button.attr('data-page', page + 1);
            button.text(VuFind.translate('more'));
            button.removeAttr('disabled');
          } else {
            button.remove();
          }
        });
      return false;
    });
  };

  return { setup: setup };
});