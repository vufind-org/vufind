/*global VuFind*/

VuFind.register("sticky_elements", function StickyElements() {
  function init($container = document) {
    function setupStickyElements() {
      function sortStickyElements(a, b) {
        let posA = a.dataset.stickyPos;
        let posB = b.dataset.stickyPos;
        if (posA === undefined && posB === undefined) return 0;
        if (posA === undefined) return 1;
        if (posB === undefined) return -1;
        return posA - posB;
      }

      function setPlaceholderStyle (stickyElement) {
        let style = window.getComputedStyle(stickyElement, null);
        let placeholder = stickyElement.parentNode.previousSibling;
        placeholder.style.height = style.height;
        placeholder.style.width = style.width;
        placeholder.style.padding = style.padding;
        placeholder.style.border = style.border;
        placeholder.style.margin = style.margin;
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

      let stickyElements = Array.from(document.getElementsByClassName('sticky-element')).sort(sortStickyElements);

      function handleStickyElements() {
        let num = 0;
        let count = stickyElements.length;
        let currentOffset = 0;
        stickyElements.forEach(
          (stickyElement) => {
            let stickyContainer = stickyElement.parentNode;
            let placeholder = stickyContainer.previousSibling;
            let gapFiller = stickyElement.previousSibling;
            let isSticky = stickyContainer.classList.contains("sticky");
            let stickyElementStyle = window.getComputedStyle(stickyElement, null);
            if (
              (!isSticky && window.scrollY + currentOffset >= stickyContainer.offsetTop - parseInt(stickyElementStyle.marginTop, 10))
              || (isSticky && window.scrollY + currentOffset >= placeholder.offsetTop - parseInt(stickyElementStyle.marginTop, 10))
            ) {
              stickyContainer.classList.add("sticky");
              placeholder.classList.remove("hidden");
              gapFiller.classList.remove("hidden");
              let parentStyle = window.getComputedStyle(stickyContainer.parentNode, null);
              let parentBoundingClientRect = stickyContainer.parentNode.getBoundingClientRect();
              stickyContainer.style.top = (currentOffset - 1) + "px";
              stickyContainer.style.marginLeft = parentBoundingClientRect.left + "px";
              stickyContainer.style.marginRight = (window.screen.width - parentBoundingClientRect.right) + "px";
              stickyContainer.style.borderLeft = parentStyle.borderLeft;
              stickyContainer.style.borderRight = parentStyle.borderRight;
              stickyContainer.style.paddingLeft = parentStyle.paddingLeft;
              stickyContainer.style.paddingRight = parentStyle.paddingRight;
              stickyContainer.style.width = parentBoundingClientRect.width + "px";
              stickyContainer.style.zIndex = 10 + count - num;
              gapFiller.style.width = stickyElementStyle.width;
              gapFiller.style.marginLeft = stickyElementStyle.marginLeft;
              gapFiller.style.borderLeft = stickyElementStyle.borderLeft;
              gapFiller.style.paddingLeft = stickyElementStyle.paddingLeft;
              currentOffset -= 1;
            } else {
              stickyContainer.classList.remove("sticky");
              placeholder.classList.add("hidden");
              gapFiller.classList.add("hidden");
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
            currentOffset += stickyContainer.offsetHeight;
            num += 1;
          });
      }

      stickyElements.forEach(
        (stickyElement) => {
          let placeholder = document.createElement('div');
          placeholder.classList.add('sticky-placeholder', 'hidden');
          stickyElement.parentNode.insertBefore(placeholder, stickyElement);

          let container = document.createElement('div');
          container.classList.add('sticky-container');
          stickyElement.parentNode.insertBefore(container, stickyElement);

          stickyElement.previousSibling.insertAdjacentElement('beforeEnd', stickyElement);

          let gapFiller = document.createElement('div');
          gapFiller.classList.add('sticky-gap-filler', 'hidden');
          stickyElement.parentNode.insertBefore(gapFiller, stickyElement);

          setPlaceholderStyle(stickyElement);
          stickyElement.parentNode.style.backgroundColor = getInheritedBackgroundColor(stickyElement.parentNode);
          stickyElement.previousSibling.style.backgroundColor = getInheritedBackgroundColor(stickyElement);
        }
      );
      handleStickyElements();

      window.addEventListener("resize", () => {
        stickyElements.forEach(
          (stickyElement) => {
            setPlaceholderStyle(stickyElement);
          }
        );
        handleStickyElements();
      });
      document.addEventListener("scroll", handleStickyElements);
    }
  }

  return { init };
});
