/*global VuFind */
VuFind.register("expandableText", function ExpandableText() {
  /**
   * Initialize the expandable texts.
   */
  function init() {
    document.querySelectorAll('.expandable-text').forEach((expandableText) => {
      if (expandableText.offsetHeight > 100) {
        expandableText.classList.add('collapse');
        expandableText.nextElementSibling.classList.remove('hidden');
      }
    });
  }

  return {
    init: init
  };
});
