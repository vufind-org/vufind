/*global checkSaveStatuses, hexEncode, path, rc4Encrypt, refreshCommentList*/

// keep a handle to the current opened dialog so we can access it later
var __dialogHandle = {dialog: null, processFollowup:false, followupModule: null, followupAction: null, recordId: null, postParams: null};

function getLightbox(module, action, id, lookfor, message, followupModule, followupAction, followupId, postParams) {
    // Optional parameters
    if (followupModule === undefined) {followupModule = '';}
    if (followupAction === undefined) {followupAction = '';}
    if (followupId     === undefined) {followupId     = '';}

    var params = {
        method: 'getLightbox',
        lightbox: 'true',
        submodule: module,
        subaction: action,
        id: id,
        lookfor: lookfor,
        message: message,
        followupModule: followupModule,
        followupAction: followupAction,
        followupId: followupId
    };

    // create a new modal dialog
    var $dialog = $('<div id="modalDialog"><div class="dialogLoading">&nbsp;</div></div>')
        .load(path + '/AJAX/JSON?' + $.param(params), postParams)
            .dialog({
                modal: true,
                autoOpen: false,
                closeOnEscape: true,
                title: message,
                width: 600,
                height: 350,
                close: function () {
                    // check if the dialog was successful, if so, load the followup action
                    if (__dialogHandle.processFollowup && __dialogHandle.followupModule
                            && __dialogHandle.followupAction) {
                        $(this).remove();
                        getLightbox(__dialogHandle.followupModule, __dialogHandle.followupAction,
                                __dialogHandle.recordId, null, message, null, null, null, postParams);
                    }
                    $(this).remove();
                }
            });

    // save information about this dialog so we can get it later for followup processing
    __dialogHandle.dialog = $dialog;
    __dialogHandle.processFollowup = false;
    __dialogHandle.followupModule = followupModule;
    __dialogHandle.followupAction = followupAction;
    __dialogHandle.recordId = followupId == '' ? id : followupId;
    __dialogHandle.postParams = postParams;

    // done
    return $dialog.dialog('open');
}

function hideLightbox() {
    if (!__dialogHandle.dialog) {
        return false;
    }
    __dialogHandle.dialog.dialog('close');
}

function displayLightboxFeedback($form, message, type) {
    var $container = $form.parent();
    $container.empty();
    $container.append('<div class="' + type + '">' + message + '</div>');
}

function displayFormError($form, error) {
    $form.parent().find('.error').remove();
    $form.prepend('<div class="error">' + error + '</div>');
}

function displayFormInfo($form, msg) {
    $form.parent().parent().find('.info').remove();
    $form.parent().prepend('<div class="info">' + msg + '</div>');
}

function showLoadingGraphic($form) {
    $form.parent().prepend('<div class="dialogLoading">&nbsp;</div>');
}

function hideLoadingGraphic($form) {
    $form.parent().parent().find('.dialogLoading').remove();
}

function registerAjaxLogin() {
    $('#modalDialog form[name="loginForm"]').unbind('submit').submit(function(){
        if (!$(this).valid()) { return false; }
        var form = this;
        $.ajax({
            url: path + '/AJAX/JSON?method=getSalt',
            dataType: 'json',
            success: function(response) {
                if (response.status == 'OK') {
                    var salt = response.data;

                    // get the user entered username/password
                    var password = form.password.value;
                    var username = form.username.value;

                    // encrypt the password with the salt
                    password = rc4Encrypt(salt, password);

                    // hex encode the encrypted password
                    password = hexEncode(password);

                    // login via ajax
                    $.ajax({
                        type: 'POST',
                        url: path + '/AJAX/JSON?method=login',
                        dataType: 'json',
                        data: {username:username, password:password},
                        success: function(response) {
                            if (response.status == 'OK') {
                                // Hide "log in" options and show "log out" options:
                                $('#loginOptions').hide();
                                $('#logoutOptions').show();

                                // Update user save statuses if the current context calls for it:
                                if (typeof(checkSaveStatuses) == 'function') {
                                    checkSaveStatuses();
                                }

                                // refresh the comment list so the "Delete" links will show
                                $('.commentList').each(function(){
                                    var recordId = $('#record_id').val();
                                    var recordSource = extractSource($('#record'));
                                    refreshCommentList(recordId, recordSource);
                                });

                                // if there is a followup action, then it should be processed
                                __dialogHandle.processFollowup = true;

                                // and we close the dialog
                                hideLightbox();
                            } else {
                                displayFormError($(form), response.data);
                            }
                        }
                    });
                } else {
                    displayFormError($(form), response.data);
                }
            }
        });
        return false;
    });
}

