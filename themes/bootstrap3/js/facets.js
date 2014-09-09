function buildFacetNodes(data, currentPath, allowExclude, excludeTitle)
{
  var json = [];
  
  $(data).each(function() {
    var html = '';
    if (!this.applied) {
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
    html += '<span class="main' + (this.applied ? ' applied' : '') + '" title="' + htmlEncode(this.text) + '"'
      + ' onclick="document.location.href=\'' + url + '\'; return false;">';
    if (this.operator == 'OR') {
      if (this.applied) {
        html += '<i class="fa fa-check-square-o"></i>';  
      } else {
        html += '<i class="fa fa-square-o"></i>';  
      }
    } else if (this.applied) {
      html += '<i class="fa fa-check pull-right"></i>';  
    }
    html += ' ' + this.text;
    html += '</span>';

    var children = null;
    if (typeof this.children !== 'undefined' && this.children.length > 0) {
      children = buildFacetNodes(this.children, currentPath, allowExclude, excludeTitle);
    }
    json.push({
      'text': html,
      'children': children,
      'applied': this.applied,
      'state': {
        'opened': this.state.opened
      },
      'li_attr': this.applied ? { 'class': 'active' } : {}
    });
  });
  
  return json;
}

function initFacetTree(treeNode)
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
  var action = window.location.href.match(/.*?\/([^\/]+)\//)[1];

  treeNode.prepend('<li class="list-group-item"><i class="fa fa-spinner fa-spin"></i></li>');
  $.getJSON(path + '/AJAX/JSON?' + query,
    {
      method: "getFacetData",
      facetName: facet,
      facetSort: sort,
      facetOperator: operator, 
      action: action
    },
    function(response, textStatus) {
      if (response.status == "OK") {
        var results = buildFacetNodes(response.data, currentPath, allowExclude, excludeTitle);
        treeNode.find('.fa-spinner').parent().remove();
        treeNode.on('loaded.jstree open_node.jstree', function (e, data) {
          treeNode.find('ul.jstree-container-ul > li.jstree-node').addClass('list-group-item');
        }).jstree({
          'core': {
            'data': results
          }
        });
      } 
    }
  );
}
