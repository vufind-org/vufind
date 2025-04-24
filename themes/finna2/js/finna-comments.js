/*global VuFind, finna, refreshCommentList, refreshRecordRating */
finna.comments = (function finnaComments() {
  /**
   * Refresh comments and ratings
   */
  function requestRefreshCommentsAndRating() {
    let recordId = $('input.hiddenId').val();
    let recordSource = $('input.hiddenSource').val();
    const tab = document.querySelector('.usercomments-tab');
    if (tab) {
      refreshCommentList(tab, recordId, recordSource);
    }
    refreshRecordRating(recordId, recordSource);
  }

  /**
   * Initialize comment to be editable
   * @param {boolean} allowCommenting Should commenting be allowed
   */
  function initEditComment(allowCommenting) {
    $('.comment-list .edit').off('click').on('click', function onCommentEditClick() {
      var comment = $(this).closest('.comment');
      var form = $('form.comment-form');
      form.toggle(true);

      var save = form.find('input.save');
      save.val(save.data('label-edit'));

      form.find('textarea[name="comment"]').val(comment.find('.comment-text').text());
      form.find('input[type=hidden][name=commentId]').val(comment.data('id'));

      form.find('input.cancel').toggleClass('hide', false);
      return false;
    });

    $('form.comment-form input.cancel').off('click').on('click', function onCommentCancelClick() {
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

  /**
   * Initialize comment list
   * @param {boolean} allowCommenting Should commenting be allowed
   * @param {boolean} allowRating     Should rating be allowed
   * @param {number}  commentCount    Comment count
   */
  function initCommentList(allowCommenting, allowRating, commentCount) {
    $('.recordTabs #usercomments .count').text(commentCount);

    var form = $('form.comment-form');
    form.toggle(allowCommenting);
    form.find('input[type=hidden][name=commentId]').val('');

    initEditComment(allowCommenting);

    VuFind.lightbox.bind($('.usercomments-tab'));
  }

  var my = {
    initCommentList: initCommentList,
    requestRefreshCommentsAndRating: requestRefreshCommentsAndRating,
    init: function init() {
    }
  };

  return my;
})();
