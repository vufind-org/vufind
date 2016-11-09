/*global VuFind*/
finna.myList = (function() {

    var addNewListLabel = null;
    var editor = null;
    var editableSettings = {'minWidth': 200, 'addToHeight': 100};
    var save = false;

    // This is duplicated in image-popup.js to avoid dependency
    var getActiveListId = function() {
        return $('input[name="listID"]').val();
    };

    var updateList = function(params, callback, type) {
        save = true;
        var spinner = null;

        var listParams = {
            'id': getActiveListId(),
            'title': $('.list-title span').text(),
            'public': $(".list-visibility input[type='radio']:checked").val()
        };

        if (type != 'add-list') {
            var description = $('.list-description .editable').data('markdown');
            if (description == VuFind.translate('add_list_description')) {
                listParams['desc'] = '';
            } else {
                listParams['desc'] = description;
            }
        }

        if (type == 'title') {
            spinner = $('.list-title .fa');
        } else if (type == 'desc') {
            spinner = $('.list-description .fa');
        } else if (type == 'add-list') {
            spinner = $('.add-new-list .fa');
        } else if (type == 'visibility') {
            var holder = $(".list-visibility > div:first");
            holder.hide().after(
                '<i class="fa fa-spinner fa-spin"></i>'
            );
        }

        if (spinner) {
            toggleSpinner(spinner, true);
        }

        toggleErrorMessage(false);
        if (typeof(params) !== 'undefined') {
            $.each(params, function(key, val) {
                listParams[key] = val;
            });
        }

        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: VuFind.path + '/AJAX/JSON?method=editList',
            data: {'params': listParams}
        })
        .done(function(data, status, jqXHR) {
            if (type != 'add-list' && spinner) {
                toggleSpinner(spinner, false);
            }
            if (callback != null) {
                callback(data.data);
            }
            save = false;
        })
        .fail(function() {
            toggleErrorMessage(true);
            save = false;
        });
    };

    var updateListResource = function(params, input, row) {
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
        .done(function(data) {
            if (spinner) {
                toggleSpinner(spinner, false);
            }

            var hasNotes = params.notes != '';
            parent.find('.note-info').toggleClass('hide', !hasNotes);
            input.data('empty', hasNotes == '' ? '1' : '0');
            if (!hasNotes) {
                input.text(VuFind.translate('add_note'));
            }
            toggleTitleEditable(true);
            save = false;
        })
        .fail(function() {
            toggleErrorMessage(true);
            toggleTitleEditable(true);
            save = false;
        });
    };

    var addResourcesToList = function(listId) {
        toggleErrorMessage(false);

        var ids = [];
        $('input.checkbox-select-item[name="ids[]"]:checked').each(function() {
            var recId = $(this).val();
            var pos = recId.indexOf('|');
            var source = recId.substring(0, pos);
            var id = recId.substring(pos+1);
            ids.push([source,id]);
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
        .done(function(data) {
            // Don't reload to avoid trouble with POST requests
            location.href = location.href;
        })
        .fail(function() {
            toggleErrorMessage(true);
            $('#add-to-list-spinner').addClass('hidden');
            $('#add-to-list').removeAttr('disabled');
            $('#add-to-list').val('');
        });
    };

    var refreshLists = function(data) {
        toggleErrorMessage(false);

        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: VuFind.path + '/AJAX/JSON?method=getMyLists',
            data: {'active': getActiveListId()}
        })
        .done(function(data) {
            $('.mylist-bar').html(data.data);
            initEditComponents();
        })
        .fail(function() {
            toggleErrorMessage(true);
        });
    };

    var listVisibilityChanged = function(data) {
        $('.mylist-bar .list-visibility .list-url').toggle(data['public'] === "1");
    };

    var listDescriptionChanged = function() {
        var description = $('.list-description .editable');
        if (description.html() == '') {
            description.data('empty', '1');
            description.html(VuFind.translate('add_list_description'));
        } else {
            description.data('empty', '0');
        }
        toggleTitleEditable(true);
    };

    var newListAdded = function(data) {
        // update add-to-list select
        $('#add-to-list')
            .append($('<option></option>')
                    .attr('value', data.id)
                    .text(data.title));

        refreshLists();
    };

    var updateBulkActionsToolbar = function() {
        var buttons = $('.bulk-action-buttons-col');
        if ($(document).scrollTop() > $('.bulk-action-buttons-row').offset().top) {
            buttons.addClass('fixed');
        } else {
            buttons.removeClass('fixed');
        }
    };


    var toggleTitleEditable = function(mode) {
        var target = $('.list-title span');
        var currentTitle;
        if (mode) {
            // list title
            var titleCallback = {
                start: function(e) {
                    if (editor) {
                        // Close active editor
                        $(document).trigger('click');
                        return;
                    }
                    currentTitle = target.find('input').val();
                },
                finish: function(e) {
                    if (typeof(e) === 'undefined' || !e.cancel) {
                        if (e.value == '') {
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
    };

    var initEditComponents = function() {
        addNewListLabel = $('.add-new-list div').text();
        var isDefaultList = typeof(getActiveListId()) == 'undefined';

        // bulk actions
        var buttons = $('.bulk-action-buttons-col');
        if (buttons.length) {
   	        $(window).on('scroll', function () {
                updateBulkActionsToolbar();
   	        });
            updateBulkActionsToolbar();
        }

        // Checkbox select all
        $('.checkbox-select-all').unbind('change').change(function() {
            $('.myresearch-row .checkbox-select-item').prop('checked', $(this).is(':checked'));
        });

        var settings = {'minWidth': 200, 'addToHeight': 100};

        if (!isDefaultList) {
            toggleTitleEditable(true);

            initEditableMarkdownField($('.list-description'), function(markdown) {
                updateList({}, listDescriptionChanged, 'desc');
            });

            // list visibility
            $(".list-visibility input[type='radio']").unbind('change').change(function() {
                updateList({}, refreshLists, 'visibility');
            });

            // delete list
            var active = $('.mylist-bar').find('a.active');
            active.find('.remove').unbind('click').click(function(e) {
                var target = $(this);
                var id = target.data('id');
                var form = $('.delete-list');
                var prompt = form.find('.dropdown-menu');

                var initRepositionListener = function() {
                    $(window).resize(repositionPrompt);
                };

                var repositionPrompt = function() {
                    var pos = target.offset();
                    prompt.css({
                        'left': pos.left-prompt.width()+target.width(),
                        'top': pos.top+30
                    });
                };

                prompt.find('.confirm').unbind('click').click(function(e) {
                    form.submit();
                    e.preventDefault();
                });
                prompt.find('.cancel').unbind('click').click(function(e) {
                    $(window).off('resize', repositionPrompt);
                    prompt.hide();
                    e.preventDefault();
                });

                repositionPrompt();
                initRepositionListener();
                prompt.show();
                e.preventDefault();
            });
        }

        // add new list
        var newListCallBack = {
            'start': function(e) {
                e.target.find('input').val('');
            },
            'finish': function(e) {
                if (e.value == '' || e.cancel) {
                    $('.add-new-list .name').text(addNewListLabel);
                    return;
                }

                if (e.value != '') {
                    updateList({'id': 'NEW', 'title': e.value, 'desc': null, 'public': 0}, newListAdded, 'add-list');
                }
            }
        };
        target = $('.add-new-list .name');
        if (target.length > 0) {
            target.editable({action: 'click', triggers: [target, $('.add-new-list .icon')]}, newListCallBack, editableSettings);
        }

        $('.myresearch-row').each(function(ind, obj) {
            var target = $(obj).find('.myresearch-notes .resource-note');
            initEditableMarkdownField(target, function(markdown) {
                var row = target.closest('.myresearch-row');
                var id = row.find('.hiddenId').val();
                var listId = getActiveListId();

                updateListResource(
                    {'id': id, 'listId': listId, 'notes': markdown},
                    target.find('> div')
                );
            });
        });

        // add resource to list
        $('.mylist-functions #add-to-list').unbind('change').change(function(e) {
            var val = $(this).val();
            if (val != '') {
                addResourcesToList(val);
            }
        });

        // Prompt before leaving page if Ajax load is in progress
        window.onbeforeunload = function(e) {
            if ($('.list-save').length) {
                return VuFind.translate('loading') + '...';
            }
        };
    };

    var initFavoriteOrderingFunctionality = function() {
    	$('#sortable').sortable({cursor: 'move', opacity: 0.7});
    	
    	$('#sort_form').submit(function(event) {
    	    var listOfItems = $('#sortable').sortable('toArray');
    	    $('#sort_form input[name="orderedList"]').val(JSON.stringify(listOfItems));
    	    return true;
    	});
    };

    var initEditableMarkdownField = function(element, callback) {
        element.find('.editable').unbind('click').click(function(e) {
            if (save) {
                // Do not open the editor when save is in progress.
                return;
            }

            if (!editor && e.target.nodeName == 'A') {
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
            textArea.text(decodeURIComponent(currentVal));
            container.hide();
            textArea.insertAfter(container);
            if (editor) {
                editor = null;
            }

            var editorSettings = {
                autoDownloadFontAwesome: false,
                autofocus: true,
                element: textArea[0],
                hideIcons: ['preview', 'side-by-side', 'guide', 'fullscreen'],
                spellChecker: false,
                status: false
            };

            editor = new SimpleMDE(editorSettings);
            currentVal = editor.value();


            editor.codemirror.on("change", function(){
                var html = SimpleMDE.prototype.markdown(editor.value());
                preview.find('.data').html(html);
            });

            // Preview
            var html = SimpleMDE.prototype.markdown(editor.value());
            $('.markdown-preview').remove();
            var preview = $('<div/>').addClass('markdown-preview')
                    .html($('<div/>').addClass('data').html(html));
            $('<div/>').addClass('preview').text(VuFind.translate('preview').toUpperCase()).prependTo(preview);
            preview.appendTo(element);

            // Close editor and save when user clicks outside the editor
            $(document).one('click', function() {
                var markdown = editor.value();
                var html = SimpleMDE.prototype.markdown(markdown);

                editor.toTextArea();
                editor = null;
                element.toggleClass('edit', false).find('textarea').remove();

                container.show();
                container.data('markdown', markdown);
                container.data('empty', (markdown.length == 0 ? '1' : '0'));
                container.html(html);

                preview.remove();

                callback(markdown);
            });

            // Prevent clicks within the editor area to
            // bubble up and close the editor.
            element.closest('.markdown').unbind('click').click(function() {
                return false;
            });
        });
    };

    var toggleErrorMessage = function(mode) {
        var $msg = $('.mylist-error');
        $msg.addClass('alert alert-danger');
        $msg.toggleClass('hidden', !mode);
        if (mode) {
            $('html, body').animate({ scrollTop: 0 }, 'fast');
        }
    };

    var toggleSpinner = function(target, mode) {
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
    };

    var my = {
        initFavoriteOrderingFunctionality: initFavoriteOrderingFunctionality,
        init: function() {
            initEditComponents();
        }
    };

    return my;
})(finna);
