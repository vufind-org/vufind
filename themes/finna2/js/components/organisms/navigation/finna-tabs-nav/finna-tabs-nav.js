$(document).ready(function onFinnaTabsNavReady() {
  $('.finna-tabs-nav').each(function doFinnaTabsNavLayout() {
    var activeUl = $(this).find(
      '.finna-nav > li.active > ul, .finna-nav > li.active-trail > ul'
    );
    if (activeUl.length > 0 && $(this).height() > 0) {
      $(this).css('height', $(this).children('.finna-nav').height() + activeUl.height());
    }
  });
});
