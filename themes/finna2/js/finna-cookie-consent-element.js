/* global VuFind, CookieConsent */
class FinnaCookieConsentElement extends HTMLElement {

  /**
   * Get consent categories
   * @returns {string} Consent categories
   */
  get consentCategories() {
    return this.getAttribute('consent-categories') || '';
  }

  /**
   * Set consent categories
   * @param {string} newValue Value to set
   */
  set consentCategories(newValue) {
    this.setAttribute('consent-categories', newValue);
  }

  /**
   * Get service base URL
   * @returns {string} Service base URL
   */
  get serviceBaseUrl() {
    const url = this.getAttribute('service-url');
    if (url) {
      try {
        return new URL(url).host;
      } catch (_) {
        return url;
      }
    }
    return '';
  }

  /**
   * Get service URL
   * @returns {string} Service URL
   */
  get serviceUrl() {
    return this.getAttribute('service-url') || '';
  }

  /**
   * Set service URL
   * @param {string} newValue Value to set
   */
  set serviceUrl(newValue) {
    this.setAttribute('service-url', newValue);
  }

  /**
   * Constructor
   */
  constructor() {
    super();
  }

  /**
   * When the element is added to the dom
   */
  connectedCallback() {
    // Create the element
    const divInfo = document.createElement('div');
    divInfo.classList.add('embedded-content-cookie-info');

    const divHeading = document.createElement('div');
    divHeading.classList.add('embedded-content-heading');
    divHeading.append(VuFind.translate('embedded_content_heading'));
    divInfo.append(divHeading);

    const divDescription = document.createElement('div');
    divDescription.classList.add('embedded-content-description');
    const replacements = {
      '%%serviceBaseUrl%%': this.serviceBaseUrl,
      '%%consentCategories%%': this.consentCategories
    };
    divDescription.append(VuFind.translate('embedded_content_description', replacements));
    divInfo.append(divDescription);

    const divActions = document.createElement('div');
    divActions.classList.add('embedded-content-actions');

    const aOuterLink = document.createElement('a');
    aOuterLink.classList.add('btn', 'btn-primary');
    aOuterLink.href = this.serviceUrl || '';
    aOuterLink.target = '_blank';
    aOuterLink.append(VuFind.translate('embedded_content_external_link'));

    const linkIcon = document.createElement('i');
    linkIcon.classList.add('fa', 'fa-new-window');
    linkIcon.setAttribute('aria-hidden', true);
    aOuterLink.append(' ', linkIcon);

    const linkSpan = document.createElement('span');
    linkSpan.classList.add('visually-hidden');
    linkSpan.append(VuFind.translate('Open in a new window'));
    aOuterLink.append(linkSpan);
    divActions.append(aOuterLink);

    const aShowModal = document.createElement('a');
    aShowModal.classList.add('btn', 'btn-default');
    aShowModal.href = '#';
    aShowModal.setAttribute('aria-haspopup', 'dialog');
    aShowModal.append(VuFind.translate('Cookie Settings'));
    aShowModal.addEventListener('click', () => {
      $.fn.finnaPopup.closeOpen();
      CookieConsent.showPreferences();
    });
    divActions.append(aShowModal);
    divInfo.append(divActions);
    this.append(divInfo);
  }
}

customElements.define('finna-consent', FinnaCookieConsentElement);
