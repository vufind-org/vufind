/*global htmlEncode, path */
function buildFacetNodes(data, currentPath, allowExclude, excludeTitle, counts)
{
  var json = [];
  
  $(data).each(function() {
    var html = '';
    if (!this.isApplied && counts) {
      html = '<span class="badge" style="float: right">' + this.count;
      if (allowExclude) {
        var excludeURL = currentPath + this.exclude;
        excludeURL.replace("'", "\\'");
        // Just to be safe
        html += ' <a href="' + excludeURL + '" onclick="document.location.href=\'' + excludeURL + '\'; return false;" title="' + htmlEncode(excludeTitle) + '"><i class="fa fa-times"></i></a>';
      }
      html += '</span>'; 
    }
    
    var url = currentPath + this.href;
    // Just to be safe
    url.replace("'", "\\'");
    html += '<span class="main' + (this.isApplied ? ' applied' : '') + '" title="' + htmlEncode(this.displayText) + '"'
      + ' onclick="document.location.href=\'' + url + '\'; return false;">';
    if (this.operator == 'OR') {
      if (this.isApplied) {
        html += '<i class="fa fa-check-square-o"></i>';  
      } else {
        html += '<i class="fa fa-square-o"></i>';  
      }
    } else if (this.isApplied) {
      html += '<i class="fa fa-check pull-right"></i>';  
    }
    html += ' ' + this.displayText;
    html += '</span>';

    var children = null;
    if (typeof this.children !== 'undefined' && this.children.length > 0) {
      children = buildFacetNodes(this.children, currentPath, allowExclude, excludeTitle, counts);
    }
    json.push({
      'text': html,
      'children': children,
      'applied': this.isApplied,
      'state': {
        'opened': this.hasAppliedChildren
      },
      'li_attr': this.isApplied ? { 'class': 'active' } : {}
    });
  });
  
  return json;
}

function initFacetTree(treeNode, inSidebar)
{
  var loaded = treeNode.data('loaded');
  if (loaded) {
    return;
  }
  treeNode.data('loaded', true);
  
  var facet = treeNode.data('facet');
  var operator = treeNode.data('operator');
  var currentPath = treeNode.data('path');
  var allowExclude = treeNode.data('exclude');
  var excludeTitle = treeNode.data('exclude-title');
  var sort = treeNode.data('sort');
  var query = window.location.href.split('?')[1];

  if (inSidebar) {
    treeNode.prepend('<li class="list-group-item"><i class="fa fa-spinner fa-spin"></i></li>');
  } else {
    treeNode.prepend('<div><i class="fa fa-spinner fa-spin"></i><div>');  
  }
  $.getJSON(path + '/AJAX/JSON?' + query,
    {
      method: "getFacetData",
      facetName: facet,
      facetSort: sort,
      facetOperator: operator 
    },
    function(response, textStatus) {
      if (response.status == "OK") {
        var results = buildFacetNodes(response.data, currentPath, allowExclude, excludeTitle, inSidebar);
        treeNode.find('.fa-spinner').parent().remove();
        if (inSidebar) {
          treeNode.on('loaded.jstree open_node.jstree', function (e, data) {
            treeNode.find('ul.jstree-container-ul > li.jstree-node').addClass('list-group-item');
          });
        }
        treeNode.jstree({
          'core': {
            'data': results
          }
        });
      } 
    }
  );
}