function registerAjaxCart() {

    var $form = $('#modalDialog form[name="cartForm"]');
    if($form) {
        $("input[name='ids[]']", $form).attr('checked', false);
        $($form).validate({
            rules: {
                "ids[]": "required"
            },
            showErrors: function(x) {
                hideLoadingGraphic($form);
                for (y in x) {
                    if (y == 'ids[]') {
                        displayFormError($form, vufindString.bulk_noitems_advice);
                    }
                 }
            }
        });
        $("input[name='email']", $form).unbind('click').click(function(){
            showLoadingGraphic($form);
            if (!$($form).valid()) { return false; }
            var selected = $("input[name='ids[]']:checked", $form);
            var postParams = [];
            $.each(selected, function(i) {
                postParams[i] = this.value;
            });
            hideLightbox();
            var $dialog = getLightbox('Cart', 'Home', null, null, this.title, 'Cart', 'Home', '', {email: 1, ids: postParams});
            return false;
        });
        $("input[name='print']", $form).unbind('click').click(function(){
            showLoadingGraphic($form);
            var selected = $("#modalDialog input[name='ids[]']:checked");
            var ids = [];
            $.each(selected, function(i) {
                ids[i] = this.value;
            });
            var printing = printIDs(ids);
            if(!printing) {
                hideLoadingGraphic($form);
                displayFormError($($form), vufindString.bulk_noitems_advice);
            } else {
                hideLightbox();
            }
            return false;
        });
        $("input[name='empty']", $form).unbind('click').click(function(){
            if (confirm(vufindString.confirmEmpty)) {
                 saveCartCookie([]);
                 showLoadingGraphic($form);
                 hideLightbox();
                 // This always assumes the Empty command was successful as no indication of success or failure is given
                 var $dialog = getLightbox('Cart', 'Home', null, null, vufindString.viewBookBag, '', '', '');
                 redrawCartStatus();
                 removeRecordState();
            }
            return false;
        });
        $("input[name='export']", $form).unbind('click').click(function(){
            showLoadingGraphic($form);
            if (!$($form).valid()) { return false; }
            var selected = $("input[name='ids[]']:checked", $form);
            var postParams = [];
            $.each(selected, function(i) {
                postParams[i] = this.value;
            });
            hideLightbox();
            var $dialog = getLightbox('Cart', 'Home', null, null, this.title, 'Cart', 'Home', '', {"export": "1", ids: postParams});
            return false;
        });
        $("input[name='delete']", $form).unbind('click').click(function(){
            showLoadingGraphic($form);
            if (!$($form).valid()) { return false; }
            var url = path + '/AJAX/JSON?' + $.param({method:'removeItemsCart'});
            $($form).ajaxSubmit({
                url: url,
                dataType: 'json',
                success: function(response, statusText, xhr, $form) {
                    if (response.status == 'OK') {
                        var items = getItemsFromCartCookie();
                        redrawCartStatus()
                        hideLightbox();
                    }
                    var $dialog = getLightbox('Cart', 'Home', null, null, vufindString.viewBookBag, '', '', '', {viewCart:"1"});
                }
            });
            return false;
        });
        $("input[name='saveCart']", $form).unbind('click').click(function(){
            showLoadingGraphic($form);
            if (!$($form).valid()) { return false; }
            var selected = $("input[name='ids[]']:checked", $form);
            var postParams = [];
            $.each(selected, function(i) {
                postParams[i] = this.value;
            });
            hideLightbox();
            var $dialog = getLightbox('Cart', 'Home', null, null, this.title, 'Cart', 'Home', '', {saveCart: 1, ids: postParams});
            return false;
        });
    }

    // assign action to the "select all checkboxes" class
    $('input[type="checkbox"].selectAllCheckboxes').change(function(){
        $(this.form).find('input[type="checkbox"]').attr('checked', $(this).is(':checked'));
    });
}

