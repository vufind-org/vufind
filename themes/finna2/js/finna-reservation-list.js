/*global VuFind, finna */
finna.reservationList = (function finnaReservationList() {
  /**
   * Check status of given list
   * @param {HTMLElement} element Element where status is inserted
   */
  function checkStatus(element) {
    const queryParams = new URLSearchParams({method: 'reservationList', list_id: element.dataset.listId, type: 'status'});
    fetch (`${VuFind.path}/AJAX/JSON?${queryParams.toString()}`)
      .then(response => {
        if (!response.ok) {
          throw new Error(VuFind.translate('error_occurred'));
        }
        return response.json();
      })
      .then(responseJSON => {
        if (responseJSON.data && responseJSON.data.status) {
          element.innerText = responseJSON.data.status;
          element.classList.remove('pending');
        }
      });
  }

  var my = {
    init: () => {
      document.querySelectorAll('.js-reservation-list-status').forEach(el => {
        checkStatus(el);
      });
    }
  };

  return my;
})();
