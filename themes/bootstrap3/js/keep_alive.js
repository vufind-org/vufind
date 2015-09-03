/*global keepAliveInterval, VUFIND */

$(document).ready(function() {
  window.setInterval(function() {
    $.getJSON(path + '/AJAX/JSON', {method: 'keepAlive'});
  }, keepAliveInterval * 1000);
});
