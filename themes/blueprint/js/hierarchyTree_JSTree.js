var hierarchyID;
$(document).ready(function() {

    hierarchyID = $("#hierarchyTree").find(".hiddenHierarchyId")[0].value;
    var recordID = $("#hierarchyTree").find(".hiddenRecordId")[0].value;
    var scroller = hierarchySettings.lightboxMode ? '#modalDialog' : '#hierarchyTree';
    var context = $("#hierarchyTree").find(".hiddenContext")[0].value;

    if (!hierarchySettings.fullHierarchy) {
        // Set Up Partial Hierarchy View Toggle
        $('#hierarchyTree').parent().prepend('<a href="#" id="toggleTree" class="closed">' + vufindString.showTree + '</a>');
        $('#toggleTree').click(function(e) {
            e.preventDefault();
            $(this).toggleClass("open");
            $(this).hasClass("open") ? scroll(scroller, "show") : scroll(scroller, "hide");
            $("#hierarchyTree").jstree("toggle_dots");
        });
    }

    $("#hierarchyTree")
    .bind("loaded.jstree", function (event, data) {
        var idList = $('#hierarchyTree .JSTreeID');
        $(idList).each(function() {
            var id = $.trim($(this).text());
            $(this).before('<input type="hidden" class="jsTreeID '+context+ '" value="'+id+'" />');
            $(this).remove();
        });

        $("#hierarchyTree a").click(function(e) {
            e.preventDefault();
            if (context == "Record") {
                window.location = $(this).attr("href");
            }
            if ($('#toggleTree').length > 0 && !$('#toggleTree').hasClass("open")) {
                hideFullHierarchy($(this).parent());
            }
        });

        var jsTreeNode = $(".jsTreeID:input[value='"+recordID+"']").parent();
        // Open Nodes to Current Path
        jsTreeNode.parents("li").removeClass("jstree-closed").addClass("jstree-open");
        // Initially Open Current node too
        jsTreeNode.removeClass("jstree-closed").addClass("jstree-open");
        // Add clicked class
        $("> a", jsTreeNode).addClass("jstree-clicked");
        // Add highlight class to parents
        jsTreeNode.parents("li").children("a").addClass("jstree-highlight");

        if (!hierarchySettings.fullHierarchy) {
            // Initial hide of nodes outside current path
            hideFullHierarchy(jsTreeNode);
            $("#hierarchyTree").jstree("toggle_dots");
        }
        // Scroll to the current record
        $(scroller).delay(250).animate({
            scrollTop: jsTreeNode.offset().top - $(scroller).offset().top + $(scroller).scrollTop()
        });
    })
    .jstree({
        "xml_data" : {
            "ajax" : {
                "url" : path + '/Hierarchy/GetTree?' + $.param({'hierarchyID': hierarchyID, 'id': recordID, 'context': context, mode: "Tree"}),
                success: function(data) {
                    // Necessary as data is a string
                    var dataAsXML = parseXml(data);
                    if(dataAsXML) {
                        var error = $(dataAsXML).find("error");
                        if (error.length > 0) {
                            showTreeError($(error).text());
                            return false;
                        } else {
                            return data;
                        }
                    } else {
                        showTreeError("Unable to Parse XML");
                    }
                },
                failure: function() {
                    showTreeError("Unable to Load Tree");
                }
            },
            "xsl" : "nest"
        },
        "plugins" : [ "themes", "xml_data", "ui" ],
        "themes" : {
            "url": path + '/themes/blueprint/js/jsTree/themes/vufind/style.css'
        }
    }).bind("open_node.jstree close_node.jstree", function (e, data) {
        $(data.args[0]).find("li").show();
    });
});

// Parse XML (Feature not available in JQuery 1.4)
if (typeof window.DOMParser != "undefined") {
    parseXml = function(xmlStr) {
        return ( new window.DOMParser() ).parseFromString(xmlStr, "text/xml");
    };
} else if (typeof window.ActiveXObject != "undefined" &&
    new window.ActiveXObject("Microsoft.XMLDOM")) {
    parseXml = function(xmlStr) {
        var xmlDoc = new window.ActiveXObject("Microsoft.XMLDOM");
        xmlDoc.async = "false";
        xmlDoc.loadXML(xmlStr);
        return xmlDoc;
    };
} else {
    parseXml = function(xmlStr) {
        return false;
    };
}

function showTreeError(msg) {
    $("#hierarchyTreeHolder").html('<p class="error">' + msg + '</p>');
}

function scroll(scroller, mode) {
    // Get the currently cicked item
    var jsTreeNode = $(".jstree-clicked").parent('li');
    // Toggle display of closed nodes
    $('#hierarchyTree li.jstree-closed').toggle();
    if (mode == "show") {
        $('#hierarchyTree li').show();
        $(scroller).animate({
            scrollTop: -$(scroller).scrollTop()
        });
        $('#toggleTree').html(vufindString.hideTree);
    } else {
        hideFullHierarchy(jsTreeNode);
        $(scroller).animate({
            scrollTop: $(jsTreeNode).offset().top - $(scroller).offset().top + $(scroller).scrollTop()
        });
        $('#toggleTree').html(vufindString.showTree);
    }
}

function hideFullHierarchy(jsTreeNode) {
    // Hide all nodes
    $('#hierarchyTree li').hide();
    // Show the nodes on the current path
    $(jsTreeNode).show().parents().show();
    // Show the nodes below the current path
    $(jsTreeNode).find("li").show();
}
