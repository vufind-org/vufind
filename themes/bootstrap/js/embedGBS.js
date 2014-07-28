google.load("books", "0");

function initialize() {
  var bibkeys = getBibKeyString().split(/\s+/);
  var viewer = new google.books.DefaultViewer(document.getElementById('gbsViewer'));
  viewer.load(bibkeys);
}

google.setOnLoadCallback(initialize);

