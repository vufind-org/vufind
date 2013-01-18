/*global documentID*/

var currTab = 2;
var loadingThumbs = true;
var noHit;

// --- TAB ACTIONS --- //
function showPages() {
    $('.doc_list').hide();
    $('.page_list').show();
    var pL = $('.page_list .page_link');
    if(pL.length == 0) {
        $('.page_list').html('<br/><br/><br/>'+noHit+'<br/><br/><br/>');
        $('.information, .original, .preview, .zoomFrame').hide();
    }
}
function showDocs() {
    $('.page_list').hide();
    $('.doc_list').show();
    var pL = $('.doc_list .page_link');
    if(pL.length == 0) {
        $('.doc_list').html('<br/><br/><br/>'+noHit+'<br/><br/><br/>');
    }
}

var currentPreview = "";
function showPreview(src,tab) {
    var tText = tab.innerHTML;
    $('.information, .original, .zoomFrame').hide();
    if(currentPreview !== src) {
        tab.innerHTML = "...";
        currentPreview = src;
        $('<img/>')
            .attr({
                'id':'preview',
                'src':src
            })
            .load(function() {
                tab.innerHTML = tText;
                $('#preview').replaceWith(this);
            });
    }
    $('.preview').show();
}

function showOriginal(src) {
    if(src.length > 0) {
        $('.original').html('Please <a href="mailto:digitallibrary@villanova.edu?subject=Hi-Res%20Request%20for%20'+src+'">email</a> us for access to the Hi-Res image.');
    } else {
        $('.original').html('Original Image File Does Not Exist');
    }
    $('.information, .preview, .zoomFrame').hide();
    $('.original').show();
}

function showZoom(src,tab) {
    var tText = tab.innerHTML;
    tab.innerHTML = "...";
    $('.zoomFrame').inspector(src);
    $('.information, .original, .preview').hide();
    $('.zoomFrame').show();
    tab.innerHTML = tText;
}

function showInfo() {
    $('.original, .preview, .zoomFrame').hide();
    $('.information').show();
}

// --- PAGE LINK ACTIONS --- //
var pages;
var currentPage = 0;
function setTabs(srcs) {
    var tabs = '<a onClick="showOriginal(\''+srcs['original']+'\')">Original</a>'+
                         '<a onClick="showPreview(\''+srcs['large']+'\',this)">Large</a>'+
                         '<a onClick="showPreview(\''+srcs['medium']+'\',this)">Medium</a>'+
                         '<a onClick="showZoom(\''+srcs['large']+'\',this)">Zoom</a>'+
                         '<a onClick="showInfo()">Information</a>';
    $(".view .navigation").html(tabs);
    // - Re-assign the click event handlers
    $('.view .navigation a').each(function (index) {
        $(this).click(function () {
            $('.view .navigation a.selected').removeClass('selected');
            $(this).addClass('selected');
            currTab = index;
        });
        if(index == currTab) {      // SET THE MIDDLE TAB (medium) TO THE ACTIVE ONE
            $(this).click();
        }
    });
}
function loadPage(page) {
    currentPage = page;
    if(!pages[page]) {
        $.get('page-tabs?page='+page+'&id='+documentID,function(response) {
            pages[page] = $.parseJSON(response);
            setTabs(pages[page]);
        });
    } else {
        setTabs(pages[page]);
    }
}
function setPageLinkClicks() {
    $('.page_link').each(function (index) {
        if($(this).is('.new')) {
            $(this).click(function () {
                $('.page_link.selected').removeClass('selected');
                $(this).addClass('selected');
                loadPage(index);
                //console.log('click '+index);
            });
            $(this).removeClass('new');
        }
    });
    if($('.page_list .page_link.selected').size() == 0) {
        loadPage(0);
        $('.page_list .page_link:first-child').addClass('selected');
    }
}
function createPageLinks() {
    loadingThumbs = true;
    var currEnd = $('.page_list .page_link').size();
    //console.log(currEnd);
    if(currEnd >= pages.length) {
        $('.side-loading').css({'display':'none'});
    } else if(pages[currEnd]) {
        $('<div class="page_link new"><img src="'+pages[currEnd]['thumbnail']+'">'+pages[currEnd]['label']+'</div>').insertBefore('.side-loading');
        // Make sure we're clear so that this lock doesn't go balistic
        var pageList = $('.page_list');
        if (pageList.scrollHeight-pageList.scrollTop-pageList.offsetHeight < 50) {
            createPageLinks();
        } else {
            loadingThumbs = false;
        }
    }
    setPageLinkClicks();
}
function selectPage(newPage) {
    currentPage = newPage;
    loadPage(newPage);
    var pageList = $('.page_list');
    pageList.scrollTop(0);
    pageList.find('.page_link.selected').removeClass('selected');
    var selected = pageList.find('.page_link:nth-child('+(newPage+1)+')');
    selected.addClass('selected');
    pageList.scrollTop(selected[0].offsetTop-50);
    if(pageList[0].scrollHeight-pageList[0].scrollTop-pageList[0].offsetHeight < 50) {
        if(!loadingThumbs) {
            createPageLinks();
        }
    }
}
function firstPage() {selectPage(0);}
function prevPage() {selectPage(Math.max(0,currentPage-1));}
function nextPage() {
    var newpage = Math.min(pages.length-1,currentPage+1);
    if(!pages[newpage]) {
        createPageLinks();
    }
    selectPage(newpage);
}
function lastPage() {
    // Load all thumbnails
    var currEnd = $('.page_list .page_link').size();
    while(currEnd < pages.length) {
        $('<div class="page_link new"><img src="'+pages[currEnd]['thumbnail']+'">'+pages[currEnd]['label']+'</div>').insertBefore('.side-loading');
        currEnd++;
    }
    $('.side-loading').css({'display':'none'});
    setPageLinkClicks();
    selectPage(pages.length-1);
}

// fit preview to screen
function resizePreview() {
    var height = $(window).height()+$(this).scrollTop()-20-$('.preview').offset().top;
    $('.preview').css({'height':height});
}

// --- DOCUMENT READY --- //
$(document).ready(function() {  // -- document ready -- //
    loadingThumbs = false;

    $('.page_list').css({'height':window.innerHeight-$('.page_link').offset().top-50});
    $('.zoomFrame').css({
        'width' :$('.view .navigation').width(),
        'height':window.innerHeight-$('.side_nav').offset().top-75
    });

    // PAGE LIST NAVIGATION
    $('.side_nav .top').each(function (index) {
        $(this).click(function () {
            $('a.top.selected').removeClass('selected');
            $(this).addClass('selected');
        });
        if(index == 0) {
            $(this).click();
        }
    });

    // fit preview to screen
    resizePreview();

    $('.page_list').scroll(function() {
        if(this.scrollHeight-this.scrollTop-this.offsetHeight < 50) {
            if(!loadingThumbs) {
                createPageLinks();
            }
        }
    }).scrollTop(0);

}); // - end ready -

$(window)
    .resize(function() {
        // fit preview to screen
        resizePreview();
    })
    .scroll(function() {
        // AJAX thumbnail loading
        var pageList = $('.page_list');
        if(pageList.offset().top+pageList.height() < window.innerHeight) { // if the last one is visible, load the next one
            //console.log(!loadingThumbs);
            if(!loadingThumbs) {
                createPageLinks();
            }
        }
    });