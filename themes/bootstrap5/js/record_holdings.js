/*global deparam, VuFind */
VuFind.register('recordHoldings', function RecordHoldings() {
  /**
   * Check if a user request is valid by making an AJAX call.
   * @param {HTMLElement} element The link element to check.
   * @param {string} requestType The type of request (e.g., 'Hold', 'StorageRetrievalRequest').
   * @param {string} [icon] The icon to display (default = 'place-hold').
   */
  function _checkRequestIsValid(element, requestType, icon = 'place-hold') {
    const recordId = element.href.match(/\/Record\/([^/]+)\//)[1];
    const vars = deparam(element.href);
    vars.id = recordId;

    const url = VuFind.path + '/AJAX/JSON?' + new URLSearchParams({
      method: 'checkRequestIsValid',
      id: recordId,
      requestType: requestType,
      data: vars
    });
    fetch(url, {
      headers: {
        'Accept': 'application/json',
        'cache': 'no-store'
      }
    }).then(response => response.json())
      .then(function checkValidDone(response) {
        if (response.data.status) {
          element.classList.remove('disabled', 'request-check');
          element.title = response.data.msg;
          VuFind.setInnerHtml(element, VuFind.icon(icon) + '<span class="icon-link__label">' + VuFind.updateCspNonce(response.data.msg) + '</span>');
        } else {
          element.parentNode.removeChild(element);
        }
      })
      .catch(() => element.parentNode.removeChild(element));
  }

  /**
   * Set up the validity check for request links within a given context.
   * @param {HTMLElement} [_context] The container element to search within (default = document).
   */
  function _setUpCheckRequest(_context) {
    const context = typeof _context === "undefined" ? document : _context;
    context.querySelectorAll('.checkRequest').forEach(
      (element) => _checkRequestIsValid(element, 'Hold', 'place-hold')
    );
    context.querySelectorAll('.checkStorageRetrievalRequest').forEach(
      (element) => _checkRequestIsValid(element, 'StorageRetrievalRequest', 'place-storage-retrieval')
    );
    context.querySelectorAll('.checkILLRequest').forEach(
      (element) => _checkRequestIsValid(element, 'ILLRequest', 'place-ill-request')
    );
  }

  /**
   * Update a container by setting up request checks.
   * @param {object} params An object containing the container element.
   */
  function updateContainer(params) {
    let container = params.container;
    _setUpCheckRequest(container);
  }

  /**
   * Initialize record holdings.
   */
  function init() {
    updateContainer({container: document});
    VuFind.listen('record-tab-init', updateContainer);
  }

  return {
    init,
  };
});
