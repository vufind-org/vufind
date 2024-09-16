/*global VuFind*/

VuFind.register("sticky_elements", function StickyElements() {
  var _stickyElements;
  var _stickyChildrenClassesConfig;
  var _resizeObserver;

  function setChildElementClasses(element, action = null, saveState = false) {
    _stickyChildrenClassesConfig.forEach(
      (config) => {
        let isInScope =
          (config["min-width"] === undefined || window.innerWidth >= config["min-width"])
          && (config["max-width"] === undefined || window.innerWidth <= config["max-width"]);
        let stickyClass = config.class === undefined ? "hidden" : config.class;
        let active = false;
        element.querySelectorAll(config.selector)
          .forEach((e) => {
            if (!isInScope) {
              e.classList.remove(stickyClass);
              return;
            }
            if (saveState) {
              e.dataset.stickyState = e.classList.contains(stickyClass).toString();
            }
            if (action === "load") {
              if (e.dataset.stickyState === "true") {
                e.classList.add(stickyClass);
                active = true;
              } else {
                e.classList.remove(stickyClass);
              }
              e.dataset.stickyState = null;
            }
            if (action === "add") {
              e.classList.add(stickyClass);
              active = true;
            }
            if (action === "remove") {
              e.classList.remove(stickyClass);
            }
          });
        element.dataset.stickyClassesActive = active.toString();
      }
    );
  }

  function setPlaceholderStyle (stickyElement) {
    setChildElementClasses(stickyElement, "remove", true);
    let style = window.getComputedStyle(stickyElement, null);
    let boundingRect = stickyElement.getBoundingClientRect();
    let placeholder = stickyElement.parentNode.previousSibling;
    placeholder.style.height = boundingRect.height + "px";
    placeholder.style.width = boundingRect.width + "px";
    placeholder.style.padding = style.padding;
    placeholder.style.border = style.border;
    placeholder.style.margin = style.margin;
    let baseHeight = boundingRect.height;
    setChildElementClasses(stickyElement, "add");
    boundingRect = stickyElement.getBoundingClientRect();
    stickyElement.dataset.stickyHeighOffset = (baseHeight - boundingRect.height).toString();
    setChildElementClasses(stickyElement, "load");
  }

  function getDefaultBackground() {
    var div = document.createElement("div");
    document.head.appendChild(div);
    var bg = window.getComputedStyle(div).backgroundColor;
    document.head.removeChild(div);
    return bg;
  }

  function getInheritedBackgroundColor(el) {
    var defaultStyle = getDefaultBackground();
    var backgroundColor = window.getComputedStyle(el).backgroundColor;
    if (backgroundColor !== defaultStyle) return backgroundColor;
    if (!el.parentElement) return defaultStyle;
    return getInheritedBackgroundColor(el.parentElement);
  }

  function handleStickyElements(forceStyleCalculation = false) {
    let num = 0;
    let count = _stickyElements.length;
    let currentOffset = 0;
    _stickyElements.forEach(
      (stickyElement) => {
        let stickyContainer = stickyElement.parentNode;
        let placeholder = stickyContainer.previousSibling;
        let isSticky = stickyContainer.classList.contains("sticky");

        // only change classes of elements if placeholder already passed the sticky element even if changed
        let classesApplied = stickyElement.dataset.stickyClassesActive === "true";
        if (isSticky && !classesApplied && placeholder.getBoundingClientRect().bottom < stickyContainer.getBoundingClientRect().bottom - parseInt(stickyElement.dataset.stickyHeighOffset) - 5) {
          setChildElementClasses(stickyElement, "add");
        } else if (classesApplied && (!isSticky || placeholder.getBoundingClientRect().bottom > stickyContainer.getBoundingClientRect().bottom + 5)) {
          setChildElementClasses(stickyElement, "remove");
        }

        let stickyElementStyle = window.getComputedStyle(stickyElement, null);

        let isInScope =
          (
            stickyElement.dataset.stickyMaxWidth === undefined
            || stickyElement.dataset.stickyMaxWidth >= window.innerWidth
          )
          && (
            stickyElement.dataset.stickyMinWidth === undefined
            || stickyElement.dataset.stickyMinWidth <= window.innerWidth
          );

        if (
          isInScope
          && ((!isSticky && currentOffset >= stickyContainer.getBoundingClientRect().top - parseInt(stickyElementStyle.marginTop, 10) + 1)
          || (isSticky && currentOffset >= placeholder.getBoundingClientRect().top - parseInt(stickyElementStyle.marginTop, 10) + 1))
        ) {
          if (forceStyleCalculation || !isSticky) {
            let parentStyle = window.getComputedStyle(stickyContainer.parentNode, null);
            let parentBoundingClientRect = stickyContainer.parentNode.getBoundingClientRect();
            stickyContainer.classList.add("sticky");
            placeholder.classList.remove("hidden");
            stickyContainer.style.marginLeft = parentBoundingClientRect.left + "px";
            stickyContainer.style.marginRight = (window.outerWidth - parentBoundingClientRect.right) + "px";
            stickyContainer.style.borderLeft = parentStyle.borderLeft;
            stickyContainer.style.borderRight = parentStyle.borderRight;
            stickyContainer.style.paddingLeft = parentStyle.paddingLeft;
            stickyContainer.style.paddingRight = parentStyle.paddingRight;
            stickyContainer.style.width = parentBoundingClientRect.width + "px";
          }
          stickyContainer.style.zIndex = 10 + count - num;
          stickyContainer.style.top = currentOffset + "px";
        } else if (forceStyleCalculation || isSticky) {
          stickyContainer.classList.remove("sticky");
          placeholder.classList.add("hidden");
          stickyContainer.style.top = "";
          stickyContainer.style.marginLeft = "0";
          stickyContainer.style.marginRight = "0";
          stickyContainer.style.borderLeft = "0";
          stickyContainer.style.borderRight = "0";
          stickyContainer.style.paddingLeft = "0";
          stickyContainer.style.paddingRight = "0";
          stickyContainer.style.width = "";
          stickyContainer.style.zIndex = "";
        }
        if (isInScope) {
          currentOffset += stickyContainer.getBoundingClientRect().height;
          num += 1;
        }
      });
  }

  function calculateStyles () {
    _stickyElements.forEach(
      (stickyElement) => {
        setPlaceholderStyle(stickyElement);
      }
    );
    handleStickyElements(true);
  }

  function updateContainer() {
    let stickyElementsConfig = VuFind.config.get('sticky-elements', []);
    _stickyElements = stickyElementsConfig.flatMap(
      (config) => {
        let elements = document.querySelectorAll(config.selector);
        if (config["min-width"] !== undefined) {
          elements.forEach((e) => e.dataset.stickyMinWidth = config["min-width"]);
        }
        if (config["max-width"] !== undefined) {
          elements.forEach((e) => e.dataset.stickyMaxWidth = config["max-width"]);
        }
        return Array.from(elements);
      });

    _stickyElements.forEach(
      (stickyElement) => {
        if (!stickyElement.parentNode.classList.contains("sticky-container")) {
          let placeholder = document.createElement('div');
          placeholder.classList.add('sticky-placeholder', 'hidden');
          stickyElement.parentNode.insertBefore(placeholder, stickyElement);

          let container = document.createElement('div');
          container.classList.add('sticky-container');
          stickyElement.parentNode.insertBefore(container, stickyElement);
          stickyElement.previousSibling.insertAdjacentElement('beforeend', stickyElement);

          setPlaceholderStyle(stickyElement);
          stickyElement.parentNode.style.backgroundColor = getInheritedBackgroundColor(stickyElement.parentNode);

          _resizeObserver.observe(stickyElement);
        }
      }
    );
    handleStickyElements(true);
  }

  function init() {
    _resizeObserver = new ResizeObserver(calculateStyles);
    _stickyChildrenClassesConfig = VuFind.config.get('sticky-children-classes', []);
    updateContainer();
    VuFind.listen('results-init', updateContainer);
    window.addEventListener("resize", calculateStyles);
    window.addEventListener("orientationchange", calculateStyles);
    document.addEventListener("scroll", () => handleStickyElements());
  }

  return { init };
});
