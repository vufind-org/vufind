
<?php
return [
    'extends' => 'root',
    'mixins' => [
        'mixin-icons-fontawesome4',
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
         * - conditional: e.g. '!IE'
         * - extras: array of additional attributes
         *
         * Strings are supported for backwards compatibility reasons. examples:
         * - 'example.css' => same as ['file' => 'example.css']
         * - 'example.css:print:!IE' => same as
         *   ['file' => 'example.css', 'media' => 'print', 'conditional' => '!IE']
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
         * - conditional: e.g. 'lt IE 10'
         * - disabled: if set to true in a child theme, the matching file will be
         *   removed if it was included by a parent theme.
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
        ['file' => 'vendor/autocomplete.js', 'priority' => 220],
        ['file' => 'lib/ajax_request_queue.js', 'priority' => 230],
        ['file' => 'common.js', 'priority' => 310],
        ['file' => 'config.js', 'priority' => 320],
        ['file' => 'lightbox.js', 'priority' => 330],
        ['file' => 'searchbox_controls.js', 'priority' => 340],
        ['file' => 'truncate.js', 'priority' => 350],
        ['file' => 'trigger_print.js', 'priority' => 360],
        ['file' => 'observer_manager.js', 'priority' => 370],
        ['file' => 'openurl.js', 'priority' => 380],
        ['file' => 'list_item_selection.js', 'priority' => 390],
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
            'VuFind\View\Helper\Bootstrap3\BulkAction' => 'VuFind\View\Helper\Root\BulkActionFactory',
            'VuFind\View\Helper\Bootstrap3\CopyToClipboardButton' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Bootstrap3\Flashmessages' => 'VuFind\View\Helper\Root\FlashmessagesFactory',
            'VuFind\View\Helper\Bootstrap3\Highlight' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFind\View\Helper\Bootstrap3\LayoutClass' => 'VuFind\View\Helper\Bootstrap3\LayoutClassFactory',
            'VuFind\View\Helper\Bootstrap3\Search' => 'Laminas\ServiceManager\Factory\InvokableFactory',
        ],
        'aliases' => [
            'bulkAction' => 'VuFind\View\Helper\Bootstrap3\BulkAction',
            'copyToClipboardButton' => 'VuFind\View\Helper\Bootstrap3\CopyToClipboardButton',
            'flashmessages' => 'VuFind\View\Helper\Bootstrap3\Flashmessages',
            'highlight' => 'VuFind\View\Helper\Bootstrap3\Highlight',
            'layoutClass' => 'VuFind\View\Helper\Bootstrap3\LayoutClass',
            'search' => 'VuFind\View\Helper\Bootstrap3\Search',
        ],
    ],
    'icons' => [
        'defaultSet' => 'FontAwesome4',
        'sets' => [
            /**
             * Define icon sets here.
             *
             * All sets need:
             * - 'template': which template the icon renders with
             * - 'src': the location of the relevant resource (font, css, images)
             * - 'prefix': prefix to place before each icon name for convenience
             *             (ie. fa fa- for FontAwesome, default "")
             */
            'Collapse' => [
                'template' => 'collapse',
            ],
            // Unicode symbol characters. Icons are defined as hex code points.
            'Unicode' => [
                'template' => 'unicode',
            ],
            /* For an example of an images set, see Bootprint's theme.config.php. */
        ],
        'aliases' => [
            /**
             * Icons can be assigned or overridden here
             *
             * Format: 'icon' => [set:]icon[:extra_classes]
             * Icons assigned without set will use the defaultSet.
             * In order to specify extra CSS classes, you must also specify a set.
             *
             * All of the items below have been specified with FontAwesome to allow
             * for a strong inheritance safety net but this is not required.
             */
            'cites' => 'Unicode:275D',
            'cited-by' => 'Unicode:275E',
            'collapse' => 'Collapse:_',
        ],
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
