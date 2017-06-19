/*exported addGroup, addSearch, deleteGroup, deleteSearch */
var nextGroup = 0;
var groupLength = [];

function addSearch(group, _fieldValues) {
  var fieldValues = _fieldValues || {};
  // Build the new search
  var inputID = group + '_' + groupLength[group];
  var $newSearch = $($('#new_search_template').html());

  $newSearch.attr('id', 'search' + inputID);
  $newSearch.find('input.form-control')
    .attr('id', 'search_lookfor' + inputID)
    .attr('name', 'lookfor' + group + '[]')
    .val('');
  $newSearch.find('select.adv-term-type option:first-child').attr('selected', 1);
  $newSearch.find('select.adv-term-type')
    .attr('id', 'search_type' + inputID)
    .attr('name', 'type' + group + '[]');
  $newSearch.find('.adv-term-remove')
    .attr('onClick', 'return deleteSearch(' + group + ',' + groupLength[group] + ')');
  // Preset Values
  if (typeof fieldValues.term !== "undefined") {
    $newSearch.find('input.form-control').val(fieldValues.term);
  }
  if (typeof fieldValues.field !== "undefined") {
    $newSearch.find('select.adv-term-type option[value="' + fieldValues.field + '"]').attr('selected', 1);
  }
  if (typeof fieldValues.op !== "undefined") {
    $newSearch.find('select.adv-term-op option[value="' + fieldValues.op + '"]').attr('selected', 1);
  }
  // Insert it
  $("#group" + group + "Holder").before($newSearch);
  // Individual search ops (for searches like EDS)
  if (groupLength[group] === 0) {
    $newSearch.find('.first-op')
      .attr('name', 'op' + group + '[]')
      .removeClass('hidden');
    $newSearch.find('select.adv-term-op').remove();
  } else {
    $newSearch.find('select.adv-term-op')
      .attr('id', 'search_op' + group + '_' + groupLength[group])
      .attr('name', 'op' + group + '[]')
      .removeClass('hidden');
    $newSearch.find('.first-op').remove();
    $newSearch.find('label').remove();
    // Show x if we have more than one search inputs
    $('#group' + group + ' .adv-term-remove').removeClass('hidden');
  }
  groupLength[group]++;
  return false;
}

function deleteSearch(group, sindex) {
  for (var i = sindex; i < groupLength[group] - 1; i++) {
    var $search0 = $('#search' + group + '_' + i);
    var $search1 = $('#search' + group + '_' + (i + 1));
    $search0.find('input').val($search1.find('input').val());
    var select0 = $search0.find('select')[0];
    var select1 = $search1.find('select')[0];
    select0.selectedIndex = select1.selectedIndex;
  }
  if (groupLength[group] > 1) {
    groupLength[group]--;
    $('#search' + group + '_' + groupLength[group]).remove();
    if (groupLength[group] === 1) {
      $('#group' + group + ' .adv-term-remove').addClass('hidden'); // Hide x
    }
  }
  return false;
}

function addGroup(_firstTerm, _firstField, _join) {
  var firstTerm = _firstTerm || '';
  var firstField = _firstField || '';
  var join = _join || '';

  var $newGroup = $($('#new_group_template').html());
  $newGroup.attr('id', 'group' + nextGroup);
  $newGroup.find('.search_place_holder')
    .attr('id', 'group' + nextGroup + 'Holder')
    .removeClass('hidden');
  $newGroup.find('.add_search_link')
    .attr('id', 'add_search_link_' + nextGroup)
    .attr('onClick', 'return addSearch(' + nextGroup + ')')
    .removeClass('hidden');
  $newGroup.find('.adv-group-close')
    .attr('onClick', 'return deleteGroup(' + nextGroup + ')');
  $newGroup.find('select.form-control')
    .attr('id', 'search_bool' + nextGroup)
    .attr('name', 'bool' + nextGroup + '[]');
  $newGroup.find('.search_bool')
    .attr('for', 'search_bool' + nextGroup);
  if (join.length > 0) {
    $newGroup.find('option[value="' + join + '"]').attr('selected', 1);
  }
  // Insert
  $('#groupPlaceHolder').before($newGroup);
  // Populate
  groupLength[nextGroup] = 0;
  addSearch(nextGroup, {term: firstTerm, field: firstField});
  // Show join menu
  if (nextGroup > 0) {
    $('#groupJoin').removeClass('hidden');
    // Show x
    $('.adv-group-close').removeClass('hidden');
  }
  return nextGroup++;
}

function deleteGroup(group) {
  // Find the group and remove it
  $("#group" + group).remove();
  // If the last group was removed, add an empty group
  if ($('.adv-group').length === 0) {
    addGroup();
  } else if ($('#advSearchForm .adv-group').length === 1) {
    $('#groupJoin').addClass('hidden'); // Hide join menu
    $('.adv-group .adv-group-close').addClass('hidden'); // Hide x
  }
  return false;
}

$(document).ready(function advSearchReady() {
  $('.clear-btn').click(function clearBtnClick() {
    $('input[type="text"]').val('');
    $("option:selected").removeAttr("selected");
    $("#illustrated_-1").click();
  });
});
