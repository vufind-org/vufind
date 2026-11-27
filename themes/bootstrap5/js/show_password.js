/*global VuFind */

VuFind.register('showPassword', () => {

  /**
   * Set up the button
   * @param {HTMLElement} button The button
   * @param {boolean}     hide   True if the password should be hidden
   */
  function _setupButton(button, hide) {
    const title = hide ? 'Show password' : 'Hide password';
    const icon = hide ? 'password-hidden' : 'password-shown';
    button.setAttribute('title', title);
    button.setAttribute('aria-label', title);
    VuFind.setInnerHtml(button, VuFind.icon(icon));
  }

  /**
   * Click event
   * @param {Event} event click event
   */
  function _click(event) {
    event.preventDefault();
    const button = event.currentTarget;
    const passwordInput = button.parentElement.querySelector('input');
    const type = passwordInput.getAttribute('type');

    if (type === 'password') {
      passwordInput.setAttribute('type', 'text');
      _setupButton(button, false);
    } else {
      passwordInput.setAttribute('type', 'password');
      _setupButton(button, true);
    }
  }

  /**
   * Initialize a password input
   * @param {Element} input input element
   */
  function _initInput(input) {
    if (input.dataset.initialized !== 'true') {
      input.dataset.initialized = 'true';
      const section = document.createElement('div');
      section.classList = 'password-section';
      input.replaceWith(section);
      section.appendChild(input);
      const button = document.createElement('button');
      _setupButton(button, true);
      section.appendChild(button);
      button.addEventListener('click', _click);
    }
  }

  /**
   * Initializes the password inputs in the provided container
   * @param {object} params Params (has to include a container element)
   */
  function updateContainer(params) {
    let container = params.container;
    container.querySelectorAll('input[type="password"]').forEach(_initInput);
  }

  /**
   * showPassword initialization
   */
  function init() {
    if (!VuFind.config.get('site:button-to-show-passwords', false)) {
      return;
    }
    if (CSS.supports('selector(::-ms-reveal)')) {
      // Edge already displays a button to show/hide passwords
      return;
    }
    updateContainer({container: document});
    VuFind.listen('lightbox.rendered', updateContainer);
  }

  return { init, updateContainer };
});
