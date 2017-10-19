/*global VuFind, finna, registerAjaxCommentRecord, deleteRecordComment, refreshCommentList */
/*exported deleteRecordComment */
finna.comments = (function finnaComments() {
  function initCommentList(allowCommenting, allowRating, commentCount) {
    $('.recordTabs #usercomments .count').text(commentCount);

    var form = $('form.comment-form');
    form.toggle(allowCommenting);
    form.find('input[type=hidden][name=commentId]').val('');

    if (allowRating) {
      var rating = form.find('.rating');
      rating.rating('rate', 0);
      initRating();
    }
    initEditComment(allowCommenting, allowRating);

    // Override global method
    var _registerAjaxCommentRecord = registerAjaxCommentRecord;
    registerAjaxCommentRecord = function registerAjaxCommentRecord() { // eslint-disable-line no-global-assign
      initCommentForm(_registerAjaxCommentRecord, allowRating);
    };

    // Override global method
    deleteRecordComment = function deleteRecordComment(element, recordId, recordSource, commentId) { // eslint-disable-line no-global-assign
      var url = VuFind.path + '/AJAX/JSON?'
        + $.param({method: 'deleteRecordComment', id: commentId});
      $.ajax({
        dataType: 'json',
        url: url,
        data: {recordId: recordId}
      })
        .done(function commentAjaxDone(response) {
          requestRefreshComments();
          if ('rating' in response.data) {
            updateAverageRating(
              response.data.rating.average,
              response.data.rating.count
            );
          }
        })
        .fail(function commentAjaxFail(response, textStatus) {
          alert(textStatus);
        });
    };

    VuFind.lightbox.bind($('.usercomments-tab'));
  }

  function initCommentForm(parentMethod, allowRating) {
    parentMethod();

    $('form.comment-form').unbind('submit').submit(function onCommentSubmit(event) {
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

      if (typeof form.checkValidity == 'function') {
        // This is for Safari, which doesn't validate forms on submit
        if (!form.checkValidity()) {
          event.preventDefault();
          return;
        }
      } else {
        // JS validation for browsers that don't support form validation
        if (form.comment.value === '') {
          $(form.comment).addClass('invalid');
          event.preventDefault();
          return;
        }
        $(form.comment).removeClass('invalid');
      }

      if (allowRating) {
        var rating = $(this).find('.rating');
        if (rating.length) {
          data.rating = rating.val();
        }
      }
      var commentId = form.commentId.value;
      if (commentId !== '') {
        data.commentId = commentId;
      }

      $(this).find('input.cancel').toggleClass('hide', true);
      $(this).find('input[type="submit"]').attr('disabled', true).button('loading');

      var url = VuFind.path + '/AJAX/JSON?' + $.param({method: 'commentRecord'});
      $.ajax({
        type: 'POST',
        url: url,
        data: data,
        dataType: 'json'
      })
        .done(function onCommentSubmitDone(response) {
          requestRefreshComments();
          if ('rating' in response.data) {
            updateAverageRating(
              response.data.rating.average,
              response.data.rating.count
            );
          }
          $(form).find('textarea[name="comment"]').val('');
        })
        .fail(function onCommentSubmitFail(response/*, textStatus*/) {
          alert(response.responseJSON.data);
        });
      return false;
    });
  }

  function initRating() {
    $('.usercomments-tab .rating').rating();
  }

  function updateAverageRating(rating, count) {
    $('.rating-average .rating').rating('rate', rating);
    $('.rating-average .count>span').text(count);
  }

  function initEditComment(allowCommenting, allowRating) {
    $('.comment-list .edit').unbind('click').click(function onCommentEditClick() {
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

    $('form.comment-form input.cancel').unbind('click').click(function onCommentCancelClick() {
      var form = $('form.comment-form');
      form.toggle(allowCommenting);
      form.find('textarea[name="comment"]').val('');
      form.find('input[type=hidden][name=commentId]').val('');

      var save = form.find('input.save');
      save.val(save.data('label-new'));
      $(this).toggleClass('hide', true);
      return false;
    });
  }

  function requestRefreshComments() {
    var record = $('input.hiddenId').val();
    var source = $('input.hiddenSource').val();
    var tab = $('.usercomments-tab');
    refreshCommentList(tab, record, source);
  }

  var my = {
    initCommentList: initCommentList,
    requestRefreshComments: requestRefreshComments,
    init: function init() {
    }
  };

  return my;
})();
