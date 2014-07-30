/*global addSearchString, searchFields, searchFieldLabel, searchLabel, searchMatch */

var nextGroup = 0;
var groupSearches = [];
var booleanSearchOperators = ["AND", "OR", "NOT"];

function addSearch(group, term, field, op)
{
  // Does anyone use this???
  if (typeof term  == "undefined") {term  = '';}
  if (typeof field == "undefined") {field = '';}
  if (typeof op    == "undefined") {op = 'AND';}

  // Build the new search
  var inputIndex = $('#group'+group+' input').length;
  var inputID = group+'_'+$('#group'+group+' input').length;
  var newSearch ='<div class="search" id="search'+inputID+'"><div class="span3 text-right">';
  if (typeof groupSearches[group] == "undefined") {
    groupSearches[group] = 0;
    newSearch += '<input type="hidden" name="op' + group + '[]" value="AND"/><label for="search_lookfor' + group + '_' + groupSearches[group] + '"><span class="help-inline">' + searchLabel + ':</span></label>';
  } else {
    newSearch += '<select id="search_op' + group + '_' + groupSearches[group] + '" name="op' + group + '[]" class="span9">';
    for(var i=0, len= booleanSearchOperators.length; i < len; i++) {
      var searchOp = booleanSearchOperators[i];
      var sel = '';
      if(op == searchOp) {
        sel = ' selected=selected ';
      }
      newSearch += '<option value="' + searchOp + '" ' + sel + ">" + searchOp +"</option>";
    }
    newSearch += '</select>';
  }
  newSearch += '</div><div class="span9"><input class="span7" id="search_lookfor'+inputID+'" type="text" name="lookfor'+group+'[]" value="'+term+'">'
    + '<span class="help-inline">'+searchFieldLabel+'</span> '
    + '<select class="span4" id="search_type'+inputID+'" name="type'+group+'[]">';
  for (var key in searchFields) {
    newSearch += '<option value="' + key + '"';
    if (key == field) {
      newSearch += ' selected="selected"';
    }
    newSearch += ">" + searchFields[key] + "</option>";
  }
  newSearch += '</select> <a href="#" onClick="deleteSearch('+group+','+inputIndex+')" class="help-inline delete">&times;</a></div>';

  // Insert it
  $("#group" + group + "Holder").before(newSearch);
  // Show x
  $('#group'+group+' .search .delete').show();
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
    + '<div class="span11"><div id="group'+nextGroup+'Holder" class="span9 offset3"><i class="icon-plus-sign"></i> <a href="#" onClick="addSearch('+nextGroup+')">'+addSearchString+'</a></div></div>'
    + '<div class="span1"><a href="#" onClick="deleteGroup('+nextGroup+')" class="close hide" title="'+deleteSearchGroupString+'">&times;</a></div></div>';

  $('#groupPlaceHolder').before(newGroup);
  addSearch(nextGroup, firstTerm, firstField);
  // Show join menu
  if($('.group').length > 1) {
    $('#groupJoin').removeClass('hidden');
    // Show x
    $('.group .close').show();
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