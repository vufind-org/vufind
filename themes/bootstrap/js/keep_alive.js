/*global path, keepAliveInterval */

$(document).ready(function() {
  window.setInterval(function() {
    $.getJSON(path + '/AJAX/JSON', {method: 'keepAlive'});
  }, keepAliveInterval * 1000);
});
