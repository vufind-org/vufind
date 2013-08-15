<?php
return array(
    'extends' => 'root',
    'css' => array(
        'jquery.mobile-1.3.2.min.css',
        'styles.css',
        'formats.css',
    ),
    'js' => array(
        'jquery-1.9.1.min.js',
        'common.js',
        'jquery.mobile-1.3.2.min.js',
        'jquery.cookie.js',
        'scripts.js',
    ),
    'favicon' => 'vufind-favicon.ico',
    'helpers' => array(
        'invokables' => array(
            'mobilemenu' => 'VuFind\View\Helper\jQueryMobile\MobileMenu'
        )
    ),
);
