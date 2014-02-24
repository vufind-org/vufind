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
        'print.css:print'
    ),
    'js' => array(
        'core/jquery.min.js',
        'core/bootstrap.js',
        'common.js',
        'lightbox.min.js',
        'rc4.js'
    ),
    'favicon' => 'vufind-favicon.ico',
    'helpers' => array(
        'factories' => array(
            'flashmessages' => array('VuFind\View\Helper\Bootstrap\Factory', 'getFlashmessages'),
            'layoutclass' => array('VuFind\View\Helper\Bootstrap\Factory', 'getLayoutClass'),
        ),
        'invokables' => array(
            'highlight' => 'VuFind\View\Helper\Bootstrap\Highlight',
            'search' => 'VuFind\View\Helper\Bootstrap\Search',
            'vudl' => 'VuDL\View\Helper\Bootstrap\VuDL',
        )
    )
);
