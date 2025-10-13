/*global VuFind, finna, Sortable */
finna.myList = (function finnaMyList() {

  var mdEditable = null;
  var editableSettings = {'minWidth': 200, 'addToHeight': 100};
  var save = false;
  var refreshLists = null;

  /**
   * Get current active list id
   * @returns {string} Active list id
   */
  function getActiveListId() {
    return $('input[name="listID"]').val();
  }

  /**
   * Toggle an error message
   * @param {boolean} mode Should the message be shown
   */
  function toggleErrorMessage(mode) {
    var $msg = $('.mylist-error');
    $msg.addClass('alert alert-danger');
    $msg.toggleClass('hidden', !mode);
    if (mode) {
      $('html, body').animate({scrollTop: 0}, 'fast');
    }
  }

  /**
   * Toggle spinner
   * @param {jQuery | HTMLElement} target jQuery element or FinnaMdEditable
   * @param {boolean} mode Should the spinner be displayed
   */
  function toggleSpinner(target, mode) {
    if (target === mdEditable) {
      mdEditable.setBusy(!mdEditable.isBusy());
      return;
    }
    if (mode) {
      // save original classes to a data-attribute
      target.data('class', target.attr('class'));
      // remove pen, plus
      target.toggleClass('fa-pen fa-plus-small', false);
    } else {
      target.attr('class', target.data('class'));
    }
    // spinner
    target.toggleClass('fa-spinner fa-spin list-save', mode);
  }

  /**
   * Update a list entity
   * @param {object} params List params as object
   * @param {Function} callback Function to call after edit list is successful
   * @param {string} type Type of the update list update method
   */
  function updateList(params, callback, type) {
    save = true;
    var spinner = null;

    var listParams = {
      'id': getActiveListId(),
      'title': $('.js-list-title').text(),
      'public': $(".list-visibility input[type='radio']:checked").val()
    };

    if (type !== 'add-list') {
      var description = $('.list-description [data-markdown]').data('markdown');
      if (description === VuFind.translate('add_list_description')) {
        listParams.desc = '';
      } else {
        listParams.desc = description;
      }

      var tags = $('.list-tags .edit-tags .tags .tag .text');
      var listTags = [];
      if (tags.length) {
        tags.each(function extractTag(ind, tag) {
          listTags.push($(tag).data('tag'));
        });
      }

      if (type === 'add-tag') {
        var newTag = $('.list-tags .new-tag');
        listTags.push(newTag.val());
      } else if (type === 'delete-tag') {
        if (!listTags.length) {
          listTags = [''];
        }
      }
      listParams.tags = listTags;
    }

    if (type === 'title') {
      spinner = $('.list-title .fa');
    } else if (type === 'desc') {
      spinner = mdEditable;
    } else if (type === 'add-tag' || type === 'delete-tag') {
      $('.list-tags form fieldset').attr('disabled', 'disabled');
      $('.list-tags .fa-spinner').toggleClass('hide', false).show();
    } else if (type === 'add-list') {
      spinner = $('.add-new-list .fa');
    } else if (type === 'visibility') {
      var holder = $('.list-visibility > div').first();
      holder.hide().after(VuFind.icon('spinner'));
    }

    if (spinner) {
      toggleSpinner(spinner, true);
    }

    toggleErrorMessage(false);
    if (typeof params !== 'undefined') {
      $.each(params, function setListParam(key, val) {
        listParams[key] = val;
      });
    }

    $.ajax({
      type: 'POST',
      dataType: 'json',
      url: VuFind.path + '/AJAX/JSON?method=editList',
      data: {'params': listParams}
    })
      .done(function onEditListDone(data /*, status, jqXHR*/) {
        if (spinner) {
          toggleSpinner(spinner, false);
        }
        if (callback != null) {
          callback(data.data);
        }
        save = false;
        mdEditable = null;
      })
      .fail(function onEditListFail() {
        toggleErrorMessage(true);
        toggleSpinner(spinner, false);
        save = false;
        mdEditable = null;
      });
  }

  /**
   * Add resources to a list
   * @param {string} listId Id of the list
   * @param {string} currentListId Current list id
   */
  function addResourcesToList(listId, currentListId = '') {
    toggleErrorMessage(false);

    var ids = [];
    VuFind.listItemSelection.getAllSelected(document.querySelector('form[name="bulkActionForm"]')).forEach(recId => {
      var pos = recId.indexOf('|');
      var source = recId.substring(0, pos);
      var id = recId.substring(pos + 1);
      ids.push([source, id]);
    });
    if (!ids.length) {
      return;
    }

    // replace list-select with spinner
    $('#add-to-list').attr('disabled', 'disabled');
    $('#add-to-list-spinner').removeClass('hidden');
    $.ajax({
      type: 'POST',
      dataType: 'json',
      url: VuFind.path + '/AJAX/JSON?method=addToList',
      data: {params: {'listId': listId, 'currentListId': currentListId, 'source': 'Solr', 'ids': ids}}
    })
      .done(function onAddToListDone(/*data*/) {
        // Don't reload to avoid trouble with POST requests
        location.href = String(location.href);
      })
      .fail(function onAddToListFail() {
        toggleErrorMessage(true);
        $('#add-to-list-spinner').addClass('hidden');
        $('#add-to-list').removeAttr('disabled');
        $('#add-to-list').val('');
      });
  }

  /**
   * Toggle title to be editable
   * @param {boolean} mode Should the title be editable
   */
  function toggleTitleEditable(mode) {
    var target = $('.js-list-title');
    var currentTitle;
    if (mode) {
      // list title
      var titleCallback = {
        start: function titleEditStart(/*e*/) {
          if (mdEditable) {
            // Close active editable
            mdEditable.closeEditable();
            return;
          }
          currentTitle = target.find('input').val();
        },
        finish: function titleEditFinish(e) {
          if (typeof e === 'undefined' || !e.cancel) {
            if (e.value === '') {
              target.text(currentTitle);
              return false;
            } else {
              updateList({title: e.value}, refreshLists, 'title');
            }
          }
        }
      };
      target.editable({action: 'click', triggers: [target, $('.list-title .icon')]}, titleCallback, editableSettings);
    } else {
      target.replaceWith(target.clone());
    }
    $('.list-title').toggleClass('disable', !mode);
  }

  /**
   * List description changed handler
   * @param {object} data Object containing descHtml, desc
   */
  function listDescriptionChanged(data) {
    var description = $('.list-description [data-markdown]');
    if (data.desc === '') {
      $('input[name=listDescription]').val('');
    } else {
      if (typeof data.descHtml !== 'undefined' && data.descHtml !== '') {
        description.html(data.descHtml);
        VuFind.truncate.initTruncate(description.find('.finna-truncate'));
      }
      $('input[name=listDescription]').val(data.desc);
    }
    toggleTitleEditable(true);
  }

  // fixes jshint error from using initListTagComponent before it's defined.
  var initListTagComponent;

  /**
   * List tags changed handler
   * @param {object} data Object containing tags-edit, tags
   */
  function listTagsChanged(data) {
    $('.list-tags .edit-tags .tags').html(data['tags-edit']);
    $('.list-tags .view-tags').html(data.tags);
    $('.list-tags .new-tag').val('');
    $('.list-tags form fieldset').attr('disabled', false);
    $('.list-tags .fa-spinner').hide();
    initListTagComponent();
  }

  initListTagComponent = function _initListTagComponent() {
    $('.list-tags form').off('submit').on('submit', function onSubmitAddListTagForm(/*event*/) {
      updateList({}, listTagsChanged, 'add-tag');
      return false;
    });
    $('.list-tags .edit-tags .tags .tag .delete-tag').off('click').on('click', function onDeleteTag(/*event*/) {
      $('.list-tags form fieldset').attr('disabled', 'disabled');
      $(this).closest('.tag').remove();
      updateList({}, listTagsChanged, 'delete-tag');
    });
    $('.list-tags .toggle').off('click').on('click', function onToggleTags(/*event*/) {
      $('.list-tags').toggleClass('editable');
    });
  };

  /**
   * New list added handler
   * @param {object} data Object containing title, id
   */
  function newListAdded(data) {
    var title = data.title;
    var newTitle = title.length > 20 ? title.substring(0, 20) + '...' : title;

    // update add-to-list select
    $('#add-to-list')
      .append($('<option></option>')
        .attr('value', data.id)
        .text(newTitle));

    refreshLists();
  }


  /**
   * Update list resource
   * @param {object} params Params for ajax edit list resource
   * @param {jQuery} input Input element to find notes from
   */
  function updateListResource(params, input /*, row*/) {
    save = true;
    toggleErrorMessage(false);

    var parent = input.closest('.myresearch-notes');
    toggleSpinner(mdEditable);

    $.ajax({
      type: 'POST',
      dataType: 'json',
      url: VuFind.path + '/AJAX/JSON?method=editListResource',
      data: {'params': params}
    })
      .done(function onEditListResourceDone(data) {
        toggleSpinner(mdEditable);

        var hasNotes = params.notes !== '';
        parent.find('.note-info').toggleClass('hide', !hasNotes);
        if (hasNotes
          && typeof data.data.notesHtml !== 'undefined'
          && data.data.notesHtml !== '')
        {
          input.html(data.data.notesHtml);
          VuFind.truncate.initTruncate(input.find('.finna-truncate'));
        }
        toggleTitleEditable(true);
        save = false;
        mdEditable = null;
      })
      .fail(function onEditListResourceFail() {
        toggleErrorMessage(true);
        toggleTitleEditable(true);
        save = false;
        mdEditable = null;
      });
  }

  /**
   * Initialize edit components for list
   */
  function initEditComponents() {
    var isDefaultList = typeof getActiveListId() == 'undefined';

    //Init mobile navigation collapse after list has been reloaded
    finna.layout.initMobileNarrowSearch();

    if (!isDefaultList) {
      toggleTitleEditable(true);

      // list tags
      initListTagComponent();

      // list visibility
      $(".list-visibility input[type='radio']").off('change').on("change", function onChangeVisibility() {
        updateList({}, refreshLists, 'visibility');
      });
    }

    $('#add-new-list-item-btn').on('click', function createNewList() {
      var newListInput = $('.new-list-input');
      var newListName = newListInput.val().trim();

      if (newListName !== '') {
        newListInput.off('keyup');
        $(this).off('click');
        updateList({'id': 'NEW', 'title': newListName, 'desc': null, 'public': 0}, newListAdded, 'add-list');
      }
    });
    $('#add-new-list-item-btn').on('keyup', function invokeCreateNewList(e) {
      if (e.keyCode === 32) {
        $('#add-new-list-item-btn').trigger("click");
      }
    });

    //Add new list, listen for keyup enter
    $('.new-list-input').on('keyup', function invokeCreateNewList(e) {
      if (e.keyCode === 13) {
        $('#add-new-list-item-btn').trigger("click");
      }
    });

    // add resource to list
    $('.mylist-functions #add-to-list').off('change').on("change", function onChangeAddToList(/*e*/) {
      var val = $(this).val();
      if (val !== '') {
        addResourcesToList(val, getActiveListId());
      }
    });

    // hide/show notes on images
    $('.note-button:not(.inited)').each(function initNotes() {
      var btn = $(this);
      var noteOverlay = btn.siblings('.note-overlay-grid, .note-overlay-condensed').first();
      btn.off('click').on('click', function onClick(e) {
        e.stopPropagation();
        btn.add(noteOverlay).toggleClass('note-show', !btn.hasClass('note-show'));
      }).addClass('inited');
    });

    // Prompt before leaving page if Ajax load is in progress
    window.onbeforeunload = function onBeforeUnloadWindow(/*e*/) {
      if ($('.list-save').length) {
        return VuFind.translate('loading_ellipsis');
      }
    };
  }

  refreshLists = function refreshListsFunc(/*data*/) {
    toggleErrorMessage(false);

    var spinner = $('.add-new-list .fa');
    toggleSpinner(spinner, true);
    $.ajax({
      type: 'POST',
      dataType: 'json',
      url: VuFind.path + '/AJAX/JSON?method=getMyLists',
      data: {'active': getActiveListId()}
    })
      .done(function onGetMyListsDone(data) {
        toggleSpinner(spinner, false);
        $('.mylist-bar').empty().html(data.data);
        initEditComponents();
      })
      .fail(function onGetMyListsDone() {
        toggleSpinner(spinner, false);
        toggleErrorMessage(true);
      });
  };

  /**
   * Initialize favorite ordering functionality
   */
  function initFavoriteOrderingFunctionality() {
    var el = document.getElementById('sortable');
    var sortable = Sortable.create(el);
    $('#sort_form').on('submit', function onSubmitSortForm(/*event*/) {
      var list = [];
      var children = sortable.el.children;
      if (children.length > 0) {
        for (var i = 0; i < children.length; i++) {
          list.push(children[i].id);
        }
      }
      this.querySelector('input[name="orderedList"]').value = JSON.stringify(list);
      return true;
    });
  }

  /**
   * Initialize listeners when editable opens
   */
  function initListeners() {
    $(document).on('finna:openEditable', function onOpenEditable(event) {
      if (event.editable.element.hasClass('list-description')
        || event.editable.element.hasClass('resource-note')
      ) {
        if (save) {
          // Do not open the editable when save is in progress.
          return false;
        }

        if (mdEditable) {
          // Close active editable
          mdEditable.closeEditable();
          return false;
        }

        toggleTitleEditable(false);

        mdEditable = event.editable;
      }
    });

    $(document).on('click', function onClickDocument(/*event*/) {
      // Close editable and save when user clicks outside the editable
      if (mdEditable) {
        mdEditable.closeEditable();
      }
    });

    $(document).on('finna:editableClosed', function onEditableClosed(event) {
      if (event.editable.element.hasClass('list-description')) {
        updateList({}, listDescriptionChanged, 'desc');
      }
      else if (event.editable.element.hasClass('resource-note')) {
        var markdown = event.editable.container.data('markdown');
        var row = event.editable.element.closest('.myresearch-row');
        var id = row.find('.hiddenId').val();
        var source = row.find('.hiddenSource').val();
        var listId = getActiveListId();

        updateListResource(
          {'id': id, 'source': source, 'listId': listId, 'notes': markdown},
          event.editable.container
        );
      }
    });
  }

  /**
   * Initialize multi page selection events
   */
  function initMultiPageSelection() {
    const favoriteForm = document.querySelector('form[name=bulkActionForm]');
    if (!favoriteForm) {
      return;
    }
    const updateFunctionButtons = function updateButtonStatesFunc() {
      var actions = $('.mylist-functions button, .mylist-functions select');
      var aria = $('.mylist-functions .visually-hidden');
      var noneChecked = VuFind.listItemSelection.getAllSelected(favoriteForm).length === 0;
      if (noneChecked) {
        actions.attr('disabled', true);
        aria.removeAttr('aria-hidden');
      } else {
        actions.removeAttr('disabled');
        aria.attr('aria-hidden', 'true');
      }
    };
    const inputSelector = `.template-name-mylist .selection-controls-bar input,
      .template-name-mylist input.checkbox-select-item,
      .template-dir-reservationlist.template-name-displaylist input.checkbox-select-item,
      .template-dir-reservationlist.template-name-displaylist .selection-controls-bar input
    `;
    document.querySelectorAll(inputSelector).forEach(el => el.addEventListener('change', updateFunctionButtons));
    const clearButton = document.querySelector('.template-name-mylist .clear-selection');
    if (clearButton) {
      clearButton.addEventListener('click', updateFunctionButtons);
    }

    updateFunctionButtons();
  }

  var my = {
    initFavoriteOrderingFunctionality: initFavoriteOrderingFunctionality,
    init: function init() {
      initEditComponents();
      initListeners();
      initMultiPageSelection();
    }
  };

  return my;
})();
