/*global VuFind, getFocusableNodes, recaptchaOnLoad, resetCaptcha, unwrapJQuery */

const RESPONSE_OK = 200;
const RESPONSE_NOCONTENT = 204;
const RESPONSE_RESET = 205;

VuFind.register('lightbox', function Lightbox() {
  var child = "";
  var parent = "";

  // State
  var _originalUrl = false;
  var _currentUrl = false;
  var _lbReferrerUrl = false;
  var _lightboxTitle = false;
  var refreshOnClose = false;
  var _modalParams = {};
  // Elements
  var _modal, _modalBody, _clickedButton = null;
  // Utilities
  function _storeClickedStatus(event) {
    _clickedButton = event.target;
  }
  function _html(content) {
    _modalBody.html(VuFind.updateCspNonce(content));
    // Set or update title if we have one
    var $h2 = _modalBody.find("h2:first-of-type");
    if (_lightboxTitle && $h2.length > 0) {
      $h2.text(_lightboxTitle);
    }
    if ($h2.length > 0) {
      $h2.attr('id', 'lightbox-title');
      _modal.attr('aria-labelledby', 'lightbox-title');
    } else {
      _modal.removeAttr('aria-labelledby');
    }
    _lightboxTitle = false;
    _modal.modal('handleUpdate');
  }
  function contains(el) {
    return unwrapJQuery(_modal).contains(el);
  }

  function _addQueryParameters(url, params) {
    let fragmentSplit = url.split('#');
    let paramsSplit = fragmentSplit[0].split('?');
    let searchParams = new URLSearchParams(paramsSplit.length > 1 ? paramsSplit[1] : "");
    for (const [key, value] of Object.entries(params)) {
      searchParams.set(key, value);
    }
    let res = paramsSplit[0] + '?' + searchParams.toString();
    res += fragmentSplit.length < 2 ? '' : '#' + fragmentSplit[1];
    return res;
  }

  // Public: Present an alert
  function showAlert(message, _type) {
    var type = _type || 'info';
    _html('<div class="flash-message alert alert-' + type + '">' + message + '</div>'
        + '<button class="btn btn-default" data-dismiss="modal">' + VuFind.translate('close') + '</button>');
    _modal.modal('show');
  }
  function flashMessage(message, _type) {
    var type = _type || 'info';
    _modalBody.find('.flash-message,.modal-loading-overlay,.loading-spinner').remove();
    _modalBody.find('h2:first-of-type')
      .after('<div class="flash-message alert alert-' + type + '">' + message + '</div>');
  }
  function close() {
    _modal.modal('hide');
  }

  /**
   * Update content
   *
   * Form data options:
   *
   * data-lightbox-ignore        do not submit this form in lightbox
   *
   * Script data options:
   *
   * data-lightbox-run           run the script when lightbox content is shown
   * data-lightbox-run="always"  run the script even if only a success message is displayed
   *
   */
  function render(content) {
    if (typeof content !== "string") {
      return;
    }
    // Isolate any success messages and scripts that should always run
    var htmlDiv = $('<div/>').html(VuFind.updateCspNonce(content));
    var runScripts = htmlDiv.find('script[data-lightbox-run]');
    var alwaysRunScripts = htmlDiv.find('script[data-lightbox-run="always"]');
    var alerts = htmlDiv.find('.flash-message.alert-success:not([data-lightbox-ignore])');
    if (alerts.length > 0) {
      var msgs = alerts.toArray().map(function getSuccessHtml(el) {
        return el.innerHTML;
      }).join('<br>');
      var href = alerts.find('.download').attr('href');
      if (typeof href !== 'undefined') {
        location.href = href;
        close();
      } else {
        showAlert(msgs, 'success');
        // Add any scripts to head to run them
        alwaysRunScripts.each(function addScript(i, script) {
          $(document).find('head').append(script);
        });
      }
      return;
    }
    // Deframe HTML
    var finalHTML = content;
    if (content.match('<!DOCTYPE html>')) {
      finalHTML = htmlDiv.find('.main > .container').html();
    }
    // Fill HTML
    _html(finalHTML);
    VuFind.modal('show');

    // Handle submit buttons attached to a form as well as those in a form. Store
    // information about which button was clicked here as checking focused button
    // doesn't work on all browsers and platforms.
    _modalBody.find('[type=submit]').click(_storeClickedStatus);

    // Select all checkboxes
    $('#modal').find('.checkbox-select-all').on("change", function lbSelectAllCheckboxes() {
      $(this).closest('.modal-body').find('.checkbox-select-item').prop('checked', this.checked);
    });
    $('#modal').find('.checkbox-select-item').on("change", function lbSelectAllDisable() {
      $(this).closest('.modal-body').find('.checkbox-select-all').prop('checked', false);
    });
    // Recaptcha
    recaptchaOnLoad();
    // Add any scripts to head to run them
    runScripts.each(function addScript(i2, script) {
      $(document).find('head').append(script);
    });
  }

  var _xhr = false;
  // Public: Handle AJAX in the Lightbox
  function ajax(obj) {
    if (_xhr !== false) {
      return;
    }
    // Loading
    _modalBody.find('.modal-loading-overlay,.loading-spinner').remove();
    if (_modalBody.children().length > 0) {
      _modalBody.prepend('<div class="modal-loading-overlay">' + VuFind.loading() + '</div>');
    } else {
      _modalBody.prepend(VuFind.loading());
    }
    // Add lightbox GET parameter
    if (!obj.url.match(/layout=lightbox/)) {
      obj.url = _addQueryParameters(obj.url, {'layout': 'lightbox'});
      // Set referrer to current url if it isn't already set:
      if (_currentUrl && !_lbReferrerUrl) {
        _lbReferrerUrl = _currentUrl;
      }
      if (_lbReferrerUrl) {
        obj.url = _addQueryParameters(obj.url, {'lbreferer': _lbReferrerUrl});
      }
    }
    if (VuFind.lightbox.parent) {
      obj.url = _addQueryParameters(obj.url, {'lightboxParent': VuFind.lightbox.parent});
    }
    // Store original URL with the layout=lightbox parameter:
    if (_originalUrl === false) {
      _originalUrl = obj.url;
    }
    _xhr = $.ajax(obj);
    _xhr.always(function lbAjaxAlways() { _xhr = false; })
      .done(function lbAjaxDone(content, status, jq_xhr) {
        var errorMsgs = [];
        var flashMessages = [];
        if (jq_xhr.status === RESPONSE_NOCONTENT) {
          // No content, close lightbox
          close();
          return;
        } else if (jq_xhr.status !== RESPONSE_RESET) {
          var testDiv = $('<div/>').html(content);
          errorMsgs = testDiv.find('.flash-message.alert-danger:not([data-lightbox-ignore])');
          flashMessages = testDiv.find('.flash-message:not([data-lightbox-ignore])');
          // Place Hold error isolation
          if (obj.url.match(/\/Record\/.*(Hold|Request)\?/)) {
            if (errorMsgs.length && testDiv.find('.record').length) {
              var msgs = errorMsgs.toArray().map(function getAlertHtml(el) {
                return el.innerHTML;
              }).join('<br>');
              showAlert(msgs, 'danger');
              return false;
            }
          }
        }

        // Close the lightbox after deliberate login provided that:
        // - is a form
        // - catalog login for holds
        // - or that matches login/create account
        // - not a failed login

        const objPathname = obj.url.split("?")[0]; // ignore queries like lbReferrer
        if (
          obj.method
          && (
            objPathname.match(/catalogLogin/)
            || objPathname.match(/MyResearch\/(?!Bulk|Delete|Recover)/)
          )
          && flashMessages.length === 0
        ) {
          let doRefresh = true;
          const cancelRefresh = () => doRefresh = false;

          VuFind.emit(
            "lightbox.login",
            {
              cancel: cancelRefresh, // call this function to cancel refresh
              status: jq_xhr.status,
              content,
              form: obj,
              currentUrl: _currentUrl,
              originalUrl: _originalUrl,
            }
          );

          if (
            objPathname.match(/catalogLogin/)
            || _originalUrl.match(/UserLogin/)
            || _originalUrl.match(/CompleteLogin/)
          ) {
            if (doRefresh) {
              VuFind.refreshPage();
            }
            return false;
          }
          _currentUrl = _originalUrl; // Now that we're logged in, where were we?
        }
        if (jq_xhr.status === RESPONSE_RESET) {
          VuFind.refreshPage();
          return;
        }
        render(content);
      })
      .fail(function lbAjaxFail(deferred, errorType, msg) {
        showAlert(VuFind.translate('error_occurred') + '<br>' + msg, 'danger');
      });
    return _xhr;
  }
  function reload() {
    ajax({ url: _currentUrl || _originalUrl });
  }

  /**
   * Modal link data options
   *
   * data-lightbox-href = go to this url instead
   * data-lightbox-ignore = do not open this link in lightbox
   * data-lightbox-post = post data
   * data-lightbox-title = Lightbox title (overrides any title the page provides)
   */
  function _constrainLink(event) {
    const link = event.target.closest("a");

    if (link === null) {
      return true;
    }

    // Data attribute escape

    if (link.hasAttribute("data-lightbox-ignore")) {
      return true;
    }

    // Is this link marked to open the lightbox or already inside the lightbox?

    if (
      !link.hasAttribute("data-lightbox") &&
      !VuFind.lightbox.contains(link)
    ) {
      return true;
    }

    // defaults in `init` below

    let doConstrain = true;
    const cancelConstrain = () => doConstrain = false;

    VuFind.emit(
      "lightbox.link",
      {
        link,
        cancel: cancelConstrain, // call this function to escape Lightbox
        currentUrl: _currentUrl,
        originalUrl: _originalUrl,
      }
    );
    if (!doConstrain) {
      return true;
    }

    event.preventDefault();

    const $link = $(link);
    var obj = { url: $link.data('lightbox-href') || link.href };
    if ("string" === typeof $link.data('lightbox-post')) {
      obj.type = 'POST';
      obj.data = $link.data('lightbox-post');
    }

    _lightboxTitle = $link.data('lightbox-title') || false;
    _modalParams = $link.data();

    VuFind.modal('show');
    ajax(obj);

    _currentUrl = link.href;
    return false;
  };

  /**
   * Handle form submission.
   *
   * Form data options:
   *
   * data-lightbox-onsubmit = on submit, run named function
   * data-lightbox-onclose  = on close, run named function
   * data-lightbox-title = Lightbox title (overrides any title the page provides)
   *
   * Submit button data options:
   *
   * data-lightbox-ignore = do not handle clicking this button in lightbox
   */
  function _constrainForm(event) {
    const form = event.target.closest("form");

    if (form === null) {
      return true;
    }

    // Data attribute escape

    if (form.hasAttribute("data-lightbox-ignore")) {
      return true;
    }

    // Is this form marked to open the lightbox or already inside the lightbox?

    if (
      !form.hasAttribute("data-lightbox") &&
      !VuFind.lightbox.contains(form)
    ) {
      return true;
    }

    let doConstrain = true;
    const cancelConstrain = () => doConstrain = false;

    VuFind.emit(
      "lightbox.form",
      {
        form,
        cancel: cancelConstrain, // call this function to escape Lightbox
        currentUrl: _currentUrl,
        originalUrl: _originalUrl,
      }
    );
    if (!doConstrain) {
      return true; // submit form normally
    }

    // Gather data
    var data = $(form).serializeArray();
    // Force layout
    data.push({ name: 'layout', value: 'lightbox' }); // Return in lightbox, please
    // Add submit button information
    var submit = $(_clickedButton);
    _clickedButton = null;
    var buttonData = { name: 'submitButton', value: 1 };
    if (submit.length > 0) {
      if (typeof submit.data('lightbox-close') !== 'undefined') {
        close();
        return false;
      }
      if (typeof submit.data('lightbox-ignore') !== 'undefined') {
        return true;
      }
      buttonData.name = submit.attr('name') || 'submitButton';
      buttonData.value = submit.attr('value') || 1;
    }
    data.push(buttonData);

    // Special handlers
    // - onsubmit behavior
    if ('string' === typeof $(form).data('lightbox-onsubmit')) {
      var ret = VuFind.evalCallback($(form).data('lightbox-onsubmit'), event, data);
      // return true or false to send that to the form
      // return null or anything else to continue to the ajax
      if (ret === false || ret === true) {
        return ret;
      }
    }
    // - onclose behavior
    if ('string' === typeof $(form).data('lightbox-onclose')) {
      VuFind.listen('lightbox.closed', function lightboxClosed() {
        VuFind.evalCallback($(form).data('lightbox-onclose'), null, form);
      }, { once: true });
    }
    // Prevent multiple submission of submit button in lightbox
    if (submit.closest(_modal).length > 0) {
      submit.attr('disabled', 'disabled');
    }
    // Store custom title
    _lightboxTitle = submit.data('lightbox-title') || $(form).data('lightbox-title') || false;

    // Get Lightbox content
    ajax({
      url: $(form).attr('action') || _currentUrl || window.location.href,
      method: $(form).attr('method') || 'GET',
      data: data
    }).done(function formAjaxDone(data, textStatus, jqXHR) {
      VuFind.emit("lightbox.form.success", { form });
      resetCaptcha($(form));
    }).fail(function formAjaxFail(qXHR, textStatus, errorThrown) {
      VuFind.emit("lightbox.form.failure", { form });
    });

    VuFind.modal('show');
    return false;
  }

  function lightboxOpenImage(event) {
    event.preventDefault();

    var url = link.dataset.lightboxHref || link.href || link.src;
    var imageCheck = $.ajax({
      url: url,
      method: "HEAD"
    });

    imageCheck.done(function lightboxImageCheckDone(content, status, jq_xhr) {
      if (
        jq_xhr.status === RESPONSE_OK &&
        jq_xhr.getResponseHeader("content-type").startsWith("image")
      ) {
        render('<div class="lightbox-image"><img src="' + url + '"/></div>');
      } else {
        location.href = url;
      }
    });

    return false;
  }

  /**
   * Tries to set focus on a node which is not a close trigger
   * if no other nodes exist then focuses on first close trigger
   */
  function setFocusToFirstNode() {
    var focusableNodes = getFocusableNodes(_modal.get(0));

    // no focusable nodes
    if (focusableNodes.length === 0) return;

    // remove nodes on whose click, the modal closes
    var nodesWhichAreNotCloseTargets = focusableNodes.filter(function nodeFilter(node) {
      return !node.hasAttribute("data-lightbox-close") && (
        !node.hasAttribute("data-dismiss") ||
        node.getAttribute("data-dismiss") !== "modal"
      );
    });

    if (nodesWhichAreNotCloseTargets.length > 0) {
      nodesWhichAreNotCloseTargets[0].focus();
    }
    if (nodesWhichAreNotCloseTargets.length === 0) {
      focusableNodes[0].focus();
    }
  }

  function retainFocus(event) {
    var focusableNodes = getFocusableNodes(_modal.get(0));

    // no focusable nodes
    if (focusableNodes.length === 0) return;

    /**
     * Filters nodes which are hidden to prevent
     * focus leak outside modal
     */
    focusableNodes = focusableNodes.filter(function nodeHiddenFilter(node) {
      return (node.offsetParent !== null);
    });

    // if disableFocus is true
    if (!VuFind.lightbox.contains(document.activeElement)) {
      focusableNodes[0].focus();
    } else {
      var focusedItemIndex = focusableNodes.indexOf(document.activeElement);

      if (event.shiftKey && focusedItemIndex === 0) {
        focusableNodes[focusableNodes.length - 1].focus();
        event.preventDefault();
      }

      if (
        !event.shiftKey &&
        focusableNodes.length > 0 &&
        focusedItemIndex === focusableNodes.length - 1
      ) {
        focusableNodes[0].focus();
        event.preventDefault();
      }
    }
  }
  function onKeydown(event) {
    if (event.keyCode === 27) { // esc
      close();
    }
    if (event.keyCode === 9) { // tab
      retainFocus(event);
    }
  }
  function bindFocus() {
    document.addEventListener('keydown', onKeydown);
    setFocusToFirstNode();
  }
  function unbindFocus() {
    document.removeEventListener('keydown', onKeydown);
  }

  function loadConfiguredLightbox() {
    if (VuFind.lightbox.child) {
      // remove lightbox reference
      let lightboxChild = VuFind.lightbox.child;
      VuFind.lightbox.child = null;
      let url = new URL(window.location.href);
      url.searchParams.delete('lightboxChild');
      window.history.replaceState({}, document.title, url.toString());

      // load lightbox
      _currentUrl = lightboxChild;
      var obj = {
        url: lightboxChild
      };
      ajax(obj);
      VuFind.modal('show');
    }
  }

  // Element which to focus after modal is closed
  var _beforeOpenElement = null;
  function reset() {
    _html('');
    _originalUrl = false;
    _currentUrl = false;
    _lbReferrerUrl = false;
    _lightboxTitle = false;
    _modalParams = {};
  }

  function init() {
    _modal = $('#modal');
    _modalBody = _modal.find('.modal-body');
    _modal.on('hide.bs.modal', function lightboxHide() {
      if (refreshOnClose) {
        VuFind.refreshPage();
      } else {
        if (_beforeOpenElement) {
          _beforeOpenElement.focus();
          _beforeOpenElement = null;
        }
        unbindFocus();
        this.setAttribute('aria-hidden', true);
        VuFind.emit('lightbox.closing');
      }
    });
    _modal.on('hidden.bs.modal', function lightboxHidden() {
      VuFind.lightbox.reset();
      VuFind.emit('lightbox.closed');
    });
    _modal.on("shown.bs.modal", function lightboxShown() {
      bindFocus();

      // Disable bootstrap-accessibility.js "enforceFocus" events.
      // retainFocus() above handles it better.
      // This is moot once that library (and bootstrap3) are retired.
      var focEls = _modal.find(":tabbable");
      var firstEl = $(focEls[0]);
      var lastEl = $(focEls[focEls.length - 1]);
      $(firstEl).add(lastEl).off('keydown.bs.modal');
    });

    VuFind.modal = function modalShortcut(cmd) {
      if (cmd === 'show') {
        _beforeOpenElement = document.activeElement;
        _modal.modal($.extend({ show: true }, _modalParams)).attr('aria-hidden', false);
        // Set keyboard focus
        setFocusToFirstNode();
      } else {
        _modal.modal(cmd);
      }
    };

    // Constrain Listeners

    document.addEventListener("click", function lighboxConstrainClick(event) {
      if (event.target.hasAttribute("data-lightbox-image")) {
        return lightboxOpenImage(event);
      }

      const link = event.target.closest("a");
      if (link !== null) {
        return _constrainLink(event);
      }

      const button = event.target.closest(`[type="button"], [type="submit"], [form]`);
      if (button !== null) {
        _clickedButton = button;
        return _constrainForm(event);
      }
    });

    document.addEventListener("submit", _constrainForm);

    // Default link constraint
    VuFind.listen("lightbox.link", ({ link, cancel }) => {
      // Invalid or non-applicable links

      const urlRoot = location.origin + VuFind.path;
      const href = link.getAttribute("href");
      const reResourceLink = new RegExp("^[a-z]+:[^/]", "i");

      if (
        href === null // invalid link
        || href.charAt(0) === "#" // anchor to same page
        || reResourceLink.test(href) // ignore resource identifiers (mailto:, tel:, etc.)
        || (
          href.startsWith("http") // external links
          && href.indexOf(urlRoot) === -1
        )
      ) {
        cancel();
        return;
      }

      // Link set to target a new tab/window

      const target = link.getAttribute("target");
      const reNewTarget = new RegExp("blank|new", "i");

      if (
        target !== null && reNewTarget
        && reNewTarget.test(target)
      ) {
        cancel();
      }
    });

    loadConfiguredLightbox();
  }

  // Reveal
  return {
    // Properties
    refreshOnClose: refreshOnClose,
    parent: parent,
    child: child,

    // Methods
    ajax: ajax,
    alert: showAlert,
    // bind: bind,
    close: close,
    contains: contains,
    flashMessage: flashMessage,
    reload: reload,
    render: render,
    // Reset
    reset: reset,
    // Init
    init: init
  };
});
