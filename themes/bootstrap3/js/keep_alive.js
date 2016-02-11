/*global keepAliveInterval, VuFind */

$(document).ready(function() {
  window.setInterval(function() {
    $.getJSON(VuFind.getPath() + '/AJAX/JSON', {method: 'keepAlive'});
  }, keepAliveInterval * 1000);
});
