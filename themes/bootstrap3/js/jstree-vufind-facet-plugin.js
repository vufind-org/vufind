/*global VuFind */

// Adds a plugin for drawing hierarchical facet entries
$.jstree.plugins.vufindFacet = function vufindFacet(options, parent) {
  this.redraw_node = function redrawNode(treeNode, deep, callback, force_draw) {
    const elem = parent.redraw_node.call(this, treeNode, deep, callback, force_draw);
    if (elem) {
      const node = this.get_node(elem.id);
      if (node) {
        // Add a wrapper around the anchor node:
        let wrapper = document.createElement('div');
        wrapper.className = 'facet js-facet-item' + (node.state.selected ? ' active' : '');
        let a = elem.querySelector('a.jstree-anchor');
        elem.insertBefore(wrapper, a);
        wrapper.appendChild(a);

        // Add badge:
        if (null !== node.data.count) {
          let badge = document.createElement('span');
          badge.className = 'badge';
          badge.appendChild(document.createTextNode(node.data.count));
          wrapper.appendChild(badge);
        }

        // Add exclude button if available:
        if (node.data.excludeUrl) {
          let excludeA = document.createElement('a');
          excludeA.className = 'exclude';
          excludeA.setAttribute('href', node.data.excludeUrl);
          excludeA.setAttribute('title', node.data.excludeTitle);
          excludeA.innerHTML = VuFind.icon('facet-exclude');
          wrapper.appendChild(excludeA);
        }
      }
    }
    return elem;
  };
};
