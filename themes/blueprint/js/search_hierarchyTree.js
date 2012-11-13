$(document).ready(function() {
    $(".hierarchyTreeLink a").click(function() {
        var hierarchyID = $(this).parent().find(".hiddenHierarchyId")[0].value;
        var id = $(this).parent().parent().parent().find(".hiddenId")[0].value;
        var $dialog = getLightbox('Record', 'AjaxTab', id, null, this.title, '', '', '', {hierarchy: hierarchyID, tab: "HierarchyTree"});
        return false;
    });
});