function registerAjaxSaveRecord() {
    var saveForm = $('#modalDialog form[name="saveRecord"]');
    if (saveForm.length > 0) {
        saveForm.unbind('submit').submit(function(){
            if (!$(this).valid()) { return false; }
            var recordId = this.id.value;
            var recordSource = this.source.value;
            var url = path + '/AJAX/JSON?' + $.param({method:'saveRecord',id:recordId,'source':recordSource});
            $(this).ajaxSubmit({
                url: url,
                dataType: 'json',
                success: function(response, statusText, xhr, $form) {
                    if (response.status == 'OK') {
                        // close the dialog
                        hideLightbox();
                        // Update user save statuses if the current context calls for it:
                        if (typeof(checkSaveStatuses) == 'function') {
                            checkSaveStatuses();
                        }
                        // Update tag list if appropriate:
                        if (typeof(refreshTagList) == 'function') {
                            refreshTagList(recordId, recordSource);
                        }
                    } else {
                        displayFormError($form, response.data);
                    }
                }
            });
            return false;
        });

        $('a.listEdit').unbind('click').click(function(){
            var id = this.href.substring(this.href.indexOf('?')+'recordId='.length+1);
            id = decodeURIComponent(id.split('&')[0]);
            var controller = extractController(this);
            hideLightbox();
            getLightbox('MyResearch', 'EditList', 'NEW', null, this.title, controller, 'Save', id);
            return false;
        });
    }
}

function registerAjaxListEdit() {
    $('#modalDialog form[name="newList"]').unbind('submit').submit(function(){
        if (!$(this).valid()) { return false; }
        var url = path + '/AJAX/JSON?' + $.param({method:'addList'});
        $(this).ajaxSubmit({
            url: url,
            dataType: 'json',
            success: function(response, statusText, xhr, $form) {
                if (response.status == 'OK') {
                    // if there is a followup action, then it should be processed
                    __dialogHandle.processFollowup = true;

                    // close the dialog
                    hideLightbox();
                } else if (response.status == 'NEED_AUTH') {
                    // TODO: redirect to login prompt?
                    // For now, we'll just display an error message; short of
                    // strange user behavior involving multiple open windows,
                    // it is very unlikely to get logged out at this stage.
                    displayFormError($form, response.data);
                } else {
                    displayFormError($form, response.data);
                }
            }
        });
        return false;
    });
}

function registerAjaxEmailRecord() {
    $('#modalDialog form[name="emailRecord"]').unbind('submit').submit(function(){
        if (!$(this).valid()) { return false; }
        showLoadingGraphic($(this));
        $(this).hide();
        var url = path + '/AJAX/JSON?' + $.param({method:'emailRecord',id:this.id.value,'source':this.source.value});
        $(this).ajaxSubmit({
            url: url,
            dataType: 'json',
            success: function(response, statusText, xhr, $form) {
                hideLoadingGraphic($form);
                if (response.status == 'OK') {
                    displayFormInfo($form, response.data);
                    // close the dialog
                    setTimeout(function() { hideLightbox(); }, 2000);
                } else {
                    $form.show();
                    displayFormError($form, response.data);
                }
            }
        });
        return false;
    });
}

