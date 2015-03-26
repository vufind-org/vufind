
/*global hierarchySettings, html_entity_decode, jqEscape, path, vufindString*/


var hierarchyID, recordID, htmlID, hierarchyContext;
var baseTreeSearchFullURL;

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

function changeNoResultLabel(display)
{
  if (display) {
    $("#treeSearchNoResults").removeClass('hidden');
  } else {
    $("#treeSearchNoResults").addClass('hidden');
  }
}

function changeLimitReachedLabel(display)
{
  if (display) {
    $("#treeSearchLimitReached").removeClass('hidden');
  } else {
    $("#treeSearchLimitReached").addClass('hidden');
  }
}

function htmlEncodeId(id)
{
  return id.replace(/\W/g, "-"); // Also change Hierarchy/TreeRenderer/JSTree.php
}

var searchAjax = false;
function doTreeSearch()
{
  $('#treeSearchLoadingImg').removeClass('hidden');
  var keyword = $("#treeSearchText").val();
  var type = $("#treeSearchType").val();
  if(keyword.length == 0) {
    $('#hierarchyTree').find('.jstree-search').removeClass('jstree-search');
    var tree = $('#hierarchyTree').jstree(true);
    tree.close_all();
    tree._open_to(htmlID);
    $('#treeSearchLoadingImg').addClass('hidden');
  } else {
    if(searchAjax) {
      searchAjax.abort();
    }
    searchAjax = $.ajax({
      "url" : path + '/Hierarchy/SearchTree?' + $.param({
        'lookfor': keyword,
        'hierarchyID': hierarchyID,
        'type': $("#treeSearchType").val()
      }) + "&format=true",
      'success': function(data) {
        if(data.results.length > 0) {
          $('#hierarchyTree').find('.jstree-search').removeClass('jstree-search');
          var tree = $('#hierarchyTree').jstree(true);
          tree.close_all();
          for(var i=data.results.length;i--;) {
            var id = htmlEncodeId(data.results[i]);
            tree._open_to(id);
          }
          for(i=data.results.length;i--;) {
            var tid = htmlEncodeId(data.results[i]);
            $('#hierarchyTree').find('#'+tid).addClass('jstree-search');
          }
          changeNoResultLabel(false);
          changeLimitReachedLabel(data.limitReached);
        } else {
          changeNoResultLabel(true);
        }
        $('#treeSearchLoadingImg').addClass('hidden');
      }
    });
  }
}

function buildJSONNodes(xml)
{
  var jsonNode = [];
  $(xml).children('item').each(function() {
    var content = $(this).children('content');
    var id = content.children("name[class='JSTreeID']");
    var name = content.children('name[href]');
    jsonNode.push({
      'id': htmlEncodeId(id.text()),
      'text': name.text(),
      'li_attr': {
        'recordid': id.text()
      },
      'a_attr': {
        'href': name.attr('href'),
        'title': name.text()
      },
      'type': name.attr('href').match(/\/Collection\//) ? 'collection' : 'record',
      children: buildJSONNodes(this)
    });
  });
  return jsonNode;
}
function buildTreeWithXml(cb)
{
  $.ajax({'url': path + '/Hierarchy/GetTree',
    'data': {
      'hierarchyID': hierarchyID,
      'id': recordID,
      'context': hierarchyContext,
      'mode': 'Tree'
    },
    'success': function(xml) {
      var nodes = buildJSONNodes($(xml).find('root'));
      cb.call(this, nodes);
    }
  });
}

$(document).ready(function()
{
  // Code for the search button
  hierarchyID = $("#hierarchyTree").find(".hiddenHierarchyId")[0].value;
  recordID = $("#hierarchyTree").find(".hiddenRecordId")[0].value;
  htmlID = htmlEncodeId(recordID);
  hierarchyContext = $("#hierarchyTree").find(".hiddenContext")[0].value;

  $("#hierarchyLoading").removeClass('hide');  

  $("#hierarchyTree")
    .bind("ready.jstree", function (event, data) {
      $("#hierarchyLoading").addClass('hide');  
      var tree = $("#hierarchyTree").jstree(true);
      tree.select_node(htmlID);
      tree._open_to(htmlID);

      if (hierarchyContext == "Collection") {
        getRecord(recordID);
      }

      $("#hierarchyTree").bind('select_node.jstree', function(e, data) {
        if (hierarchyContext == "Record") {
          window.location.href = data.node.a_attr.href;
        } else {
          getRecord(data.node.li_attr.recordid);
        }
      });

      // Scroll to the current record
      if ($('#hierarchyTree').parents('#modal').length > 0) {
        var hTree = $('#hierarchyTree');
        var offsetTop = hTree.offset().top;
        var maxHeight = Math.max($(window).height() - 200, 200);
        hTree.css('max-height', maxHeight + 'px').css('overflow', 'auto');
        hTree.animate({
          scrollTop: $('.jstree-clicked').offset().top - offsetTop + hTree.scrollTop() - 50
        }, 1500);
      } else {
        $('html,body').animate({
          scrollTop: $('.jstree-clicked').offset().top - 50
        }, 1500);
      }
    })
    .jstree({
      'plugins': ['search','types'],
      'core' : {
        'data' : function (obj, cb) {
          $.ajax({
            'url': path + '/Hierarchy/GetTreeJSON',
            'data': {
              'hierarchyID': hierarchyID,
              'id': recordID
            },
            'statusCode': {
              200: function(json, status, request) {
                cb.call(this, json);
              },
              204: function(json, status, request) { // No Content
                buildTreeWithXml(cb);
              },
              503: function(json, status, request) { // Service Unavailable
                buildTreeWithXml(cb);
              }
            }
          });
        }
      },
      'types' : {
        'record': {
          'icon':'fa fa-file-o'
        },
        'collection': {
          'icon':'fa fa-folder'
        }
      }
    });

  $('#treeSearch').removeClass('hidden');
  $('#treeSearch [type=submit]').click(doTreeSearch);
  $('#treeSearchText').keyup(function (e) {
    var code = (e.keyCode ? e.keyCode : e.which);
    if(code == 13 || $(this).val().length == 0) {
      doTreeSearch();
    }
  });
});