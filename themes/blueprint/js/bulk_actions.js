$(document).ready(function(){
    registerBulkActions();
});

function registerBulkActions() {
    $('form[name="bulkActionForm"] input[type="submit"]').unbind('click').click(function(){
        var ids = $.map($(this.form).find('input.checkbox_ui:checked'), function(i) {
            return $(i).val();
        });
        // If no IDs were selected, let the non-Javascript action display an error:
        if (ids.length == 0) {
            return true;
        }
        var action = $(this).attr('name');
        var message = $(this).attr('title');
        var id = '';
        var module = "Cart";
        switch (action) {
        case 'export':
            var postParams = {ids:ids, 'export':'1'};
            action = "MyResearchBulk";
            break;
        case 'delete':
            module = "MyResearch";
            action = "Delete";
            id = $(this).attr('id');
            id = (id.indexOf('bottom_delete_list_items_') != -1)
                ? id.replace('bottom_delete_list_items_', '')
                : id.replace('delete_list_items_', '');
            var postParams = {ids:ids, 'delete':'1', 'listID':id};
            break;
        case 'email':
            action = "MyResearchBulk";
            var postParams = {ids:ids, email:'1'};
            break;
        case 'print':
            var printing = printIDs(ids);
            if(printing) {
                return false;
            } else {
                action = "MyResearchBulk";
                var postParams = {error:'1'};
            }
            break;
        }
        getLightbox(module, action, id, '', message, '', '', '', postParams);
        return false;
    });

    // Support delete list button:
    $('.deleteList').unbind('click').click(function(){
        var id = $(this).attr('id').substr('deleteList'.length);
        var message = $(this).attr('title');
        var postParams = {listID: id, deleteList: 'deleteList'};
        getLightbox('MyResearch', 'DeleteList', '', '', message, 'MyResearch', 'Favorites', '', postParams);
        return false;
    });

    // Support delete item from list button:
    $('.delete.tool').unbind('click').click(function(){
        var recordID = this.href.substring(this.href.indexOf('delete=')+'delete='.length);
        recordID = decodeURIComponent(recordID.split('&')[0]);
        var listID = this.href.substring(this.href.lastIndexOf('/')+1);
        listID = decodeURIComponent(listID.split('?')[0]);
        if(listID == 'Favorites') listID = '';
        var message = $(this).attr('title');
        var postParams = {'delete': recordID, 'source': extractSource(this)};
        getLightbox('MyResearch', 'MyList', listID, '', message, 'MyResearch', 'MyList', listID, postParams);

        return false;
    });
}
