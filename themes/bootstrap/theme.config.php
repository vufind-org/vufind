<?php
return array(
    'extends' => 'root',
    'less' => array(
        'style.less' // imports screen, print
    ),
    'sass' => array(
        //'style.scss' // imports screen, print
    ),
    'css' => array(
        'font/font-awesome.min.css',
        'font/font-awesome-ie7.min.css',
        'slider.css'
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
