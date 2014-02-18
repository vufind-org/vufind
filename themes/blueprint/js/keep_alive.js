$(document).ready(function() {
  // poll every 60 seconds
  var refreshTime = 60000;
  window.setInterval(function() {
    $.getJSON(path + '/AJAX/JSON', {method: 'keepAlive'});
  }, refreshTime);
});
