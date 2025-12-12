/*global recaptchaOnLoad, resetCaptcha, VuFind */
VuFind.register('recordComments', function RecordComments() {
  /**
   * Delete a record comment via an AJAX request.
   * @param {HTMLElement} element The element that triggered the delete.
   * @param {string} recordId The ID of the record.
   * @param {string} recordSource The source of the record.
   * @param {string} commentId The ID of the comment to delete.
   */
  function _deleteRecordComment(element, recordId, recordSource, commentId) {
    const url = VuFind.path + '/AJAX/JSON?' + new URLSearchParams({ method: 'deleteRecordComment', id: commentId });
    fetch(url, {
      headers: {'Accept': 'application/json'}
    }).then(() => {
      const comment = element.closest('.comment');
      if (comment) {
        comment.parentNode.removeChild(comment);
      }
    });
  }

  /**
   * Refresh the list of comments for a record.
   * @param {HTMLElement} target The container element for the comments.
   * @param {string} recordId The ID of the record.
   * @param {string} recordSource The source of the record.
   */
  function _refreshCommentList(target, recordId, recordSource) {
    const commentList = target.querySelector('.comment-list');
    if (!commentList) return;
    commentList.prepend(VuFind.loadingOverlay());
    const url = VuFind.path + '/AJAX/JSON?' + new URLSearchParams({
      method: 'getRecordCommentsAsHTML',
      id: recordId,
      source: recordSource
    });
    fetch(url, {
      headers: {'Accept': 'application/json'}
    }).then(response => response.json())
      .then((response) => {
        // Update HTML
        VuFind.setInnerHtml(commentList, VuFind.updateCspNonce(response.data.html));
        commentList.querySelectorAll('.delete')
          .forEach((deleteLink) => deleteLink.addEventListener('click', event => {
            event.preventDefault();
            const commentId = deleteLink.id.substring('recordComment'.length);
            _deleteRecordComment(deleteLink, recordId, recordSource, commentId);
          }));
        resetCaptcha(target);
      });
  }

  /**
   * Refresh the record rating display.
   * @param {string} recordId The ID of the record.
   * @param {string} recordSource The source of the record.
   */
  function _refreshRecordRating(recordId, recordSource) {
    const rating = document.querySelector('.media-left .rating');
    if (!rating) {
      return;
    }
    fetch(VuFind.path + '/AJAX/JSON?' + new URLSearchParams({
      method: 'getRecordRating',
      id: recordId,
      source: recordSource
    }))
      .then(response => response.json())
      .then(result => {
        VuFind.setOuterHtml(rating, result.data.html);
        // Bind lightbox to the new content:
        VuFind.lightbox.bind(rating);
      });
  }

  /**
   * Handle the submission of a comment form via AJAX.
   * @param {Event} event The form submission event.
   */
  function _postComment(event) {
    event.preventDefault();
    const form = event.target;
    const id = form.id.value;
    const recordSource = form.source.value;
    const url = VuFind.path + '/AJAX/JSON?' + new URLSearchParams({ method: 'commentRecord' });
    const data = {};
    const loadingSpinner = form.querySelector('.js-loading-spinner');
    if (loadingSpinner) {
      loadingSpinner.classList.remove('hidden');
    }
    const submitButtons = form.querySelectorAll('[type=submit]');
    // Disable submit buttons (we don't use the data-disable-on-submit attribute because we need to also enable them):
    submitButtons.forEach(btn => btn.disabled = true);
    form.querySelectorAll('input,textarea').forEach((input) => {
      if (input.type !== 'radio' || input.checked) {
        data[input.name] = input.value;
      }
    });
    fetch(url, {
      method: 'POST',
      headers: {'Accept': 'application/json'},
      body: new URLSearchParams(data)
    }).then((response) => {
      if (!response.ok) {
        return response.json();
      }
      return Promise.resolve();
    })
      .then((optionalError) => {
        if (optionalError) {
          VuFind.lightbox.alert(optionalError.data, 'danger');
          submitButtons.forEach(btn => btn.disabled = false);
          loadingSpinner.classList.add('hidden');
          return;
        }
        let tab = form.closest('.list-tab-content');
        if (!tab) {
          tab = form.closest('.tab-pane');
        }
        if (tab) {
          _refreshCommentList(tab, id, recordSource);
        }
        _refreshRecordRating(id, recordSource);
        const textArea = form.querySelector('textarea[name="comment"]');
        if (textArea) {
          textArea.value = '';
        }
        if (form.dataset.ratingRemoval === "false" && Object.prototype.hasOwnProperty.call(data, 'rating') && '' !== data.rating) {
          const link = form.querySelector('a[data-click-set-checked]');
          if (link) {
            link.parentNode.removeChild(link);
          }
        }
        resetCaptcha(form);
        submitButtons.forEach(btn => btn.disabled = false);
        loadingSpinner.classList.add('hidden');
      });
  }

  /**
   * Handle adding a rating to a record by programmatically clicking the rating link.
   */
  function addRecordRating() {
    const ratingLink = document.querySelector('.rating-average a');
    if (ratingLink) {
      ratingLink.click();
    }
  }

  /**
   * Register event listeners for AJAX-based comment submission and deletion.
   * @param {HTMLElement} [_context] The container element to search within (default = document).
   */
  function registerAjaxCommentRecord(_context) {
    const context = typeof _context === "undefined" ? document : _context;

    // Form submission
    context.querySelectorAll('form.comment-form')
      .forEach((form) => form.addEventListener('submit', _postComment));

    // Delete links
    context.querySelectorAll('.delete')
      .forEach((deleteLink) => deleteLink.addEventListener('click', event => {
        event.preventDefault();
        const commentId = deleteLink.id.substring('recordComment'.length);
        const id = document.querySelector('.hiddenId');
        const source = document.querySelector('.hiddenSource');
        if (id && source) {
          _deleteRecordComment(deleteLink, id.value, source.value, commentId);
        }
      }));
  }

  /**
   * Update a container by registering comments.
   * @param {object} params An object containing the container element.
   */
  function updateContainer(params) {
    let container = params.container;
    recaptchaOnLoad(container);
    registerAjaxCommentRecord(container);
  }

  /**
   * Initialize record comments.
   */
  function init() {
    updateContainer({container: document});
    VuFind.listen('record-tab-init', updateContainer);
  }

  return {
    init,
    addRecordRating
  };
});
