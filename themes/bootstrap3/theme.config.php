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
        /**
         * Entries in this section can either be specified as array or as string.
         * Array format is preferred.
         *
         * Available array options:
         * - file: the path to the file
         * - load_after: Use this to explicitly load the file after the given other
         *   file. This may NOT be used together with a priority setting.
         * - priority: an optional priority (lower value means higher priority).
         *      Default convention for VuFind's own themes:
         *          - 1xx => vendor (third-party code)
         *          - 2xx => VuFind library (general-purpose code)
         *          - 3xx => VuFind scripts (highly VuFind-specific code)
         * - conditional: e.g. 'lt IE 10'
         *
         * Entries with neither priority nor load_after will be loaded after all
         * other entries.
         *
         * Strings are supported for backwards compatibility reasons. examples:
         * - 'example.js' => same as ['file' => 'example.js']
         * - 'example.js:lt IE 10' => same as
         *   ['file' => 'example.js', 'conditional' => 'lt IE 10']
         */
        ['file' => 'vendor/jquery.min.js', 'priority' => 110],
        ['file' => 'vendor/bootstrap.min.js', 'priority' => 120],
        ['file' => 'vendor/bootstrap-accessibility.min.js', 'priority' => 130],
        ['file' => 'vendor/validator.min.js', 'priority' => 140],
        ['file' => 'lib/form-attr-polyfill.js', 'priority' => 210], // input[form] polyfill, cannot load conditionally, since we need all versions of IE
        ['file' => 'lib/autocomplete.js', 'priority' => 220],
        ['file' => 'common.js', 'priority' => 310],
        ['file' => 'lightbox.js', 'priority' => 320],
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
