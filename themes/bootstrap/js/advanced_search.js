/*global addSearchString, searchFields, searchLabel */

var nextGroup = 0;

function addSearch(group, term, field)
{
  // Does anyone use this???
  if (term  == undefined) {term  = '';}
  if (field == undefined) {field = '';}

  // Build the new search
  var inputIndex = $('#group'+group+' input').length;
  var inputID = group+'_'+$('#group'+group+' input').length;
  var newSearch = '<div class="search" id="search'+inputID+'"><input class="span7" id="search_lookfor'+inputID+'" type="text" name="lookfor'+group+'[]" value="'+term+'">'
    + ' in '
    + '<select class="span4" id="search_type'+inputID+'" name="type'+group+'[]">';
  for (var key in searchFields) {
    newSearch += '<option value="' + key + '"';
    if (key == field) {
      newSearch += ' selected="selected"';
    }
    newSearch += ">" + searchFields[key] + "</option>";
  }
  newSearch += '</select> <a href="#" onClick="deleteSearch('+group+','+inputIndex+')" class="help-inline" title="Remove this term">&times;</a></div>';

  // Insert it
  $("#group" + group + "Holder").before(newSearch);
  // Show x
  $('#group'+group+' .search .help-inline').show();
}

function deleteSearch(group, eq) {
  var searches = $('#group'+group+' .search');
  for(var i=eq;i<searches.length-1;i++) {
    $(searches[i]).find('input').val($(searches[i+1]).find('input').val());
    var select0 = $(searches[i]).find('select')[0];
    var select1 = $(searches[i+1]).find('select')[0];
    select0.selectedIndex = select1.selectedIndex;
  }
  if($('#group'+group+' .search').length > 1) {
    $('#group'+group+' .search:last').remove();
  }
  // Hide x
  if($('#group'+group+' .search').length == 1) {
    $('#group'+group+' .search .help-inline').hide();
  }
}

function addGroup(firstTerm, firstField, join)
{
  if (firstTerm  == undefined) {firstTerm  = '';}
  if (firstField == undefined) {firstField = '';}
  if (join       == undefined) {join       = '';}
  
  var newGroup = '<div id="group'+nextGroup+'" class="group well clearfix">'
    + '<div class="span4 pull-right">'
    + '<label for="search_bool'+nextGroup+'">Match:&nbsp;</label>'
    + '<select class="span8" id="search_bool'+nextGroup+'" name="bool'+nextGroup+'[]">'
    + '<option value="AND"';
  if(join == 'AND') {
    newGroup += ' selected';
  }
  newGroup += '>ALL Terms</option>'
    + '<option value="OR"';
  if(join == 'OR') {
    newGroup += ' selected';
  }
  newGroup += '>ANY Terms</option>'
    + '<option value="NOT"';
  if(join == 'NOT') {
    newGroup += ' selected';
  }
  newGroup += '>NO Terms</option>'
    + '</select><a href="#" onClick="deleteGroup('+nextGroup+')" class="close hide" title="Remove Group">&times;</a></div><div class="span8 pull-left switch-margins row-fluid"><div class="span3 text-right">'+searchLabel+':</div>'
    + '<div class="span9"><i id="group'+nextGroup+'Holder" class="icon-plus-sign"></i> <a href="#" onClick="addSearch('+nextGroup+')">'+addSearchString+'</a></div></div></div>';
  
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