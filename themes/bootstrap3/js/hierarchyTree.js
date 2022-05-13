/*global VuFind */

var hierarchyID, recordID, hierarchySource, htmlID, hierarchyContext, hierarchySettings;

/* Utility functions */
function htmlEncodeId(id) {
  return id.replace(/\W/g, "-"); // Also change Hierarchy/TreeRenderer/JSTree.php
}
function html_entity_decode(string) {
  var hash_map = {
    '&': '&amp;',
    '>': '&gt;',
    '<': '&lt;'
  };
  var tmp_str = string.toString();

  for (var symbol in hash_map) {
    if (Object.prototype.hasOwnProperty.call(hash_map, symbol)) {
      var entity = hash_map[symbol];
      tmp_str = tmp_str.split(entity).join(symbol);
    }
  }
  tmp_str = tmp_str.split('&#039;').join("'");

  return tmp_str;
}

function getRecord(id) {
  $.ajax({
    url: VuFind.path + '/Hierarchy/GetRecord?' + $.param({id: id, hierarchySource: hierarchySource}),
    dataType: 'html'
  })
    .done(function getRecordDone(response) {
      $('#tree-preview').html(html_entity_decode(response));
      // Remove the old path highlighting
      $('#hierarchyTree a').removeClass("jstree-highlight");
      // Add Current path highlighting
      var jsTreeNode = $(":input[value='" + id + "']").parent();
      jsTreeNode.children("a").addClass("jstree-highlight");
      jsTreeNode.parents("li").children("a").addClass("jstree-highlight");
    });
}

function changeNoResultLabel(display) {
  if (display) {
    $("#treeSearchNoResults").removeClass('hidden');
  } else {
    $("#treeSearchNoResults").addClass('hidden');
  }
}

function changeLimitReachedLabel(display) {
  if (display) {
    $("#treeSearchLimitReached").removeClass('hidden');
  } else {
    $("#treeSearchLimitReached").addClass('hidden');
  }
}

var searchAjax = false;
function doTreeSearch() {
  $('#treeSearchLoadingImg').removeClass('hidden');
  var keyword = $("#treeSearchText").val();
  if (keyword.length === 0) {
    $('#hierarchyTree').find('.jstree-search').removeClass('jstree-search');
    var tree = $('#hierarchyTree').jstree(true);
    tree.close_all();
    tree._open_to(htmlID);
    $('#treeSearchLoadingImg').addClass('hidden');
  } else {
    if (searchAjax) {
      searchAjax.abort();
    }
    searchAjax = $.ajax({
      url: VuFind.path + '/Hierarchy/SearchTree?' + $.param({
        lookfor: keyword,
        hierarchyID: hierarchyID,
        hierarchySource: hierarchySource,
        type: $("#treeSearchType").val()
      }) + "&format=true"
    })
      .done(function searchTreeAjaxDone(data) {
        if (data.results.length > 0) {
          $('#hierarchyTree').find('.jstree-search').removeClass('jstree-search');
          var jstree = $('#hierarchyTree').jstree(true);
          jstree.close_all();
          for (var i = data.results.length; i--;) {
            var id = htmlEncodeId(data.results[i]);
            jstree._open_to(id);
          }
          for (var j = data.results.length; j--;) {
            var tid = htmlEncodeId(data.results[j]);
            $('#hierarchyTree').find('#' + tid).addClass('jstree-search');
          }
          changeNoResultLabel(false);
          changeLimitReachedLabel(data.limitReached);
        } else {
          changeNoResultLabel(true);
        }
        $('#treeSearchLoadingImg').addClass('hidden');
      });
  }
}

function scrollToClicked() {
  // Scroll to the current record
  var hTree = $('#hierarchyTree');
  hTree.animate({
    scrollTop: $('.jstree-clicked').offset().top - hTree.offset().top + hTree.scrollTop() - 50
  }, 1000);
}

function hideFullHierarchy() {
  var $selected = $('.jstree-clicked');
  // Hide all nodes
  $('#hierarchyTree li').hide();
  // Show the nodes on the current path
  $selected.show().parents().show();
  // Show the nodes below the current path
  $selected.find("li").show();
}

