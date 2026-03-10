/**
 origin: {finc}
 called by view helper/controller:
 usage: {}
 modified for finc:
 * finc-specific file to list amsl resources on extra page etc, cf. #11004 - CK
 configured in: {}
 */

// Collapse nearest element on click
$('.collapse-toggler').click(function () {
  $(this).next().collapse('toggle');
});

// Collapse all button
$('#collapse-all-toggler').click(function () {
  $(this).prop('disabled', true);
  if($(this).hasClass('expanded')) {
    // don't collapse already collapsed
    $('#sources-list li ul.panel-collapse').filter('.in').collapse('toggle');
  } else {
    // don't expand already expanded
    $('#sources-list li ul.panel-collapse').filter(':not(.in)').collapse('toggle');
  }
  $('#collapse-all-toggler').toggleClass('expanded');
});

$('#sources-list li ul.panel-collapse').on('shown.bs.collapse hidden.bs.collapse', function() {
  $('#collapse-all-toggler').prop('disabled', false);
});

// toggle chevron
function toggleChevron(e) {
  $(e.target)
    .prev('.collapse-toggler')
    .find('i.fa')
    .toggleClass('fa-chevron-down fa-chevron-up');
}

$('#sources-list').on('hidden.bs.collapse shown.bs.collapse', toggleChevron);

// Sources filter
$('#sources-filter').keyup(function () {
  var that = this, $allListElements = $('ul > li');
  var $matchingListElements = $allListElements.filter(function (i, li) {
    var listItemText = $(li).text().toUpperCase(), searchText = that.value.toUpperCase();
    return ~listItemText.indexOf(searchText);
  });
  $allListElements.hide();
  $matchingListElements.show();
});
