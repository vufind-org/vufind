/*global getBibKeyString, google */
$(function activateGooglePreview() {
  var lang = document.documentElement.getAttribute('lang');
  google.books.load({ language: lang });
  function initialize() {
    var bibkeys = getBibKeyString().split(/\s+/);
    var viewer = new google.books.DefaultViewer(document.getElementById('gbsViewer'));
    viewer.load(bibkeys);
  }
  google.books.setOnLoadCallback(initialize);
});