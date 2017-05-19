<?php
return array(
    'extends' => 'root',
    'css' => array(
        //'vendor/bootstrap.min.css',
        //'vendor/bootstrap-accessibility.css',
        //'vendor/font-awesome.min.css',
        //'bootstrap-custom.css',
        'compiled.css',
        'print.css:print',
    ),
    'js' => array(
        'vendor/base64.js:lt IE 10', // btoa polyfill
        'vendor/jquery.min.js:critical',
        'vendor/bootstrap.min.js',
        'vendor/bootstrap-accessibility.min.js',
        'vendor/validator.min.js',
        'lib/form-attr-polyfill.js', // input[form] polyfill, cannot load conditionally, since we need all versions of IE
        'lib/autocomplete.js',
        'common.js:critical',
        'lightbox.js',
    ),
    'less' => array(
        'active' => false,
        'compiled.less'
    ),
    'favicon' => 'vufind-favicon.ico',
    'helpers' => array(
        'factories' => array(
            'flashmessages' => 'VuFind\View\Helper\Bootstrap3\Factory::getFlashmessages',
            'layoutclass' => 'VuFind\View\Helper\Bootstrap3\Factory::getLayoutClass',
            'recaptcha' => 'VuFind\View\Helper\Bootstrap3\Factory::getRecaptcha',
        ),
        'invokables' => array(
            'highlight' => 'VuFind\View\Helper\Bootstrap3\Highlight',
            'search' => 'VuFind\View\Helper\Bootstrap3\Search'
        )
    )
);
