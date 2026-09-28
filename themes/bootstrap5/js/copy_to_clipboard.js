/*global VuFind, bootstrap */
VuFind.register('copyToClipboard', function copyToClipboard() {

  /**
   * Show popover for 2 seconds
   * @param {object} popover Popover
   */
  function _showPopover(popover) {
    if (popover !== undefined) {
      popover.show();
      setTimeout(() => {
        popover.hide();
      }, 2000);
    }
  }

  /**
   * Initialise copy button
   * @param {Element} button Copy button
   */
  function _initButton(button) {
    if (button.dataset.initialized !== 'true') {
      button.dataset.initialized = 'true';
      if (!button.dataset.target) return;
      const targetElement = document.querySelector(button.dataset.target);
      if (!targetElement) return;
      let successPopover;
      const successMessageElement = document.querySelector('#copySuccessMessage' + button.dataset.number);
      if (successMessageElement) {
        successPopover = new bootstrap.Popover(button.parentNode, {trigger: 'manual', 'html': true, 'content': successMessageElement.innerHTML});
      }
      let errorPopover;
      const errorMessageElement = document.querySelector('#copyFailureMessage' + button.dataset.number);
      if (errorMessageElement) {
        errorPopover = new bootstrap.Popover(button.parentNode, {trigger: 'manual', 'html': true, 'content': errorMessageElement.innerHTML});
      }
      button.addEventListener('click', () => {
        let content = targetElement.textContent.trim();
        if (typeof ClipboardItem !== 'undefined') {
          const html = targetElement.innerHTML.trim();
          content = [ new ClipboardItem({
            ['text/plain']: new Blob([content], {type: 'text/plain'}),
            ['text/html']: new Blob([html], {type: 'text/html'})
          })];
        }
        navigator.clipboard.write(content).then(() => _showPopover(successPopover), () => _showPopover(errorPopover));
      });
      button.classList.remove('hidden');
    }
  }

  /**
   * Initializes the copy to clipboard buttons in the provided container
   * @param {object} params Params (has to include a container element)
   */
  function updateContainer(params) {
    let container = params.container;
    container.querySelectorAll('.copy-to-clipboard-button').forEach(_initButton);
  }

  /**
   * Init copy to clipboard
   */
  function init() {
    updateContainer({container: document});
    VuFind.listen('lightbox.rendered', updateContainer);
  }

  return { init, updateContainer };
});


