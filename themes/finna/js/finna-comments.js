finna.comments = (function() {

    var initCommentList = function(allowCommenting, allowRating, commentCount) {
        $(".recordTabs #usercomments .count").text(commentCount);

        var form = $('form.comment-form');
        form.toggle(allowCommenting);
        form.find('input[type=hidden][name=commentId]').val('');

        initInappropriateComment();
        if (allowRating) {
            var rating = form.find('.rating');
            rating.rating('rate', 0);
            initRating();
        }
        initEditComment(allowCommenting, allowRating);

        // Override global methods
        _registerAjaxCommentRecord = registerAjaxCommentRecord;
        registerAjaxCommentRecord = function() {
            initCommentForm(_registerAjaxCommentRecord, allowRating);
        };

        deleteRecordComment = function(element, recordId, recordSource, commentId) {
            var url = path + '/AJAX/JSON?'
                + $.param({method:'deleteRecordComment', id:commentId});
            $.ajax({
                dataType: 'json',
                url: url,
                data: {recordId: recordId},
                success: function(response) {
                    if (response.status == 'OK') {
                        requestRefreshComments();
                        if ('rating' in response.data) {
                            updateAverageRating(
                                response.data.rating.average,
                                response.data.rating.count
                            );
                        }
                    }
                }
            });
        };
        
        // Login link
        $('a.login-link').click(function() {
          return Lightbox.get('MyResearch','UserLogin');
        });
        finna.layout.initLightbox($('#usercomments-tab'));
    };

    var initCommentForm = function(parentMethod, allowRating) {
        parentMethod();

        $('form.comment-form').unbind('submit').submit(function(){
            var form = this;
            var id = form.id.value;
            var recordSource = form.source.value;
            var type = form.type.value;
            var data = {
                comment: form.comment.value,
                id: id,
                source: recordSource,
                type: type
            };

            if (allowRating) {
                var rating = $(this).find('.rating');
                if (rating.length) {
                    data.rating = rating.val();
                }
            }
            var commentId = form.commentId.value;
            if (commentId != '') {
                data.commentId = commentId;
            }

            $(this).find('input.cancel').toggleClass('hide', true);
            $(this).find('input[type="submit"]').attr('disabled', true).button('loading');

            var url = path + '/AJAX/JSON?' + $.param({method:'commentRecord'});
            $.ajax({
                type: 'POST',
                url:  url,
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.status == 'OK') {
                        requestRefreshComments();
                        if ('rating' in response.data) {
                            updateAverageRating(
                                response.data.rating.average,
                                response.data.rating.count
                            );
                        }
                        $(form).find('textarea[name="comment"]').val('');
                    } else {
                        Lightbox.displayError(response.data);
                    }
                }
            });
            return false;
        });
    };

    var initRating = function() {
        $('#usercomments-tab .rating').rating();
    };

    var updateAverageRating = function(rating, count) {
        $('.rating-average .rating').rating('rate', rating);
        $('.rating-average .count>span').text(count);
    };

    var initInappropriateComment = function() {
        $('.comment-inappropriate a.modal-link').unbind('click').click(function() {
            var comment = $(this).closest('.comment').data('id');
            if (title = $(this).attr('title')) {
                $('#modal .modal-title').html(title);
                Lightbox.titleSet = true;
            }

            return Lightbox.get(
                'Comments', 'inappropriate',
                {comment: comment}
            );
        });

        Lightbox.addFormHandler('commentInappropriateForm', function(evt) {
            if (evt.isDefaultPrevented()) {
                $('.fa.fa-spinner', evt.target).remove();
                return false;
            }

            var form = $(evt.target);
            form.find(':submit').attr('disabled', true);
            var comment = form.find('input[type=hidden][name=comment]').val();
            var reason = form.find('input[name=reason]:checked').val();

            var url = path + '/AJAX/JSON?method=inappropriateComment&comment';
            $.ajax({
                dataType: 'json',
                data: {comment: comment, reason: reason},
                method: 'POST',
                url: url,
                success: function(response) {
                    if (response.status == 'OK') {
                        Lightbox.close();
                        requestRefreshComments();
                    }
                }
            });
            return false;
        });
    };

    var initEditComment = function(allowCommenting, allowRating) {
        $('.comment-list .edit').unbind('click').click(function() {
            var comment = $(this).closest('.comment');
            var form = $('form.comment-form');
            form.toggle(true);

            var save = form.find('input.save');
            save.val(save.data('label-edit'));

            form.find('textarea[name="comment"]').val(comment.find('.comment-text').text());
            form.find('input[type=hidden][name=commentId]').val(comment.data('id'));

            if (allowRating) {
                var rating = comment.find('.rating');
                if (rating.length) {
                    form.find('.rating').rating('rate', rating.val());
                }
            }

            form.find('input.cancel').toggleClass('hide', false);
            return false;
        });

        $('form.comment-form input.cancel').unbind('click').click(function() {
            var form = $('form.comment-form');
            form.toggle(allowCommenting);
            form.find('textarea[name="comment"]').val('');
            form.find('input[type=hidden][name=commentId]').val('');

            var save = form.find('input.save');
            save.val(save.data('label-new'));
            $(this).toggleClass('hide', true);
            return false;
        });
    };

    var requestRefreshComments = function() {
        var record = $('input.hiddenId').val();
        var source = $('input.hiddenSource').val();
        var tab = $('#usercomments-tab');
        refreshCommentList(tab, record, source);
    };

    var my = {
        initCommentList: initCommentList,
        init: function() {
        },
    };

    return my;
})(finna);
