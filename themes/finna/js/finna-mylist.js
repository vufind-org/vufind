/*global VuFind*/
finna.myList = (function() {

    var addNewListLabel = null;

    // This is duplicated in image-popup.js to avoid dependency
    var getActiveListId = function() {
        return $('input[name="listID"]').val();
    };

    var processHTMLforSave = function(html) {
        html = html.replace(/\&lt;/g, '<');
        html = html.replace(/\&gt;/g, '>');
        html = html.replace(/<br class="newline">/g, '\n');
        return html;
    };

    var updateList = function(params, callback, type) {
        var spinner = null;

        var listParams = {
            'id': getActiveListId(),
            'title': $('.list-title span').text(),
            'public': $(".list-visibility input[type='radio']:checked").val()
        };

        if (type != 'add-list') {
            var description = processHTMLforSave($('.list-description span').html());
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
            url: VuFind.getPath() + '/AJAX/JSON?method=editList',
            data: {'params': listParams},
            success: function(data, status, jqXHR) {
                if (type != 'add-list' && spinner) {
                    toggleSpinner(spinner, false);
                }
                if (status == 'success' && data.status == 'OK') {
                    if (callback != null) {
                        callback(data.data);
                    }
                } else {
                    toggleErrorMessage(true);
                }
            }
        });
    };

    var updateListResource = function(params, input, row) {
        toggleErrorMessage(false);

        var spinner = input.next('.fa');
        toggleSpinner(spinner, true);

        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: VuFind.getPath() + '/AJAX/JSON?method=editListResource',
            data: {'params': params},
            success: function(data, status, jqXHR) {
                if (spinner) {
                    toggleSpinner(spinner, false);
                }

                if (status == 'success' && data.status == 'OK') {
                    var hasNotes = params.notes != '';
                    input.closest('.myresearch-notes').find('.note-info').toggleClass('hide', !hasNotes);
                    input.data('empty', hasNotes == '' ? '1' : '0');
                    if (!hasNotes) {
                        input.text(VuFind.translate('add_note'));
                    }
                } else {
                    toggleErrorMessage(true);
                }
            }
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
            url: VuFind.getPath() + '/AJAX/JSON?method=addToList',
            data: {params: {'listId': listId, 'source': 'Solr', 'ids': ids}},
            success: function(data, status, jqXHR) {
                if (status == 'success' && data.status == 'OK') {
                    // Don't reload to avoid trouble with POST requests
                    location.href = location.href;
                } else {
                    toggleErrorMessage(true);
                    $('#add-to-list-spinner').addClass('hidden');
                    $('#add-to-list').removeAttr('disabled');
                    $('#add-to-list').val('');
                }
            }
        });
    };

    var refreshLists = function(data) {
        toggleErrorMessage(false);

        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: VuFind.getPath() + '/AJAX/JSON?method=getMyLists',
            data: {'active': getActiveListId()},
            success: function(data, status, jqXHR) {
                if (status == 'success' && data.status == 'OK') {
                    $('.mylist-bar').html(data.data);
                    initEditComponents();
                } else {
                    toggleErrorMessage(true);
                }
            }
        });
    };

    var listVisibilityChanged = function(data) {
        $('.mylist-bar .list-visibility .list-url').toggle(data['public'] === "1");
    };

    var listDescriptionChanged = function() {
        var description = $('.list-description span');
        if (description.html() == '') {
            description.data('empty', '1');
            description.html(VuFind.translate('add_list_description'));
        } else {
            description.data('empty', '0');
        }
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
            // list title
            var titleCallback = {
                'finish': function(e) {
                    if (typeof(e) === 'undefined' || !e.cancel) {
                        updateList({}, refreshLists, 'title');
                    }
                }
            };
            var target = $('.list-title span');
            target.editable({action: 'click', triggers: [target, $('.list-title i')]}, titleCallback, settings);

            // list description
            var descCallback = {
                'start': function(e) {
                    if (e.target.data('empty') == '1') {
                        e.target.find('textarea').val('');
                    }
                },
                'finish': function(e) {
                    if (typeof(e) === 'undefined' || !e.cancel) {
                        if (e.value == '' && e.target.data('empty') == '1') {
                            e.target.text(VuFind.translate('add_list_description'));
                            return;
                        }
                        updateList({}, listDescriptionChanged, 'desc');
                    }
                }
            };
            target = $('.list-description span');
            target.editable({type: 'textarea', action: 'click', triggers: [target, $('.list-description i')]}, descCallback, settings);

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
        target.editable({action: 'click', triggers: [target, $('.add-new-list .icon')]}, newListCallBack, settings);

        // list resource notes
        var resourceNotesCallback = {
            'start': function(e) {
                if (e.target.data('empty') == '1') {
                    e.target.find('textarea').val('');
                }
            },
            'finish': function(e) {
                if (typeof(e) === 'undefined' || !e.cancel) {
                    if (e.value == '' && e.target.data('empty') == '1') {
                        e.target.text(VuFind.translate('add_note'));
                        return;
                    }

                    var row = e.target.closest('.myresearch-row');
                    var id = row.find('.hiddenId').val();
                    var listId = getActiveListId();
                    var notes = processHTMLforSave(e.target.html());
                    updateListResource(
                        {'id': id, 'listId': listId, 'notes': notes},
                        e.target
                    );
                }
            }
        };
        $('.myresearch-row').each(function(ind, obj) {
            var target = $(obj).find('.myresearch-notes .resource-note span');
            target.editable(
                {action: 'click', type: 'textarea', triggers: [target, target.next('.icon')]},
                resourceNotesCallback, settings
            );
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

    var toggleErrorMessage = function(mode) {
        $('.alert-danger').toggleClass('hide', !mode);
        if (mode) {
            $("html, body").animate({ scrollTop: 0 }, 'fast');
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
        init: function() {
            initEditComponents();
        },
    };

    return my;
})(finna);