function registerAjaxSMSRecord() {
    $('#modalDialog form[name="smsRecord"]').unbind('submit').submit(function(){
        if (!$(this).valid()) { return false; }
        showLoadingGraphic($(this));
        $(this).hide();
        var url = path + '/AJAX/JSON?' + $.param({method:'smsRecord',id:this.id.value,'source':this.source.value});
        $(this).ajaxSubmit({
            url: url,
            dataType: 'json',
            clearForm: true,
            success: function(response, statusText, xhr, $form) {
                hideLoadingGraphic($form);
                if (response.status == 'OK') {
                    displayFormInfo($form, response.data);
                    // close the dialog
                    setTimeout(function() { hideLightbox(); }, 2000);
                } else {
                    $form.show();
                    displayFormError($form, response.data);
                }
            }
        });
        return false;
    });
}

function registerAjaxTagRecord() {
    $('#modalDialog form[name="tagRecord"]').unbind('submit').submit(function(){
        if (!$(this).valid()) { return false; }
        var id = this.id.value;
        var recordSource = this.source.value;
        var url = path + '/AJAX/JSON?' + $.param({method:'tagRecord',id:id,'source':recordSource});
        $(this).ajaxSubmit({
            url: url,
            dataType: 'json',
            success: function(response, statusText, xhr, $form) {
                if (response.status == 'OK') {
                    hideLightbox();
                    refreshTagList(id, recordSource);
                } else {
                    displayFormError($form, response.data);
                }
            }
        });
        return false;
    });
}

function refreshTagList(id, recordSource) {
    $('#tagList').empty();
    var url = path + '/AJAX/JSON?' + $.param({method:'getRecordTags',id:id,'source':recordSource});
    $.ajax({
        dataType: 'json',
        url: url,
        success: function(response) {
            if (response.status == 'OK') {
                $.each(response.data, function(i, tag) {
                    var href = path + '/Tag?' + $.param({lookfor:tag.tag});
                    var html = (i>0 ? ', ' : ' ') + '<a href="' + htmlEncode(href) + '">' + htmlEncode(tag.tag) +'</a> (' + htmlEncode(tag.cnt) + ')';
                    $('#tagList').append(html);
                });
            } else if (response.data && response.data.length > 0) {
                $('#tagList').append(response.data);
            }
        }
    });
}

function registerAjaxEmailSearch() {
    $('#modalDialog form[name="emailSearch"]').unbind('submit').submit(function(){
        if (!$(this).valid()) { return false; }
        showLoadingGraphic($(this));
        $(this).hide();
        var url = path + '/AJAX/JSON?' + $.param({method:'emailSearch'});
        $(this).ajaxSubmit({
            url: url,
            dataType: 'json',
            data: {url:window.location.href},
            success: function(response, statusText, xhr, $form) {
                hideLoadingGraphic($form);
                if (response.status == 'OK') {
                    displayFormInfo($form, response.data);
                    // close the dialog
                    setTimeout(function() { hideLightbox(); }, 2000);
                } else {
                    $form.show();
                    displayFormError($form, response.data);
                }
            }
        });
        return false;
    });
}

function registerAjaxBulkEmail() {
    $('#modalDialog form[name="bulkEmail"]').unbind('submit').submit(function(){
        if (!$(this).valid()) { return false; }
        var url = path + '/AJAX/JSON?' + $.param({method:'emailSearch', 'subject':'bulk_email_title'});
        var ids = [];
        $(':input[name="ids[]"]', this).each(function() {
            ids.push(encodeURIComponent('id[]') + '=' + encodeURIComponent(this.value));
        });
        var searchURL = path + '/Records?' + ids.join('&');
        $(this).ajaxSubmit({
            url: url,
            dataType: 'json',
            data: {url:searchURL},
            success: function(response, statusText, xhr, $form) {
                if (response.status == 'OK') {
                    displayLightboxFeedback($form, response.data, 'info');
                    setTimeout("hideLightbox();", 3000);
                } else {
                    displayFormError($form, response.data);
                }
            }
        });
        return false;
    });
}

