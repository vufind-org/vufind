var nextGroup = 0;

function addSearch(group, fieldValues)
{
  if(typeof fieldValues === "undefined") {
    fieldValues = {};
  }
  // Build the new search
  var inputIndex = $('#group'+group+' input[type=text]').length;
  var inputID = group+'_'+inputIndex;
  var $newSearch = $($('#new_search_template').html());

  $newSearch.attr('id', 'search'+inputID);
  $newSearch.find('input.form-control')
    .attr('id', 'search_lookfor'+inputID)
    .attr('name', 'lookfor'+group+'[]');
  $newSearch.find('select.type')
    .attr('id', 'search_type'+inputID)
    .attr('name', 'type'+group+'[]');
  $newSearch.find('a.delete')
    .attr('onClick', 'deleteSearch('+group+','+inputIndex+')');
  // Preset Values
  if(typeof fieldValues.term !== "undefined") {
    $newSearch.find('input.form-control').attr('value', fieldValues.term);
  }
  if(typeof fieldValues.field !== "undefined") {
    $newSearch.find('select.type option[value="'+fieldValues.field+'"]').attr('selected', 1);
  }
  if (typeof fieldValues.op !== "undefined") {
    $newSearch.find('select.op option[value="'+fieldValues.op+'"]').attr('selected', 1);
  }
  // Insert it
  $("#group" + group + "Holder").before($newSearch);
  // Individual search ops
  if (inputIndex == 0) {
    $newSearch.find('.first-op').attr('name', 'op' + group + '[]');
    $newSearch.find('select.op').remove();
  } else {
    $newSearch.find('select.op')
      .attr('id', 'search_op' + group + '_' + inputIndex)
      .attr('name', 'op' + group + '[]');
    $newSearch.find('.first-op').remove();
    $newSearch.find('label').remove();
    // Show x if we have more than one search inputs
    $('#group'+group+' .search .delete').removeClass('hidden');
  }
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
  if($('#group'+group+' .search').length > 1) {
    $('#group'+group+' .search:last').remove();
  }
  // Hide x
  if($('#group'+group+' .search').length == 1) {
    $('#group'+group+' .search .delete').addClass('hidden');
  }
}

function addGroup(firstTerm, firstField, join)
{
  if (firstTerm  == undefined) {firstTerm  = '';}
  if (firstField == undefined) {firstField = '';}
  if (join       == undefined) {join       = '';}

  var $newGroup = $($('#new_group_template').html());
  $newGroup.attr('id', 'group'+nextGroup);
  $newGroup.find('.fa.fa-plus-circle')
    .attr('id', 'group'+nextGroup+'Holder')
    .removeClass('hidden');
  $newGroup.find('.add_search_link')
    .attr('id', 'add_search_link_'+nextGroup)
    .attr('onClick', 'addSearch('+nextGroup+')')
    .removeClass('hidden');
  $newGroup.find('.close')
    .attr('onClick', 'deleteGroup('+nextGroup+')');
  $newGroup.find('select.form-control')
    .attr('id', 'search_bool'+nextGroup)
    .attr('name', 'bool'+nextGroup+'[]');
  $newGroup.find('.search_bool')
    .attr('for', 'search_bool'+nextGroup);
  if(join.length > 0) {
    $newGroup.find('option[value="'+join+'"]').attr('selected', 1);
  }
  // Insert
  $('#groupPlaceHolder').before($newGroup);
  // Populate
  addSearch(nextGroup, {term:firstTerm, field:firstField});
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
    $('.group .close').addClass('hidden');
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