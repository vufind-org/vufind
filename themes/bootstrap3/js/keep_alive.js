/*global keepAliveInterval, VuFind */

$(document).ready(function() {
  window.setInterval(function() {
    $.getJSON(VuFind.path + '/AJAX/JSON', {method: 'keepAlive'});
  }, keepAliveInterval * 1000);
});
