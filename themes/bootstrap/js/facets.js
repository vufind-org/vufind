function buildFacetNodes(data, currentPath, allowExclude, excludeTitle)
{
  var json = [];
  
  $(data).each(function() {
    var html = '';
    if (!this.applied) {
      html = '<span class="pull-right small">' + this.count;
      if (allowExclude) {
        var excludeURL = currentPath + this.exclude;
        excludeURL.replace("'", "\\'");
        // Just to be safe
        html += '<a href="' + excludeURL + '" onclick="document.location.href=\'' + excludeURL + '\'; return false;" title="' + htmlEncode(excludeTitle) + '"><i class="icon-remove"></i></a>';
      }
      html += '</span>'; 
    }
    
    var url = currentPath + this.href;
    // Just to be safe
    url.replace("'", "\\'");
    html += '<span class="main' + (this.applied ? ' applied' : '') + '" title="' + htmlEncode(this.text) + '"';
    if (!this.applied) {
        html += ' onclick="document.location.href=\'' + url + '\'; return false;"';
    }
    html += '>';
    if (this.operator == 'OR') {
      html += '<input type="checkbox"' + (this.applied ? ' checked="checked" class="applied"' : '') 
        + ' onclick="updateOrFacets(\'' + url + '\', this); return false;"/>';
    } else if (this.applied) {
      html += '<i class="icon-ok pull-right"></i>';  
    }
    html += this.text;
    html += '</span>';

    var children = null;
    var childrenActive = false;
    if (typeof this.children !== 'undefined' && this.children.length > 0) {
      children = buildFacetNodes(this.children, currentPath, allowExclude, excludeTitle);
      $(children).each(function() {
        if (this.applied) {
          childrenActive = true;
          return false;
        }
      });
    }
    json.push({
      'text': html,
      'children': children,
      'applied': this.applied,
      'state': {
        'opened': this.state.opened
      }
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
  var currentPath = treeNode.data('path');
  var allowExclude = treeNode.data('exclude');
  var excludeTitle = treeNode.data('exclude-title');
  var sort = treeNode.data('sort');
  var query = window.location.href.split('?')[1];
  var action = window.location.href.match(/.*?\/([^\/]+)\//)[1];

  treeNode.prepend('<i class="icon-spinner icon-spin"></i>');
  $.getJSON(path + '/AJAX/JSON?' + query,
    {
      method: "getFacetData",
      facetName: facet,
      facetSort: sort,
      action: action
    },
    function(response, textStatus) {
      if (response.status == "OK") {
        var results = buildFacetNodes(response.data, currentPath, allowExclude, excludeTitle);
        treeNode.find('.icon-spinner').remove();
        treeNode.jstree({
          'core': {
            'data': results
          }
        }).find('ul.jstree-container-ul').addClass('nav');
      } 
    }
  );
}
