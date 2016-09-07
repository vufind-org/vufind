/*global getBibKeyString, google */

// we don't need to wait for dom ready since lang is in the dom root
var lang = document.documentElement.getAttribute('lang');
google.load("books", "0", {"language":lang});

function initialize() {
  var bibkeys = getBibKeyString().split(/\s+/);
  var viewer = new google.books.DefaultViewer(document.getElementById('gbsViewer'));
  viewer.load(bibkeys);
}

google.setOnLoadCallback(initialize);

