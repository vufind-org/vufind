(function() {

  /**
   * @attribute exclude: json array of strings - RVK notations to exclude
   * @attribute search-link-text: string
   * @attribute site-base-url: string
   */
  class RvkTree extends HTMLElement {
    static baseUrl = 'https://rvk.uni-regensburg.de/api/json/children'
    
    connectedCallback() {
      this.searchLinkText = this.getAttribute('search-link-text') || 'search'
      
      this.exclude = this.hasAttribute('exclude')
      ? JSON.parse(this.getAttribute('exclude'))
      : []
      
      const siteBaseUrl = this.getAttribute('site-base-url') || '' 
      this.siteBaseUrl = siteBaseUrl.endsWith('/')
        ? siteBaseUrl.slice(0, -1)
        : siteBaseUrl

      this.renderChildren('root').then(ul => this.append(ul))
    }

    fetchTree(notation) {
      const path = notation === 'root' ? '' : notation
      return fetch(`${RvkTree.baseUrl}/${path}`).then(res => res.json())
    }

    renderChildren(notation) {
      return this.fetchTree(notation).then(tree => {
        const ul = document.createElement('ul')
        for (const child of tree.node.children.node) {
          if (this.exclude.includes(child.notation)) {
            continue
          }
          const li = document.createElement('li')
          li.append(this.renderNode(child));
          ul.append(li)
        }
        return ul
      })
    }

    renderNode(node) {
      const line = this.renderLine(node)
      if (node.has_children !== 'yes') {
        const div = document.createElement('div')
        div.append(line)
        return div
      }
      const details = document.createElement('details')
      const summary = document.createElement('summary')
      details.append(summary)
      summary.append(line)
      details.addEventListener('toggle', e => {
        this.renderChildren(node.notation).then(ul => details.append(ul))
      }, { once: true })
      return details
    }

    renderLine(node) {
      const b = document.createElement('b')
      b.textContent = node.notation
      const a = document.createElement('a')
      a.setAttribute('href', `${this.siteBaseUrl}/Search/Results?lookfor=${encodeURIComponent(node.notation)}&type=rvk_path`)
      a.textContent = this.searchLinkText
      const outerSpan = document.createElement('span')
      outerSpan.style.cursor = node.has_children === 'yes' ? 'pointer' : 'unset'
      outerSpan.append(b, node.benennung, a)
      return outerSpan
    }
  }
  
  window.customElements.define('rvk-tree', RvkTree)
}())