function registerAjaxBulkExport() {
    $('#modalDialog form[name="bulkExport"]').unbind('submit').submit(function(){
        if (!$(this).valid()) { return false; }
        var url = path + '/AJAX/JSON?' + $.param({method:'exportFavorites'});
        $(this).ajaxSubmit({
            url: url,
            dataType: 'json',
            success: function(response, statusText, xhr, $form) {
                if (response.status == 'OK') {
                    $form.parent().empty().append(response.data.result_additional);
                } else {
                    displayFormError($form, response.data);
                }
            }
        });
        return false;
    });
}

function registerAjaxCartExport() {
    $('#modalDialog form[name="exportForm"]').unbind('submit').submit(function(){
        if (!$(this).valid()) { return false; }
        var url = path + '/AJAX/JSON?' + $.param({method:'exportFavorites'});
        $(this).ajaxSubmit({
            url: url,
            dataType: 'json',
            success: function(response, statusText, xhr, $form) {
                if (response.status == 'OK') {
                    $form.parent().empty().append(response.data.result_additional);
                } else {
                    displayFormError($form, response.data);
                }
            }
        });
        return false;
    });
}

function registerAjaxBulkSave() {
    var bulkSave = $('#modalDialog form[name="bulkSave"]');
    if (bulkSave.length > 0) {
        bulkSave.unbind('submit').submit(function(){
            if (!$(this).valid()) { return false; }
            var url = path + '/AJAX/JSON?' + $.param({method:'bulkSave'});
            $(this).ajaxSubmit({
                url: url,
                dataType: 'json',
                success: function(response, statusText, xhr, $form) {
                    if (response.status == 'OK') {
                        displayLightboxFeedback($form, response.data.info, 'info');
                        var url =  path + '/MyResearch/MyList/' + response.data.result.list;
                        setTimeout(function() { hideLightbox(); window.location = url; }, 2000);
                    } else {
                        displayFormError($form, response.data.info);
                    }
                }
            });
            return false;
        });

        $('a.listEdit').unbind('click').click(function(){
            var $form = $('#modalDialog form[name="bulkSave"]');
            var id = this.href.substring(this.href.indexOf('?')+'recordId='.length+1);
            id = decodeURIComponent(id.split('&')[0]);
            var ids = $("input[name='ids[]']", $form);
            var postParams = [];
            $.each(ids, function(i) {
                postParams[i] = this.value;
            });
            hideLightbox();
            var $dialog = getLightbox('MyResearch', 'EditList', 'NEW', null, this.title, 'Cart', 'Save', '', {ids: postParams});
            return false;
        });
    }
}

function registerAjaxBulkDelete() {
    $('#modalDialog form[name="bulkDelete"]').unbind('submit').submit(function(){
        if (!$(this).valid()) { return false; }
        var url = path + '/AJAX/JSON?' + $.param({method:'deleteFavorites'});
        $(this).ajaxSubmit({
            url: url,
            dataType: 'json',
            success: function(response, statusText, xhr, $form) {
                if (response.status == 'OK') {
                    displayLightboxFeedback($form, response.data.result, 'info');
                    setTimeout("hideLightbox(); window.location.reload();", 3000);
                } else {
                    displayFormError($form, response.data);
                }
            }
        });
        return false;
    });
}

/**
 * This is called by the lightbox when it
 * finished loading the dialog content from the server
 * to register the form in the dialog for ajax submission.
 */
function lightboxDocumentReady() {
    registerAjaxLogin();
    registerAjaxCart();
    registerAjaxCartExport();
    registerAjaxSaveRecord();
    registerAjaxListEdit();
    registerAjaxEmailRecord();
    registerAjaxSMSRecord();
    registerAjaxTagRecord();
    registerAjaxEmailSearch();
    registerAjaxBulkSave();
    registerAjaxBulkEmail();
    registerAjaxBulkExport();
    registerAjaxBulkDelete();
    $('.mainFocus').focus();
}