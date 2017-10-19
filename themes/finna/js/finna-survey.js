/*global finna */
finna.finnaSurvey = (function finnaSurvey() {
  var _cookieName = 'finnaSurvey';

  function init() {
    var cookie = $.cookie(_cookieName);
    if (typeof cookie !== 'undefined' && cookie === '1') {
      return;
    }

    var holder = $('#finna-survey');
    holder.find('a').click(function onClickHolder(/*e*/) {
      holder.fadeOut(100);
      $.cookie(_cookieName, '1', { path: '/' });

      if ($(this).hasClass('close-survey')) {
        return false;
      }
    });

    setTimeout(function timeoutCallback() {
      holder.fadeIn(300).css({bottom: 0});
    }, 150);
  }

  var my = {
    init: init
  };

  return my;
})();
