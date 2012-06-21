$('[data-role="page"]').live('pageshow', function() {
    var items = getItemsFromCartCookie();
    updateCartSummary(items);
    updateCheckboxStates(items);
});

$('.add_to_book_bag').live('click', function(){
    var id = $(this).attr('data-record-id');
    var $icon = $('.ui-icon', this).first();
    if ($icon.hasClass('ui-icon-plus')) {
        $icon.removeClass('ui-icon-plus').addClass('ui-icon-check');
        updateCartSummary(addItemToCartCookie(id));
    } else if ($icon.hasClass('ui-icon-check')) {
        $icon.removeClass('ui-icon-check').addClass('ui-icon-plus');
        updateCartSummary(removeItemFromCartCookie(id));
    }        
    return false;
});

$('.remove_from_book_bag').live('click', function(){
    var id = $(this).attr('data-record-id'); 
    updateCartSummary(removeItemFromCartCookie(id));
    $li = $(this).parent('li');
    $ul = $li.parent('ul');
    if ($ul.children('li').size() == 1) {
        $ul.parent().empty().append('<p>' + _translations['Your book bag is empty'] + '.</p>');
    } else {
        $li.remove();
    }
    return false;
});

$('.empty_book_bag').live('click', function() {
    updateCartSummary(emptyCartCookie());
    $('.bookbag').parent().empty().append('<p>' + _translations['Your book bag is empty'] + '.</p>');
    return false;
});

function updateCheckboxStates(items) {
    $('.add_to_book_bag').each(function(){
        var id = $(this).attr('data-record-id');
        var $icon = $('.ui-icon', this).first();
        if (items.indexOf(id) != -1) {            
            $icon.removeClass('ui-icon-plus').addClass('ui-icon-check');
        } else {
            $icon.removeClass('ui-icon-check').addClass('ui-icon-plus');
        }
    });
}

function updateCartSummary(items) {
    $summary = $('.cart_size');
    if ($summary.size() > 0) {
        $summary.empty().append(items.length);
    }
    // workaround to force book bag dialog to be reloaded every time
    $button = $('a.book_bag_btn');
    if ($button.size() > 0) {
        $button.attr('href', $button.attr('href').replace(/Home.*/, 'Home?_='+(new Date()).getTime()));
    }
}
