/*global hierarchySettings, path, vufindString*/

var hierarchyID;
var baseTreeSearchFullURL;

function showTreeError(msg)
{
  $("#hierarchyTreeHolder").html('<p class="error">' + msg + '</p>');
}

function getRecord(recordID)
{
  $.ajax({
    url: path + '/Hierarchy/GetRecord?' + $.param({id: recordID}),
    dataType: 'html',
    success: function(response) {
      if (response) {
        $('#hierarchyRecord').html(html_entity_decode(response));
        // Remove the old path highlighting
        $('#hierarchyTree a').removeClass("jstree-highlight");
        // Add Current path highlighting
        var jsTreeNode = $(":input[value='"+recordID+"']").parent();
        jsTreeNode.children("a").addClass("jstree-highlight");
        jsTreeNode.parents("li").children("a").addClass("jstree-highlight");
      }
    }
  });
}

function hideFullHierarchy(jsTreeNode)
{
    // Hide all nodes
  $('#hierarchyTree li').hide();
  // Show the nodes on the current path
  $(jsTreeNode).show().parents().show();
  // Show the nodes below the current path
  $(jsTreeNode).find("li").show();
}

function toggleFullHierarchy(parentElement)
{
  // Toggle status
  $('#toggleTree').toggleClass("open");
  // Get the currently clicked item
  var jsTreeNode = $(".jstree-clicked").parent('li');
  // Toggle display of closed nodes
  $('#hierarchyTree li.jstree-closed').toggle();
  if ($('#toggleTree').hasClass("open")) {
    $('#hierarchyTree li').show();
    $("#hierarchyTree").jstree("show_dots");
    console.log(jsTreeNode);
    console.log(parentElement);
    console.log($(parentElement));
    $(parentElement).animate({
      scrollTop: $(jsTreeNode).offset().top - $(parentElement).offset().top + $(parentElement).scrollTop()
    });
    $('#toggleTree').html(vufindString.hierarchy_hide_tree);
  } else {
    hideFullHierarchy(jsTreeNode);
    $(parentElement).animate({
        scrollTop: -$(parentElement).scrollTop()
    });
    $("#hierarchyTree").jstree("hide_dots");
    $('#toggleTree').html(vufindString.hierarchy_show_tree);
  }
}

function changeNoResultLabel(display)
{
  if (display) {
    $("#treeSearchNoResults").show();
  } else {
    $("#treeSearchNoResults").hide();
  }
}

function changeLimitReachedLabel(display)
{
  if (display) {
    $("#treeSearchLimitReached").show();
  } else {
    $("#treeSearchLimitReached").hide();
  }
}

function doTreeSearch()
{
  var keyword = $("#treeSearchText").val();
  if (keyword == ""){
    changeNoResultLabel(true);
    return;
  }
  var searchType = $("#treeSearchType").val();

  $("#treeSearchLoadingImg").show();
  $.getJSON(path + '/Hierarchy/SearchTree?' + $.param({'lookfor': keyword, 'hierarchyID': hierarchyID, 'type': searchType}), function(results)
  {
    if (results["limitReached"] == true) {
      if(typeof(baseTreeSearchFullURL) == "undefined" || baseTreeSearchFullURL == null){
        baseTreeSearchFullURL = $("#fullSearchLink").attr("href");
      }
      $("#fullSearchLink").attr("href", baseTreeSearchFullURL + "?lookfor="+ keyword + "&filter[]=hierarchy_top_id:\"" + hierarchyID  + "\"");
      changeLimitReachedLabel(true);
    } else {
      changeLimitReachedLabel(false);
    }

    if (results["results"].length >= 1) {
      $("#hierarchyTree .jstree-search").removeClass("jstree-search");
      $("#hierarchyTree").jstree("close_all", hierarchyID);
      changeNoResultLabel(false);
    } else {
      $("#hierarchyTree .jstree-search").removeClass("jstree-search");
      changeNoResultLabel(true);
    }

    $.each(results["results"], function(key, val)
    {
      $("jstree-open").removeClass("jstree-open");
      var jsTreeNode = $('.jsTreeID:input[value="'+val+'"]').parent();
      if (jsTreeNode.hasClass("jstree-closed")) {
        jsTreeNode.removeClass("jstree-closed").addClass("jstree-open");
      }
      jsTreeNode.show().children('a:first').addClass("jstree-search");
      var parents = $(jsTreeNode).parents();
      parents.each(function() {
        if ($(this).hasClass("jstree-closed")) {
          $(this).removeClass("jstree-closed").addClass("jstree-open");
        }
        $(this).show();
      });
    });
    if (results["results"].length == 1) {
      $("#hierarchyTree .jstree-clicked").removeClass("jstree-clicked");
      // only do this for collection pages
      if ($(".Collection").length != 0) {
        getRecord(results["results"][0]);
      }
    }
    $("#treeSearchLoadingImg").hide();
  });
}

