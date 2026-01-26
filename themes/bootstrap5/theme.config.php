<?php
return [
    'extends' => 'root',
    'mixins' => [
        'mixin-icons-fontawesome-6',
    ],
    'css' => [
        /**
         * Entries in this section can either be specified as array or as string.
         * Array format is preferred.
         *
         * Available array options:
         * - file: the path to the file (either relative to the css directory of your
         *   theme, or a URL)
         * - load_after: Use this to explicitly load the file after the given other
         *   file. This may NOT be used together with a priority setting.
         * - priority: an optional priority (lower value means higher priority).
         *      Default convention for VuFind's own themes:
         *          - 1xx => vendor (third-party code)
         *          - 2xx => VuFind library (general-purpose code)
         *          - 3xx => VuFind scripts (highly VuFind-specific code)
         * - media: e.g. 'print'
         * - extras: array of additional attributes
         *
         * Strings are supported for legacy backwards compatibility reasons. examples:
         * - 'example.css' => same as ['file' => 'example.css']
         * - 'example.css:print' => same as
         *   ['file' => 'example.css', 'media' => 'print']
         */
        ['file' => 'compiled.css'],
        ['file' => 'print.css', 'media' => 'print'],
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
         * - position: 'header' (default) or 'footer'
         * - disabled: if set to true in a child theme, the matching file will be
         *   removed if it was included by a parent theme.
         *
         * Entries with neither priority nor load_after will be loaded after all
         * other entries.
         *
         * Strings are supported for legacy backwards compatibility reasons. example:
         * - 'example.js' => same as ['file' => 'example.js']
         */
        ['file' => 'polyfills.js', 'priority' => 100],
        ['file' => 'vendor/jquery.min.js', 'priority' => 110],
        ['file' => 'vendor/popper.min.js', 'priority' => 120],
        ['file' => 'vendor/bootstrap.min.js', 'priority' => 130],
        ['file' => 'vendor/autocomplete.js', 'priority' => 220],
        ['file' => 'lib/ajax_request_queue.js', 'priority' => 230],
        ['file' => 'common.js', 'priority' => 310],
        ['file' => 'config.js', 'priority' => 320],
        ['file' => 'lightbox.js', 'priority' => 330],
        ['file' => 'cookie.js', 'priority' => 340],
        ['file' => 'searchbox_controls.js', 'priority' => 350],
        ['file' => 'truncate.js', 'priority' => 360],
        ['file' => 'trigger_print.js', 'priority' => 370],
        ['file' => 'observer_manager.js', 'priority' => 380],
        ['file' => 'openurl.js', 'priority' => 390],
        ['file' => 'list_item_selection.js', 'priority' => 400],
        ['file' => 'covers.js', 'priority' => 410],
        ['file' => 'validation.js', 'priority' => 420],
        ['file' => 'copy_to_clipboard.js', 'priority' => 430],
    ],
    /**
     * Configuration for a single or multiple favicons.
     *
     * Can be a single string that is a path to an .ico icon relative to the theme image folder.
     *
     * For multiple favicons the value must be an array of arrays of attributes
     * that will be rendered as link elements.
     *
     * Example:
     *  [
     *      [
     *          'href' => 'favicon-32x32.png',
     *          'rel' => 'icon',
     *          'type' => 'image/png',
     *          'sizes' => '32x32',
     *      ],
     *       [
     *          'href' => 'favicon-180x180.png',
     *          'rel' => 'apple-touch-icon',
     *          'type' => 'image/png',
     *          'sizes' => '180x180',
     *      ],
     *  ]
     */
    'favicon' => 'vufind-favicon.ico',
    'helpers' => [
        'factories' => [
            'VuFind\View\Helper\Bootstrap5\BulkAction' => 'VuFind\View\Helper\Root\BulkActionFactory',
            'VuFind\View\Helper\Bootstrap5\CopyToClipboardButton' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Bootstrap5\Flashmessages' => 'VuFind\View\Helper\Root\FlashmessagesFactory',
            'VuFind\View\Helper\Bootstrap5\Highlight' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Bootstrap5\LayoutClass' => 'VuFind\View\Helper\Bootstrap5\LayoutClassFactory',
            'VuFind\View\Helper\Bootstrap5\Search' => 'Laminas\ServiceManager\Factory\InvokableFactory',
        ],
        'aliases' => [
            'bulkAction' => 'VuFind\View\Helper\Bootstrap5\BulkAction',
            'copyToClipboardButton' => 'VuFind\View\Helper\Bootstrap5\CopyToClipboardButton',
            'flashmessages' => 'VuFind\View\Helper\Bootstrap5\Flashmessages',
            'highlight' => 'VuFind\View\Helper\Bootstrap5\Highlight',
            'layoutClass' => 'VuFind\View\Helper\Bootstrap5\LayoutClass',
            'search' => 'VuFind\View\Helper\Bootstrap5\Search',
        ],
    ],
    'icons' => [
        'defaultSet' => 'FontAwesome6', // from mixin
    ],
    /**
     * Html elements can be made sticky which means that they don't leave the screen on scrolling.
     * You can make an element sticky by adding an array with the css selector to stickyElements.
     * Warning! The order of the entries in the config will be used to order the elements while they are sticky.
     * If you want to add extra classes to some child elements of sticky elements you can add an array with their
     * css selectors and the classes to stickyChildrenClasses. The default class is "hidden".
     * You can also add "min-width" and "max-width" to the configs so that the effect only applies on specific
     * screen sizes.
     * Examples:
     */
    'stickyElements' => [
        // Navbar Banner on non-mobile screens
        //["selector" => ".banner.container.navbar", "min-width" => 768],
        // Searchbox on search home page
        //["selector" => ".searchHomeContent"],
        // Searchbox on other pages
        //["selector" => ".search.container.navbar"],
        // Breadcrumbs on non-mobile screens
        //["selector" => ".breadcrumbs", "min-width" => 768]
    ],
    'stickyChildrenClasses' => [
        // Hide search tab selection on mobile screens
        //["selector" => ".searchForm > .nav.nav-tabs", "class" => "hidden", "max-width" => 767]
    ],
    'doctype' => 'HTML5',
];
