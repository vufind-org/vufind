/*global VuFind */
/*exported addGroup, addSearch, deleteGroup, deleteSearch */

var nextGroup = 0;
var groupLength = [];
var deleteGroup, deleteSearch;

function addSearch(group, _fieldValues, isUser = false) {
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
    .data('group', group)
    .data('groupLength', groupLength[group])
    .on("click", function deleteSearchHandler() {
      return deleteSearch($(this).data('group'), $(this).data('groupLength'));
    });
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

  if (isUser) {
    $newSearch.find('input.form-control').trigger("focus");
  }

  return false;
}

deleteSearch = function _deleteSearch(group, sindex) {
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
    var toRemove = $('#search' + group + '_' + groupLength[group]);
    var parent = toRemove.parent();
    toRemove.remove();
    if (parent.length) {
      parent.find('.adv-search input.form-control').focus();
    }
    if (groupLength[group] === 1) {
      $('#group' + group + ' .adv-term-remove').addClass('hidden'); // Hide x
    }
  }
  return false;
};

function _renumberGroupLinkLabels() {
  $('.adv-group-close').each(function deleteGroupLinkLabel(i, link) {
    $(link).attr(
      'aria-label',
      VuFind.translate('del_search_num', { '%%num%%': i + 1 })
    );
  });
}

function addGroup(_firstTerm, _firstField, _join, isUser = false) {
  var firstTerm = _firstTerm || '';
  var firstField = _firstField || '';
  var join = _join || '';

  var $newGroup = $($('#new_group_template').html());
  $newGroup.find('.adv-group-label') // update label
    .attr('for', 'search_lookfor' + nextGroup + '_0');
  $newGroup.attr('id', 'group' + nextGroup);
  $newGroup.find('.search_place_holder')
    .attr('id', 'group' + nextGroup + 'Holder')
    .removeClass('hidden');
  $newGroup.find('.add_search_link')
    .attr('id', 'add_search_link_' + nextGroup)
    .data('nextGroup', nextGroup)
    .on("click", function addSearchHandler() {
      return addSearch($(this).data('nextGroup'), {}, true);
    })
    .removeClass('hidden');
  $newGroup.find('.adv-group-close')
    .data('nextGroup', nextGroup)
    .on("click", function deleteGroupHandler() {
      return deleteGroup($(this).data('nextGroup'));
    });
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
  _renumberGroupLinkLabels();

  // Populate
  groupLength[nextGroup] = 0;
  addSearch(nextGroup, {term: firstTerm, field: firstField}, isUser);
  // Show join menu
  if (nextGroup > 0) {
    $('#groupJoin').removeClass('hidden');
    // Show x
    $('.adv-group-close').removeClass('hidden');
  }

  $newGroup.children('input.form-control').first().trigger("focus");

  return nextGroup++;
}

deleteGroup = function _deleteGroup(group) {
  // Find the group and remove it
  $("#group" + group).remove();
  _renumberGroupLinkLabels();

  // If the last group was removed, add an empty group
  if ($('.adv-group').length === 0) {
    addGroup();
  } else if ($('#advSearchForm .adv-group').length === 1) {
    $('#groupJoin').addClass('hidden'); // Hide join menu
    $('.adv-group .adv-group-close').addClass('hidden'); // Hide x
  }
  return false;
};

$(function advSearchReady() {
  $('.clear-btn').on("click", function clearBtnClick() {
    $('input[type="text"]').val('');
    $('input[type="checkbox"],input[type="radio"]').each(function onEachCheckbox() {
      var checked = $(this).data('checked-by-default');
      checked = (checked == null) ? false : checked;
      $(this).prop("checked", checked);
    });
    $("option:selected").prop("selected", false);
  });
});
