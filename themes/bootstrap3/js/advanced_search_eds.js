/*global addSearchString, searchFields, searchFieldLabel, searchLabel, searchMatch */

var nextGroup = 0;
var groupSearches = [];
var booleanSearchOperators = ["AND", "OR", "NOT"];

function addSearch(group, term, field, op)
{
  if (typeof term  === "undefined") {term  = '';}
  if (typeof field === "undefined") {field = '';}
  if (typeof op    === "undefined") {op = 'AND';}

  // Build the new search
  var inputIndex = $('#group'+group+' input').length;
  var inputID = group+'_'+inputIndex;
  var $newSearch = $($('#new_search_template').html());
  $newSearch.attr('id', 'search'+inputID);
  if (typeof groupSearches[group] === "undefined") {
    groupSearches[group] = 0;
    $newSearch.find('.first-join').attr('name', 'op' + group + '[]');
    $newSearch.find('select.join').remove();
  } else {
    $newSearch.find('select.join')
      .attr('id', 'search_op' + group + '_' + groupSearches[group])
      .attr('name', 'op' + group + '[]');
    $newSearch.find('.first-join').remove();
    $newSearch.find('label').remove();
  }
  $newSearch.find('input.form-control')
    .attr('id', 'search_lookfor'+inputID)
    .attr('nam', 'lookfor'+group+'[]')
    .attr('value', term);
  $newSearch.find('select.type')
    .attr('id', 'search_type'+inputID)
    .attr('name', 'type'+group+'[]');
  $newSearch.find('a.delete')
    .attr('onClick', 'deleteSearch('+group+','+inputIndex+')');
  if(field.length > 0) {
    $newSearch.find('option[value="'+field+'"]').attr('selected', 1);
  }
  // Insert it
  $("#group" + group + "Holder").before($newSearch);
  // Show x
  if(inputIndex > 0) {
    $('#group'+group+' .search .delete').removeClass('hidden');
  }
  groupSearches[group]++;
}

function deleteSearch(group, eq)
{
  var searches = $('#group'+group+' .search');
  for(var i=eq;i<searches.length-1;i++) {
    $(searches[i]).find('input').val($(searches[i+1]).find('input').val());
    var select0 = $(searches[i]).find('select')[0];
    var select1 = $(searches[i+1]).find('select')[0];
    select0.selectedIndex = select1.selectedIndex;
  }
  if(groupSearches[group] > 1) {
    $('#group'+group+' .search:last').remove();
    groupSearches[group]--;
  }
  // Hide x
  if(groupSearches[group] == 1) {
    $('#group'+group+' .search .delete').hide();
  }
}

function addGroup(firstTerm, firstField, join)
{
  if (firstTerm  == undefined) {firstTerm  = '';}
  if (firstField == undefined) {firstField = '';}
  if (join       == undefined) {join       = '';}

  var newGroup = '<div id="group'+nextGroup+'" class="group well clearfix">'
    + '<input type="hidden" name="bool'+nextGroup+'[]" value="AND"/>'
    + '<div id="group'+nextGroup+'Holder"><i class="col-sm-offset-3 fa fa-plus-circle"></i> <a href="#" onClick="addSearch('+nextGroup+')">'+addSearchString+'</a></div>';

  $('#groupPlaceHolder').before(newGroup);
  addSearch(nextGroup, firstTerm, firstField);
  // Show join menu
  if($('.group').length > 1) {
    $('#groupJoin').removeClass('hidden');
    // Show x
    $('.group .close').removeClass('hidden');
  }
  return nextGroup++;
}

function deleteGroup(group)
{
  // Find the group and remove it
  $("#group" + group).remove();
  // If the last group was removed, add an empty group
  if($('.group').length == 0) {
    addGroup();
  } else if($('.group').length == 1) { // Hide join menu
    $('#groupJoin').addClass('hidden');
    // Hide x
    $('.group .close').hide();
  }
}

// Fired by onclick event
function deleteGroupJS(group)
{
  var groupNum = group.id.replace("delete_link_", "");
  deleteGroup(groupNum);
  return false;
}

// Fired by onclick event
function addSearchJS(group)
{
  var groupNum = group.id.replace("add_search_link_", "");
  addSearch(groupNum);
  return false;
}

$(document).ready(function() {
  $('#groupPlaceHolder').hide();
});