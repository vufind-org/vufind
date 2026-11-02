/*global keepAliveInterval, VuFind */

$(function keepAliveReady() {
  window.setInterval(function keepAliveInterval() {
    $.getJSON(VuFind.path + '/AJAX/JSON', {method: 'keepAlive'});
  }, keepAliveInterval * 1000);
});
