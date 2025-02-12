/*global finna, VuFind */
finna.checkoutHistory = (function checkoutHistory() {
  /**
   * Selector used to obtain download button
   * @member {string} downloadButtonSelector
   */
  const downloadButtonSelector = 'button.js-download-checkout-history';

  /**
   * Holder for download button element.
   * @member {HTMLButtonElement} downloadButton
   */
  let downloadButton;

  /**
   * Current part to be downloaded
   * @member {number} currentPart
   */
  let currentPart = 0;

  /**
   * Last part to be downloaded
   * @member {number} lastPart
   */
  let lastPart = -1;

  /**
   * Set the download button text to match next loadable part
   * @returns {void}
   */
  function syncButtonText() {
    const textTemplate = VuFind.translate('loan_history_download_part');
    downloadButton.textContent = `${textTemplate.replace('%%part%%', currentPart).replace('%%lastPart%%', lastPart)}`;
  }

  /**
   * Display a spinner inside the download button
   */
  function displaySpinner()
  {
    const spinnerElement = VuFind.icon('spinner', {}, true);
    downloadButton.replaceChildren(spinnerElement, ` ${VuFind.translate('loading_ellipsis')}`);
  }

  /**
   * Request part of a checkout history to download
   */
  function getCheckoutHistoryPart()
  {
    displaySpinner();
    const searchParams = new URLSearchParams(
      {
        method: "getCheckoutHistoryFile",
        part: currentPart,
        format: downloadButton.dataset.format
      }
    );
    let filename;
    fetch (`${VuFind.path}/AJAX/FILE?${searchParams}`)
      .then(response => {
        if (!response.ok) {
          throw new Error('');
        }
        const header = response.headers.get('Content-Disposition');
        const parts = header.split(';');
        filename = parts[1].split('=')[1].replaceAll("\"", "");
        return response.blob();
      }).then((blob) => {
        const url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a); // we need to append the element to the dom -> otherwise it will not work in firefox
        a.click();
        a.remove();
        if (currentPart < lastPart) {
          currentPart++;
        }
        syncButtonText();
      }).catch((reason) => {
        console.warn(reason);
        syncButtonText();
      });
  }

  /**
   * Initializes download checkout history
   * @returns {void}
   */
  function init() {
    downloadButton = document.querySelector(downloadButtonSelector);
    if (!downloadButton) {
      return;
    }

    // Check if there is cached results
    if (lastPart > -1) {
      currentPart = 1;
      syncButtonText();
      downloadButton.addEventListener('click', (e) => {
        e.preventDefault();
        getCheckoutHistoryPart();
      });
    } else {
      displaySpinner();
      fetch (`${VuFind.path}/AJAX/JSON?method=getCheckoutHistory`)
        .then(response => {
          if (!response.ok) {
            throw new Error('Error occurred.');
          }
          return response.json();
        }).then(result => {
          if (!result.data) {
            return;
          }
          currentPart = 1;
          if (result.data && result.data.parts) {
            lastPart = result.data.parts;
            syncButtonText();
            downloadButton.addEventListener('click', (e) => {
              e.preventDefault();
              getCheckoutHistoryPart();
            });
          }
        }).catch(error => {
          downloadButton.style.display = 'none';
          console.warn(error);
        });
    }
  }

  return {
    init: init
  };
})();
