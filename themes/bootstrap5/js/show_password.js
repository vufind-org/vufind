/*global VuFind, bootstrap */

VuFind.register('showPassword', () => {

  /**
   * Click event on an eye span
   * @param {Event} event click event
   */
  function _clickOnEye(event) {
    event.preventDefault();
    const eye = event.currentTarget;
    const passwordInput = eye.parentElement.querySelector('input');
    const type = passwordInput.getAttribute('type');

    if (type === 'password') {
      passwordInput.setAttribute('type', 'text');
      eye.classList.remove('fa-eye-slash');
      eye.classList.add('fa-eye');
      eye.setAttribute('title', VuFind.translate('Hide password'));
      eye.setAttribute('aria-label', VuFind.translate('Hide password'));
    } else {
      passwordInput.setAttribute('type', 'password');
      eye.classList.remove('fa-eye');
      eye.classList.add('fa-eye-slash');
      eye.setAttribute('title', VuFind.translate('Show password'));
      eye.setAttribute('aria-label', VuFind.translate('Show password'));
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
      const eye = document.createElement('span');
      eye.classList = 'fa fa-eye-slash';
      eye.setAttribute('title', VuFind.translate('Show password'));
      eye.setAttribute('aria-label', VuFind.translate('Show password'));
      eye.setAttribute('tabindex', '0')
      section.appendChild(eye);
      eye.addEventListener('click', _clickOnEye);
      eye.addEventListener('keyup', event => {
        if (event.key === 'Enter') {
          eye.click();
        }
      });
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
