<?php
return array(
    'extends' => 'root',
    'css' => array(
        //'bootstrap.min.css',
        //'bootstrap-accessibility.css',
        //'bootstrap-custom.css',
        'compiled.css',
        'font-awesome/font-awesome.css',
        'print.css:print',
        'slider.css',
    ),
    'js' => array(
        'vendor/jquery.min.js',
        'vendor/bootstrap.min.js',
        'vendor/bootstrap-accessibility.min.js',
        'vendor/typeahead.js',
        'vendor/rc4.js',
        'common.js',
        'lightbox.js',
    ),
    'less' => array(
        //'compiled.less',
        //'font-awesome/font-awesome.less'
    ),
    'scss' => array(
        //'compiled.scss',
        //'font-awesome.scss'
    ),
    'favicon' => 'vufind-favicon.ico',
    'helpers' => array(
        'factories' => array(
            'flashmessages' => 'VuFind\View\Helper\Bootstrap3\Factory::getFlashmessages',
            'layoutclass' => 'VuFind\View\Helper\Bootstrap3\Factory::getLayoutClass',
        ),
        'invokables' => array(
            'highlight' => 'VuFind\View\Helper\Bootstrap3\Highlight',
            'search' => 'VuFind\View\Helper\Bootstrap3\Search',
            'vudl' => 'VuDL\View\Helper\Bootstrap3\VuDL',
        )
    )
);