function buildJSONNodes(xml) {
  var jsonNode = [];
  $(xml).children('item').each(function xmlTreeChildren() {
    var content = $(this).children('content');
    var id = content.children("name[class='JSTreeID']");
    var name = content.children('name[href]');
    jsonNode.push({
      id: htmlEncodeId(id.text()),
      text: name.text(),
      li_attr: { recordid: id.text() },
      a_attr: {
        href: name.attr('href'),
        title: name.text()
      },
      type: name.attr('href').match(/\/Collection\//) ? 'collection' : 'record',
      children: buildJSONNodes(this)
    });
  });
  return jsonNode;
}

function buildTreeWithXml(cb) {
  $.ajax({
    url: VuFind.path + '/Hierarchy/GetTree',
    data: {
      hierarchyID: hierarchyID,
      id: recordID,
      context: hierarchyContext,
      hierarchySource: hierarchySource,
      mode: 'Tree'
    }
  })
    .done(function getTreeDone(xml) {
      var nodes = buildJSONNodes($(xml).find('root'));
      cb.call(this, nodes);
    });
}

$(document).ready(function hierarchyTreeReady() {
  // Code for the search button
  hierarchyID = $("#hierarchyTree").find(".hiddenHierarchyId")[0].value;
  recordID = $("#hierarchyTree").find(".hiddenRecordId")[0].value;
  hierarchySource = $("#hierarchyTree").find(".hiddenHierarchySource");
  hierarchySource = hierarchySource.length ? hierarchySource[0].value : 'Solr';

  htmlID = htmlEncodeId(recordID);
  hierarchyContext = $("#hierarchyTree").find(".hiddenContext")[0].value;
  var inLightbox = $("#hierarchyTree").parents("#modal").length > 0;

  if (!hierarchySettings.fullHierarchy) {
    // Set Up Partial Hierarchy View Toggle
    $('#hierarchyTree').parent().prepend('<a href="#" id="toggleTree" class="closed">' + VuFind.translate("showTree") + '</a>');
    $('#toggleTree').click(function toggleFullTree(e) {
      e.preventDefault();
      $(this).toggleClass("open");
      if ($(this).hasClass("open")) {
        $(this).html(VuFind.translate("hideTree"));
        $('#hierarchyTree li').show();
      } else {
        $(this).html(VuFind.translate("showTree"));
        hideFullHierarchy();
      }
      scrollToClicked();
      $("#hierarchyTree").jstree("toggle_dots");
    });
  }

  $("#hierarchyLoading").removeClass('hide');

  $("#hierarchyTree")
    .bind("ready.jstree", function jsTreeReady(/*event, data*/) {
      $("#hierarchyLoading").addClass('hide');
      var tree = $("#hierarchyTree").jstree(true);
      tree.select_node(htmlID);
      tree._open_to(htmlID);

      if (!inLightbox && hierarchyContext === "Collection") {
        getRecord(recordID);
      }

      $("#hierarchyTree").bind('select_node.jstree', function jsTreeSelect(e, resp) {
        if (inLightbox || hierarchyContext === "Record") {
          window.location.href = resp.node.a_attr.href;
        } else {
          getRecord(resp.node.li_attr.recordid);
        }
      });

      if (!hierarchySettings.fullHierarchy) {
        // Initial hide of nodes outside current path
        hideFullHierarchy();
        $("#hierarchyTree").jstree("toggle_dots");
      }

      scrollToClicked();
    })
    .jstree({
      plugins: ['search', 'types'],
      core: {
        data: function jsTreeCoreData(obj, cb) {
          $.ajax({
            url: VuFind.path + '/Hierarchy/GetTreeJSON',
            data: {
              hierarchyID: hierarchyID,
              id: recordID,
              hierarchySource: hierarchySource
            },
            statusCode: {
              200: function jsTree200Status(json /*, status, request*/) {
                cb.call(this, json);
              },
              204: function jsTree204Status(/*json, status, request*/) { // No Content
                buildTreeWithXml(cb);
              },
              503: function jsTree503Status(/*json, status, request*/) { // Service Unavailable
                buildTreeWithXml(cb);
              }
            }
          });
        }
      },
      types: {
        record: {
          icon: VuFind.path + "/themes/bootstrap3/images/hierarchy-file.svg"
        },
        collection: {
          icon: VuFind.path + "/themes/bootstrap3/images/hierarchy-folder.svg"
        }
      }
    });

  $('#treeSearch').removeClass('hidden');
  $('#treeSearch [type=submit]').click(doTreeSearch);
  $('#treeSearchText').keyup(function treeSearchKeyup(e) {
    var code = (e.keyCode ? e.keyCode : e.which);
    if (code === 13 || $(this).val().length === 0) {
      doTreeSearch();
    }
  });
});