$(document).ready(function()
{
  // Code for the search button
  $('#treeSearch input[type="submit"]').click(doTreeSearch);

  hierarchyID = $("#hierarchyTree").find(".hiddenHierarchyId")[0].value;
  var recordID = $("#hierarchyTree").find(".hiddenRecordId")[0].value;
  var parentElement = hierarchySettings.lightboxMode ? '#modal .modal-body' : '#hierarchyTree';
  var context = $("#hierarchyTree").find(".hiddenContext")[0].value;

  if (!hierarchySettings.fullHierarchy) {
    // Set Up Partial Hierarchy View Toggle
    $('#hierarchyTree').parent().prepend('<a href="#" id="toggleTree" class="">' + vufindString.hierarchy_show_tree + '</a>');
    $('#toggleTree').click(function(e)
    {
      e.preventDefault();
      toggleFullHierarchy(parentElement);
    });
  }

  $("#hierarchyTree")
  .bind("ready.jstree", function (event, data)
  {
    $(".Collection").each(function()
    {
      var id = $(this).attr("value");
      $(this).next("a").click(function(e)
      {
        e.preventDefault();
        $("#hierarchyTree a").removeClass("jstree-clicked");
        $(this).addClass("jstree-clicked");
        // Open this node
        $(this).parent().removeClass("jstree-closed").addClass("jstree-open");
        getRecord(id);
        return false;
      });
    });

    $("#hierarchyTree a").click(function(e)
    {
      e.preventDefault();
      if (context == "Record") {
        window.location = $(this).attr("href");
      }
      if ($('#toggleTree').length > 0 && !$('#toggleTree').hasClass("open")) {
        hideFullHierarchy($(this).parent());
      }
    });
    
    var jsTreeNode = $("#hierarchyTree").jstree('select_node', recordID);
    jsTreeNode.parents('.jstree-closed').each(function () {
      data.inst.open_node(this);
    });
    
    $("#hierarchyTree").bind('select_node.jstree', function(e, data) {
      window.location.href = data.node.a_attr.href;
    })
    if (!hierarchySettings.fullHierarchy) {
      // Initial hide of nodes outside current path
      toggleFullHierarchy(parentElement);
    }
  })
  .bind("loaded.jstree", function (event, data)
  {
  })
  .jstree({
    'core' : {
      'data' : function (obj, cb) {
        $.ajax({
          'url': path + '/Hierarchy/GetTree',
          'data': {
            'hierarchyID': hierarchyID, 
            'id': recordID,
            'context': context,
            'mode': 'Tree'  
          },
          'success': function(xml) {
            var nodes = buildJSONNodes($(xml).find('root'));
            cb.call(this, nodes);
          }
        })       
      },
      'initially_open': jqEscape(recordID),
      "themes" : {
        "url": path + '/themes/bootstrap/js/jsTree/themes/default/style.css'
      },
      'strings': {
        'Loading ...': ' '
      }  
    },
  });/*.bind("activate_node.jstree", function (event, data) {
    /*data.rslt.obj.parents('.jstree-closed').each(function () {
      data.inst.open_node(this);
    });
    this.show();
    window.location.href = data.rslt.obj.find('a').attr("href");
  });/*.bind("open_node.jstree close_node.jstree", function (e, data) {
    $(data.args[0]).find("li").show();
  });*/

  $('#treeSearch').show();
  $('#treeSearchText').bind('keypress', function(e)
  {
    var code = (e.keyCode ? e.keyCode : e.which);
    if(code == 13) {
      // Enter keycode should call the search code
      doTreeSearch();
    }
  });
});

function buildJSONNodes(xml) {
  var jsonNode = [];
  
  $(xml).children('item').each(function() {
     var content = $(this).children('content');
     var id = content.children("name[class='JSTreeID']");
     var name = content.children('name[href]');
     jsonNode.push({
       'id': id.text(),
       'text': name.text(),
       'a_attr': {
         'href': name.attr('href')  
       },
       children: buildJSONNodes(this)
     });
  });
  return jsonNode;
}

function html_entity_decode(string, quote_style) {
  var hash_map = {},
    symbol = '',
    tmp_str = '',
    entity = '';
  tmp_str = string.toString();

  delete(hash_map['&']);
  hash_map['&'] = '&amp;';
  hash_map['>'] = '&gt;';
  hash_map['<'] = '&lt;';

  for (symbol in hash_map) {
    entity = hash_map[symbol];
    tmp_str = tmp_str.split(entity).join(symbol);
  }
  tmp_str = tmp_str.split('&#039;').join("'");

  return tmp_str;
}

