<?php
return array(
    'extends' => 'root',
    'css' => array(
        'bootstrap.min.css',
        'bootstrap-responsive.min.css',
        'font-awesome.css',
        'font-awesome-ie7.min.css',
        'slider.css',
        'screen.css',
        'bootstrap-custom.css',
        'print.css:print'
    ),
    'js' => array(
        'core/jquery.min.js',
        'core/bootstrap.min.js',
        'common.js',
        'lightbox.js',
        'rc4.js'
    ),
    'favicon' => 'vufind-favicon.ico',
    'helpers' => array(
        'factories' => array(
            'flashmessages' => 'VuFind\View\Helper\Bootstrap\Factory::getFlashmessages',
            'layoutclass' => 'VuFind\View\Helper\Bootstrap\Factory::getLayoutClass',
        ),
        'invokables' => array(
            'highlight' => 'VuFind\View\Helper\Bootstrap\Highlight',
            'search' => 'VuFind\View\Helper\Bootstrap\Search',
            'vudl' => 'VuDL\View\Helper\Bootstrap\VuDL',
        )
    )
);
