// Polyfill for parentNode.replaceChildren()
// Spec: https://dom.spec.whatwg.org/#dom-parentnode-replacechildren
// Docs: https://developer.mozilla.org/en-US/docs/Web/API/ParentNode/replaceChildren
// Gecko implementation: https://github.com/mozilla/gecko-dev/blob/master/dom/base/nsINode.cpp#L2027
// Webkit implementation: https://github.com/WebKit/webkit/blob/master/Source/WebCore/dom/ContainerNode.cpp#L958
// Source: https://gist.github.com/tomhodgins/14451f123e1878e766c9568ecce13878
if (typeof Element.prototype.replaceChildren !== 'function') {
  Object.defineProperty(Element.prototype, 'replaceChildren', {
    configurable: true,
    writable: true,
    value: function replaceChildren(...nodes) {
      // Remove all existing child nodes
      while (this.firstChild) {
        this.removeChild(this.firstChild);
      }
      // Append new DOM objects
      this.append(...nodes);
    }
  });
}
