$(function() {
    $('.notifications-tabs-nav').on('click', function(e) {
        e.preventDefault();
        $this = $(this);

        $('.notifications-tabs-nav').each(function() {
            $(this).parent().removeClass('active');
        });
        $this.parent().addClass('active');

        $('.notifications-tabs-content').each(function() {
            if ($this.data('tab') === $(this).attr('id')) {
                $(this).addClass('active');
            } else {
                $(this).removeClass('active');
            }
        });
    });

    var notificationsTable = $('#notifications-list').DataTable({
        paging: false,
        ordering: false,
        searching: false,
        info: false,
        rowReorder: {
            enable: true,
            update: false,
            selector: 'span.reorder'
        },
    });

    notificationsTable.on('row-reorder', function(e, diff, edit) {
        for (var i=0, ien=diff.length ; i<ien ; i++) {
            $(diff[i].node).addClass("reordered");
        }
        var order = [];
        var type = '';
        $('#notifications-list tbody tr').each(function() {
            if ($(this).data('page-id') !== undefined && $(this).data('page-id') !== '') {
                order.push($(this).data('page-id'));
                type = 'page';
            }
            if ($(this).data('broadcast-id') !== undefined && $(this).data('broadcast-id') !== '') {
                order.push($(this).data('broadcast-id'));
                type = 'broadcast';
            }
        });

        $.ajax({
            url : '/vufind/AJAX/JSON?method=NotificationsReorder',
            dataType:'json',
            data: {
                'order': order,
                'type': type,
            },
            success : function(data) {
            },
        });
    });

    $('.notifications-visibility').on('click', function(e){
        e.preventDefault();
        var $this = $(this);
        $.ajax({
            url : '/vufind/AJAX/JSON?method=NotificationsVisibility',
            dataType:'json',
            data: {
                'page-id': $this.parents('tr').data('page-id'),
                'broadcast-id': $this.parents('tr').data('broadcast-id'),
                'visibility': $this.data('visibility'),
            },
            success : function(data) {
                if (data.data.visibility == 1) {
                    $this.find('.icon--font').removeClass('fa-eye-slash');
                    $this.find('.icon--font').addClass('fa-eye');
                    $this.data('visibility', '0');
                } else {
                    $this.find('.icon--font').removeClass('fa-eye');
                    $this.find('.icon--font').addClass('fa-eye-slash');
                    $this.data('visibility', '1');
                }
            },
        });
    });

    $('.notifications-visibility-global').on('click', function(e){
        e.preventDefault();
        var $this = $(this);
        $.ajax({
            url : '/vufind/AJAX/JSON?method=NotificationsVisibility',
            dataType:'json',
            data: {
                'page-id': $this.parents('tr').data('page-id'),
                'broadcast-id': $this.parents('tr').data('broadcast-id'),
                'visibility-global': $this.data('visibility-global'),
            },
            success : function(data) {
                if (data.data.visibility_global == 1) {
                    $this.find('.icon--font').removeClass('fa-globe');
                    $this.find('.icon--font').addClass('fa-times-circle');
                    $this.data('visibility-global', '0');
                } else {
                    $this.find('.icon--font').removeClass('fa-times-circle');
                    $this.find('.icon--font').addClass('fa-globe');
                    $this.data('visibility-global', '1');
                }
            },
        });
    });

    function isExternalUrl () {
        if ($('input[type="checkbox"][name="is_external_url"]').prop('checked')) {
            return true;
        }
        return false;
    }

    function toggleFormExternalUrl () {
        if (isExternalUrl()) {
            $('.not_external_url_content').css('display', 'none');
            $('.external_url_content').css('display', 'block');
        } else {
            $('.not_external_url_content').css('display', 'block');
            $('.external_url_content').css('display', 'none');
        }
    }

    $('input[type="checkbox"][name="is_external_url"]').each(function(){
        toggleFormExternalUrl();
    });

    $('input[type="checkbox"][name="is_external_url"]').change(function(){
        toggleFormExternalUrl();
    });

    $('.close-broadcast').on('click', function(e){
        e.preventDefault();
        var $this = $(this);
        $.ajax({
            url : '/vufind/AJAX/JSON?method=NotificationsClose',
            dataType:'json',
            data: {
                'broadcast-id': $this.data('broadcast-id'),
            },
            success : function(data) {
                $this.parents('span.broadcast').hide();
            },
        });
    });
});