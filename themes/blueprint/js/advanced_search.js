var nextGroupNumber = 0;
var groupSearches = new Array();

function addSearch(group, term, field)
{
    if (term  == undefined) {term  = '';}
    if (field == undefined) {field = '';}

    var newSearch = '<div class="advRow">';

    // Label
    newSearch += '<div class="label"><label ';
    if (groupSearches[group] > 0) {
        newSearch += 'class="hide"';
    }
    newSearch += ' for="search_lookfor' + group + '_' + groupSearches[group] + '">' + searchLabel + ':</label>&nbsp;</div>';

    // Terms
    newSearch += '<div class="terms"><input type="text" id="search_lookfor' + group + '_' + groupSearches[group] + '" name="lookfor' + group + '[]" size="50" value="' + jsEntityEncode(term) + '"/></div>';

    // Field
    newSearch += '<div class="field"><label for="search_type' + group + '_' + groupSearches[group] + '">' + searchFieldLabel + '</label> ';
    newSearch += '<select id="search_type' + group + '_' + groupSearches[group] + '" name="type' + group + '[]">';
    for (key in searchFields) {
        newSearch += '<option value="' + key + '"';
        if (key == field) {
            newSearch += ' selected="selected"';
        }
        newSearch += ">" + searchFields[key] + "</option>";
    }
    newSearch += '</select>';
    newSearch += '</div>';

    // Handle floating nonsense
    newSearch += '<span class="clearer"></span>';
    newSearch += '</div>';

    // Done
    $("#group" + group + "SearchHolder").append(newSearch);

    // Actual value doesn't matter once it's not zero.
    groupSearches[group]++;
}

function addGroup(firstTerm, firstField, join)
{
    if (firstTerm  == undefined) {firstTerm  = '';}
    if (firstField == undefined) {firstField = '';}
    if (join       == undefined) {join       = '';}

    var newGroup = '<div id="group' + nextGroupNumber + '" class="group group' + (nextGroupNumber % 2) + '">';
    newGroup += '<div class="groupSearchDetails">';

    // Boolean operator drop-down
    newGroup += '<div class="join"><label for="search_bool' + nextGroupNumber + '">' + searchMatch + ':</label> ';
    newGroup += '<select id="search_bool' + nextGroupNumber + '" name="bool' + nextGroupNumber + '[]">';
    for (key in searchJoins) {
        newGroup += '<option value="' + key + '"';
        if (key == join) {
            newGroup += ' selected="selected"';
        }
        newGroup += '>' + searchJoins[key] + '</option>';
    }
    newGroup += '</select>';
    newGroup += '</div>';

    // Delete link
    newGroup += '<a href="#" class="delete" id="delete_link_' + nextGroupNumber + '" onclick="deleteGroupJS(this); return false;">' + deleteSearchGroupString + '</a>';
    newGroup += '</div>';

    // Holder for all the search fields
    newGroup += '<div id="group' + nextGroupNumber + 'SearchHolder" class="groupSearchHolder"></div>';

    // Add search term link
    newGroup += '<div class="addSearch"><a href="#" class="add" id="add_search_link_' + nextGroupNumber + '" onclick="addSearchJS(this); return false;">' + addSearchString + '</a></div>';

    newGroup += '</div>';

    // Set to 0 so adding searches knows
    //   which one is first.
    groupSearches[nextGroupNumber] = 0;

    // Add the new group into the page
    $("#searchHolder").append(newGroup);
    // Add the first search field
    addSearch(nextGroupNumber, firstTerm, firstField);
    // Keep the page in order
    reSortGroups();

    // Pass back the number of this group
    return nextGroupNumber - 1;
}

function deleteGroup(group)
{
    // Find the group and remove it
    $("#group" + group).remove();
    // And keep the page in order
    reSortGroups();
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

function reSortGroups()
{
    // Loop through all groups
    var groups = 0;
    $("#searchHolder > .group").each(function() {
        // If the number of this group doesn't
        //   match our running count
        if ($(this).attr("id") != "group"+groups) {
            // Re-number this group
            reNumGroup(this, groups);
        }
        groups++;
    });
    nextGroupNumber = groups;

    // Hide some group-related controls if there is only one group:
    if (nextGroupNumber == 1) {
        $("#groupJoin").hide();
        $("#delete_link_0").hide();
    } else {
        $("#groupJoin").show();
        $("#delete_link_0").show();
    }

    // If the last group was removed, add an empty group
    if (nextGroupNumber == 0) {
        addGroup();
    }
}

function reNumGroup(oldGroup, newNum)
{
    // Keep the old details for use
    var oldId  = $(oldGroup).attr("id");
    var oldNum = oldId.substring(5, oldId.length);

    // Which alternating row we're on
    var alt = newNum % 2;

    // Make sure the function was called correctly
    if (oldNum != newNum) {
        // Update the delete link with the new ID
        $("#delete_link_" + oldNum).attr("id", "delete_link_" + newNum);

        // Update the bool[] parameter number
        $(oldGroup).find("[name='bool" + oldNum + "[]']:first").attr("name", "bool" + newNum + "[]");

        // Update the add term link with the new ID
        $("#add_search_link_" + oldNum).attr("id", "add_search_link_" + newNum);

        // Now loop through and update all lookfor[] and type[] parameters
        $("#group"+ oldNum + "SearchHolder").find("[name='lookfor" + oldNum + "[]']").each(function() {
            $(this).attr("name", "lookfor" + newNum + "[]");
        });
        $("#group"+ oldNum + "SearchHolder").find("[name='type" + oldNum + "[]']").each(function() {
            $(this).attr("name", "type" + newNum + "[]");
        });

        // Update search holder ID
        $("#group"+ oldNum + "SearchHolder").attr("id", "group" + newNum + "SearchHolder");

        // Finally, re-number the group itself
        $(oldGroup).attr("id", "group" + newNum).attr("class", "group group" + alt);
    }
}

function jsEntityEncode(str)
{
    var new_str = str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    return new_str;
}