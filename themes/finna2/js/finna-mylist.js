/*global VuFind, finna, SimpleMDE */
finna.myList = (function finnaMyList() {

  var editor = null;
  var editableSettings = {'minWidth': 200, 'addToHeight': 100};
  var save = false;
  var listUrl = null;
  var refreshLists = null;
  var truncateField = '<div class="truncate-field" data-rows="1" data-row-height="5" markdown="1">';
  var truncateTag = '[[more]]';

  function getEditorCursorPos(mdeditor) {
    var doc = mdeditor.codemirror.getDoc();
    var cursorPos = doc.getCursor();
    var position = {
      line: cursorPos.line,
      ch: cursorPos.ch
    };
    return position;
  }

  function insertElement(element, mdeditor) {
    var doc = mdeditor.codemirror.getDoc();
    doc.replaceRange(element, getEditorCursorPos(mdeditor));
    mdeditor.codemirror.focus();
  }

  function toggleTruncateField(mdeditor) {
    var value = mdeditor.value();
    if (value.indexOf(truncateTag) !== -1) {
      return;
    } else {
      var moreTag = '\n' + truncateTag + '\n';
      insertElement(moreTag, mdeditor);
    }
  }

  function insertDetails(mdeditor) {
    var summaryPlaceholder = VuFind.translate('details_summary_placeholder');
    var detailsElement = '\n<details class="favorite-list-details" markdown="1">' +
     '<summary markdown="1">' + summaryPlaceholder + '</summary>\n' +
     VuFind.translate('details_text_placeholder') + '\n' +
     '</details>';

    insertElement(detailsElement, mdeditor);
    var doc = editor.codemirror.getDoc();
    var cursorPos = getEditorCursorPos(editor);
    var summaryAndPlaceholder = '<summary>' + summaryPlaceholder;
    doc.setCursor({line: cursorPos.line - 1, ch: summaryAndPlaceholder.length});
  }

  var mdeToolbar = [
    'bold', 'italic',
    'heading', '|',
    'quote', 'unordered-list',
    'ordered-list', '|',
    'link', 'image',
    '|',
    {
      name: 'Details',
      action: function detailsInsert(mdeditor) {
        insertDetails(mdeditor);
      },
      className: 'fa details-icon',
      title: 'Insert details element'
    },
    {
      name: 'truncate',
      action: function truncateFieldToggle(mdeditor) {
        toggleTruncateField(mdeditor);
      },
      className: 'fa fa-pagebreak',
      title: 'Truncate'
    }
  ];

  function initDetailsElements() {
    $('.favorite-list-details').click(function onDetailsClick() {
      if ($(this).attr('open') === 'open') {
        $(this).attr('open', false);
      } else {
        $(this).attr('open', 'open');
      }
    });
  }

  // This is duplicated in image-popup.js to avoid dependency
  function getActiveListId() {
    return $('input[name="listID"]').val();
  }

  function onCustomOrderSaved(/*ev, data*/) {
    location.href = listUrl;
  }

  function toggleErrorMessage(mode) {
    var $msg = $('.mylist-error');
    $msg.addClass('alert alert-danger');
    $msg.toggleClass('hidden', !mode);
    if (mode) {
      $('html, body').animate({ scrollTop: 0 }, 'fast');
    }
  }

  function toggleSpinner(target, mode) {
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

  function handleTruncateField(description, addTruncate) {
    var trunc = typeof addTruncate !== 'undefined' ? addTruncate : true;
    var desc = description;
    if (trunc && description.indexOf(truncateTag) !== -1) {
      desc = description.replace(truncateTag, truncateField);
      desc += '</div>';
    } else if (description.indexOf(truncateField) !== -1) {
      // replace <div class="truncate-field"...> with [[more]] and
      // get rid of the closing tag
      desc = description.replace(truncateField, truncateTag);
      desc = desc.substr(0, desc.length - 6);
    }
    return desc;
  }

  function updateList(params, callback, type) {
    save = true;
    var spinner = null;

    var listParams = {
      'id': getActiveListId(),
      'title': $('.list-title span').text(),
      'public': $(".list-visibility input[type='radio']:checked").val()
    };

    if (type !== 'add-list') {
      var description = $('.list-description .editable').data('markdown');
      description = handleTruncateField(description);
      if (description === VuFind.translate('add_list_description')) {
        listParams.desc = '';
      } else {
        listParams.desc = description;
      }
    }

    if (type === 'title') {
      spinner = $('.list-title .fa');
    } else if (type === 'desc') {
      spinner = $('.list-description .fa:not(.fa-arrow-down)');
    } else if (type === 'add-list') {
      spinner = $('.add-new-list .fa');
    } else if (type === 'visibility') {
      var holder = $('.list-visibility > div:first');
      holder.hide().after('<i class="fa fa-spinner fa-spin"></i>');
    }

    if (spinner) {
      toggleSpinner(spinner, true);
    }

    toggleErrorMessage(false);
    if (typeof(params) !== 'undefined') {
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
      .done(function onEditListDone(data/*, status, jqXHR*/) {
        if (spinner) {
          toggleSpinner(spinner, false);
        }
        if (callback != null) {
          callback(data.data);
        }
        save = false;
      })
      .fail(function onEditListFail() {
        toggleErrorMessage(true);
        toggleSpinner(spinner, false);
        save = false;
      });
  }

  function addResourcesToList(listId) {
    toggleErrorMessage(false);

    var ids = [];
    $('input.checkbox-select-item[name="ids[]"]:checked').each(function processRecordId() {
      var recId = $(this).val();
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
      data: {params: {'listId': listId, 'source': 'Solr', 'ids': ids}}
    })
      .done(function onAddToListDone(/*data*/) {
        // Don't reload to avoid trouble with POST requests
        location.href = location.href;
      })
      .fail(function onAddToListFail() {
        toggleErrorMessage(true);
        $('#add-to-list-spinner').addClass('hidden');
        $('#add-to-list').removeAttr('disabled');
        $('#add-to-list').val('');
      });
  }

  function toggleTitleEditable(mode) {
    var target = $('.list-title span');
    var currentTitle;
    if (mode) {
      // list title
      var titleCallback = {
        start: function titleEditStart(/*e*/) {
          if (editor) {
            // Close active editor
            $(document).trigger('click');
            return;
          }
          currentTitle = target.find('input').val();
        },
        finish: function titleEditFinish(e) {
          if (typeof(e) === 'undefined' || !e.cancel) {
            if (e.value === '') {
              target.text(currentTitle);
              return false;
            } else {
              updateList({title: e.value}, refreshLists, 'title');
            }
          }
        }
      };
      target.editable({action: 'click', triggers: [target, $('.list-title i')]}, titleCallback, editableSettings);
    } else {
      target.replaceWith(target.clone());
    }
    $('.list-title').toggleClass('disable', !mode);
  }

  function listDescriptionChanged() {
    var description = $('.list-description .editable');
    if (description.html() === '') {
      description.data('empty', '1');
      description.html(VuFind.translate('add_list_description'));
      $('input[name=listDescription]').val('');
    } else {
      description.data('empty', '0');
      $('input[name=listDescription]').val(description.data('markdown'));
    }
    toggleTitleEditable(true);
  }

  function newListAdded(data) {
    var title = data.title;
    var newTitle = (title.length > 20) ? title.substring(0, 20) + '...' : title;

    // update add-to-list select
    $('#add-to-list')
      .append($('<option></option>')
        .attr('value', data.id)
        .text(newTitle));

    refreshLists();
  }

  function updateBulkActionsToolbar() {
    var buttons = $('.bulk-action-buttons-col');
    if ($(document).scrollTop() > $('.bulk-action-buttons-row').offset().top) {
      buttons.addClass('fixed');
    } else {
      buttons.removeClass('fixed');
    }
  }

  function updateListResource(params, input/*, row*/) {
    save = true;
    toggleErrorMessage(false);

    var parent = input.closest('.myresearch-notes');
    var spinner = parent.find('.fa-pen');
    toggleSpinner(spinner, true);

    $.ajax({
      type: 'POST',
      dataType: 'json',
      url: VuFind.path + '/AJAX/JSON?method=editListResource',
      data: {'params': params}
    })
      .done(function onEditListResourceDone(/*data*/) {
        if (spinner) {
          toggleSpinner(spinner, false);
        }

        var hasNotes = params.notes !== '';
        parent.find('.note-info').toggleClass('hide', !hasNotes);
        input.data('empty', hasNotes === '' ? '1' : '0');
        if (!hasNotes) {
          input.text(VuFind.translate('add_note'));
        }
        toggleTitleEditable(true);
        save = false;
      })
      .fail(function onEditListResourceFail() {
        toggleErrorMessage(true);
        toggleTitleEditable(true);
        save = false;
      });
  }

  function initEditableMarkdownField(element, callback) {
    element.find('.editable').unbind('click').click(function onClickEditable(e) {
      if (save) {
        // Do not open the editor when save is in progress.
        return;
      }

      if (!editor && e.target.nodeName === 'A') {
        // Do not open the editor when a link within the editable area was clicked.
        e.stopPropagation();
        return;
      }

      if (editor) {
        // Close active editor
        $(document).trigger('click');
        return;
      }

      toggleTitleEditable(false);

      element.toggleClass('edit', true);
      var container = element.find('.editable');

      var textArea = $('<textarea/>');
      var currentVal = null;
      currentVal = container.data('markdown');
      currentVal = handleTruncateField(currentVal, false);
      textArea.text(currentVal);
      container.hide();
      textArea.insertAfter(container);
      if (editor) {
        editor = null;
      }

      var editorSettings = {
        autoDownloadFontAwesome: false,
        autofocus: true,
        element: textArea[0],
        toolbar: mdeToolbar,
        spellChecker: false,
        status: false
      };

      editor = new SimpleMDE(editorSettings);
      currentVal = editor.value();

      if (currentVal.indexOf(truncateTag) !== -1) {
        $('.fa-pagebreak').addClass('pagebreak-toggled');
      }
      // Preview
      var html = SimpleMDE.prototype.markdown(editor.value());
      html = handleTruncateField(html);
      $('.markdown-preview').remove();
      var preview = $('<div/>').addClass('markdown-preview')
        .html($('<div/>').addClass('data').html(html));
      $('<div/>').addClass('preview').text(VuFind.translate('preview').toUpperCase()).prependTo(preview);
      preview.appendTo(element);
      finna.layout.initTruncate(preview);
      initDetailsElements();

      editor.codemirror.on('change', function onChangeEditor() {
        var result = SimpleMDE.prototype.markdown(editor.value());
        if (result.indexOf(truncateTag) !== -1) {
          if (!$('.fa-pagebreak').hasClass('pagebreak-toggled')) {
            $('.fa-pagebreak').addClass('pagebreak-toggled');
          }
        } else {
          $('.fa-pagebreak').removeClass('pagebreak-toggled');
        }
        result = handleTruncateField(result);
        preview.find('.data').html(result);
        finna.layout.initTruncate(preview);
        initDetailsElements();
      });

      // Close editor and save when user clicks outside the editor
      $(document).one('click', function onClickDocument() {
        var markdown = editor.value();
        var resultHtml = SimpleMDE.prototype.markdown(markdown);

        editor.toTextArea();
        editor = null;
        element.toggleClass('edit', false).find('textarea').remove();

        container.show();
        container.data('markdown', markdown);
        container.data('empty', (markdown.length === 0 ? '1' : '0'));
        resultHtml = handleTruncateField(resultHtml);
        container.html(resultHtml);
        finna.layout.initTruncate(container);
        preview.remove();

        callback(markdown);
      });
      $('.CodeMirror-code').focus();
      // Prevent clicks within the editor area from bubbling up and closing the editor.
      element.closest('.markdown').unbind('click').click(function onClickEditor() {
        return false;
      });
    });
  }

  function initEditComponents() {
    var isDefaultList = typeof(getActiveListId()) == 'undefined';

    // bulk actions
    var buttons = $('.bulk-action-buttons-col');
    if (buttons.length) {
      $(window).on('scroll', function onScrollWindow() {
        updateBulkActionsToolbar();
      });
      updateBulkActionsToolbar();
    }

    //Init mobile navigation collapse after list has been reloaded
    finna.layout.initMobileNarrowSearch();

    // Checkbox select all
    $('.mylist-controls-bar .checkbox-select-all').unbind('change').change(function onChangeSelectAll() {
      $('.myresearch-row .checkbox-select-item').prop('checked', $(this).is(':checked'));
    });

    if (!isDefaultList) {
      toggleTitleEditable(true);

      initEditableMarkdownField($('.list-description'), function onDoneEditDescription(/*markdown*/) {
        updateList({}, listDescriptionChanged, 'desc');
      });

      // list visibility
      $(".list-visibility input[type='radio']").unbind('change').change(function onChangeVisibility() {
        updateList({}, refreshLists, 'visibility');
      });

      // delete list
      var active = $('.mylist-bar').find('a.active');
      active.find('.remove').unbind('click').click(function onClickRemove(e) {
        var target = $(this);
        var form = $('.delete-list');
        var prompt = form.find('.dropdown-menu');

        function repositionPrompt() {
          var pos = target.offset();
          var left = ($(window).width() / 2) - (prompt.width() / 2);

          prompt.css({
            'left': left,
            'top': pos.top + 30
          });
        }

        function initRepositionListener() {
          $(window).resize(repositionPrompt);
        }

        prompt.find('.confirm').unbind('click').click(function onClickConfirm(ev) {
          form.submit();
          ev.preventDefault();
        });
        prompt.find('.cancel').unbind('click').click(function onClickCancel(ev) {
          $(window).off('resize', repositionPrompt);
          prompt.hide();
          $('.remove-favorite-list').focus();
          ev.preventDefault();
        });

        repositionPrompt();
        initRepositionListener();
        prompt.show();
        prompt.find('.confirm a').focus();
        e.preventDefault();
      });
    }

    $('.add-new-list .icon').on('click', function createNewList() {
      var newListInput = $('.new-list-input');
      var newListName = newListInput.val().trim();

      if (newListName !== '') {
        newListInput.off('keyup');
        $(this).off('click');
        updateList({'id': 'NEW', 'title': newListName, 'desc': null, 'public': 0}, newListAdded, 'add-list');
      }
    });

    //Add new list, listen for keyup enter
    $(".new-list-input").on('keyup', function invokeCreateNewList(e) {
      if (e.keyCode === 13) {
        $('.add-new-list .icon').click();
      }
    });

    $('.myresearch-row').each(function initNoteEditor(ind, obj) {
      var editField = $(obj).find('.myresearch-notes .resource-note');
      initEditableMarkdownField(editField, function onMarkdownEditDone(markdown) {
        var row = editField.closest('.myresearch-row');
        var id = row.find('.hiddenId').val();
        var listId = getActiveListId();

        updateListResource(
          {'id': id, 'listId': listId, 'notes': markdown},
          editField.find('> div')
        );
      });
    });

    // add resource to list
    $('.mylist-functions #add-to-list').unbind('change').change(function onChangeAddToList(/*e*/) {
      var val = $(this).val();
      if (val !== '') {
        addResourcesToList(val);
      }
    });

    function adjustNoteOverlaySize(noteOverlay) {
      var container = noteOverlay.closest('.grid-body');
      var coverContainer = container.find('.grid-image');
      var imageWidth = coverContainer.width();
      var imageHeight = Math.min(container.find('.grid-title').position().top, container.find('.record-image-container').height());
      noteOverlay.height(imageHeight);
      noteOverlay.width(imageWidth);
    }

    // hide/show notes on images
    $('.notes').not(':data(inited)').each(function initNotes() {
      $(this).data('inited', '1');
      var noteButton = $(this).closest('.grid-body').find('.note-button');
      var noteOverlay = $(this).closest('.grid-body').find('.note-overlay');
      noteButton.click(function onClick() {
        adjustNoteOverlaySize(noteOverlay);
        if (!noteOverlay.hasClass('note-show')) {
          noteButton.addClass('note-show');
          noteOverlay.addClass('note-show');
        } else {
          noteButton.removeClass('note-show');
          noteOverlay.removeClass('note-show');
        }
      });
    });

    // Prompt before leaving page if Ajax load is in progress
    window.onbeforeunload = function onBeforeUnloadWindow(/*e*/) {
      if ($('.list-save').length) {
        return VuFind.translate('loading') + '...';
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
        $('.mylist-bar').html(data.data);
        initEditComponents();
      })
      .fail(function onGetMyListsDone() {
        toggleSpinner(spinner, false);
        toggleErrorMessage(true);
      });
  };

  function initFavoriteOrderingFunctionality(url) {
    listUrl = url;

    $('#sortable').sortable({cursor: 'move', opacity: 0.7});

    $('#sort_form').submit(function onSubmitSortForm(/*event*/) {
      var listOfItems = $('#sortable').sortable('toArray');
      $('#sort_form input[name="orderedList"]').val(JSON.stringify(listOfItems));
      return true;
    });
  }

  var my = {
    onCustomOrderSaved: onCustomOrderSaved,
    initFavoriteOrderingFunctionality: initFavoriteOrderingFunctionality,
    init: function init() {
      initEditComponents();
    }
  };

  return my;
})();
