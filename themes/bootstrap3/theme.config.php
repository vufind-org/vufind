<?php
return [
    'extends' => 'root',
    'css' => [
        //'vendor/bootstrap.min.css',
        //'vendor/bootstrap-accessibility.css',
        //'vendor/font-awesome.min.css',
        //'bootstrap-custom.css',
        'compiled.css',
        'print.css:print',
        'flex-fallback.css::lt IE 10', // flex polyfill
    ],
    'js' => [
        'vendor/jquery.min.js',
        'vendor/bootstrap.min.js',
        'vendor/bootstrap-accessibility.min.js',
        'vendor/validator.min.js',
        'lib/form-attr-polyfill.js', // input[form] polyfill, cannot load conditionally, since we need all versions of IE
        'lib/autocomplete.js',
        'common.js',
        'lightbox.js',
    ],
    'less' => [
        'active' => false,
        'compiled.less'
    ],
    'favicon' => 'vufind-favicon.ico',
    'helpers' => [
        'factories' => [
            'VuFind\View\Helper\Bootstrap3\Flashmessages' => 'VuFind\View\Helper\Root\FlashmessagesFactory',
            'VuFind\View\Helper\Bootstrap3\Highlight' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Bootstrap3\LayoutClass' => 'VuFind\View\Helper\Bootstrap3\LayoutClassFactory',
            'VuFind\View\Helper\Bootstrap3\Search' => 'Laminas\ServiceManager\Factory\InvokableFactory',
        ],
        'aliases' => [
            'flashmessages' => 'VuFind\View\Helper\Bootstrap3\Flashmessages',
            'highlight' => 'VuFind\View\Helper\Bootstrap3\Highlight',
            'layoutClass' => 'VuFind\View\Helper\Bootstrap3\LayoutClass',
            'search' => 'VuFind\View\Helper\Bootstrap3\Search'
        ]
    ]
];
