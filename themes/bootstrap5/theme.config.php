<?php
return [
    'extends' => 'root',
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
        ['file' => 'vendor/popper.min.js', 'priority' => 120],
        ['file' => 'vendor/bootstrap.min.js', 'priority' => 130],
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
        ['file' => 'validation.js', 'priority' => 400],
        ['file' => 'bs3-compat.js', 'priority' => 1000],
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
        'defaultSet' => 'FontAwesome',
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
            'FontAwesome' => [
                // Specifically Font Awesome 4.7
                'template' => 'font',
                'prefix' => 'fa fa-',
                // Right now, FontAwesome is bundled into compiled.css; when we no
                // longer globally rely on FA (by managing all icons through the
                // helper), we should change this to 'vendor/font-awesome.min.css'
                // so it only loads conditionally when icons are used.
                'src' => 'compiled.css',
            ],
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
            'addthis-bookmark' => 'FontAwesome:bookmark-o',
            'barcode' => 'FontAwesome:barcode',
            'browzine-issue' => 'Alias:format-serial',
            'browzine-pdf' => 'FontAwesome:file-pdf-o',
            'browzine-retraction' => 'FontAwesome:exclamation',
            'cart' => 'FontAwesome:suitcase',
            'cart-add' => 'FontAwesome:plus',
            'cart-empty' => 'FontAwesome:times',
            'cart-remove' => 'FontAwesome:minus-circle',
            'cite' => 'FontAwesome:asterisk',
            'cites' => 'Unicode:275D',
            'cited-by' => 'Unicode:275E',
            'collapse' => 'Collapse:_', // uses the icons below
            'collapse-close' => 'FontAwesome:chevron-up',
            'collapse-open' => 'FontAwesome:chevron-down',
            'cover-replacement' => 'FontAwesome:question',
            'currency-eur' => 'FontAwesome:eur',
            'currency-gbp' => 'FontAwesome:gbp',
            'currency-inr' => 'FontAwesome:inr',
            'currency-jpy' => 'FontAwesome:jpy',
            'currency-krw' => 'FontAwesome:krw',
            'currency-rmb' => 'FontAwesome:rmb',
            'currency-rub' => 'FontAwesome:rub',
            'currency-try' => 'FontAwesome:try',
            'currency-usd' => 'FontAwesome:usd',
            'currency-won' => 'FontAwesome:won',
            'currency-yen' => 'FontAwesome:yen',
            'dropdown-caret' => 'FontAwesome:caret-down',
            'export' => 'FontAwesome:external-link',
            'external-link' => 'FontAwesome:link',
            'facet-applied' => 'FontAwesome:check',
            'facet-checked' => 'FontAwesome:check-square-o',
            'facet-collapse' => 'FontAwesome:caret-down',
            'facet-exclude' => 'FontAwesome:times',
            'facet-expand' => 'FontAwesome:caret-right',
            'facet-noncollapsible' => 'FontAwesome:none',
            'facet-unchecked' => 'FontAwesome:square-o',
            'feedback' => 'FontAwesome:envelope',
            'format-atlas' => 'FontAwesome:compass',
            'format-book' => 'FontAwesome:book',
            'format-braille' => 'FontAwesome:hand-o-up',
            'format-cdrom' => 'FontAwesome:laptop',
            'format-chart' => 'FontAwesome:signal',
            'format-chipcartridge' => 'FontAwesome:laptop',
            'format-collage' => 'FontAwesome:picture-o',
            'format-default' => 'FontAwesome:book',
            'format-disccartridge' => 'FontAwesome:laptop',
            'format-drawing' => 'FontAwesome:picture-o',
            'format-ebook' => 'FontAwesome:file-text-o',
            'format-electronic' => 'FontAwesome:file-archive-o',
            'format-file' => 'FontAwesome:file-o',
            'format-filmstrip' => 'FontAwesome:film',
            'format-flashcard' => 'FontAwesome:bolt',
            'format-floppydisk' => 'FontAwesome:save',
            'format-folder' => 'FontAwesome:folder',
            'format-globe' => 'FontAwesome:globe',
            'format-journal' => 'FontAwesome:file-text-o',
            'format-kit' => 'FontAwesome:briefcase',
            'format-manuscript' => 'FontAwesome:file-text-o',
            'format-map' => 'FontAwesome:compass',
            'format-microfilm' => 'FontAwesome:film',
            'format-motionpicture' => 'FontAwesome:video-camera',
            'format-musicalscore' => 'FontAwesome:music',
            'format-musicrecording' => 'FontAwesome:music',
            'format-newspaper' => 'FontAwesome:file-text-o',
            'format-online' => 'FontAwesome:laptop',
            'format-painting' => 'FontAwesome:picture-o',
            'format-photo' => 'FontAwesome:picture-o',
            'format-photonegative' => 'FontAwesome:picture-o',
            'format-physicalobject' => 'FontAwesome:archive',
            'format-print' => 'FontAwesome:picture-o',
            'format-sensorimage' => 'FontAwesome:picture-o',
            'format-serial' => 'FontAwesome:file-text-o',
            'format-slide' => 'FontAwesome:film',
            'format-software' => 'FontAwesome:laptop',
            'format-soundcassette' => 'FontAwesome:headphones',
            'format-sounddisc' => 'FontAwesome:laptop',
            'format-soundrecording' => 'FontAwesome:headphones',
            'format-tapecartridge' => 'FontAwesome:laptop',
            'format-tapecassette' => 'FontAwesome:headphones',
            'format-tapereel' => 'FontAwesome:film',
            'format-transparency' => 'FontAwesome:film',
            'format-unknown' => 'FontAwesome:question',
            'format-video' => 'FontAwesome:video-camera',
            'format-videocartridge' => 'FontAwesome:video-camera',
            'format-videocassette' => 'FontAwesome:video-camera',
            'format-videodisc' => 'FontAwesome:laptop',
            'format-videoreel' => 'FontAwesome:video-camera',
            'hierarchy-collapse' => 'Alias:facet-collapse',
            'hierarchy-collection' => 'FontAwesome:folder-open-o',
            'hierarchy-expand' => 'Alias:facet-expand',
            'hierarchy-noncollapsible' => 'Alias:facet-noncollapsible',
            'hierarchy-record' => 'FontAwesome:file-o',
            'hierarchy-tree' => 'FontAwesome:sitemap',
            'lightbox-close' => 'FontAwesome:times',
            'more' => 'FontAwesome:chevron-circle-right',
            'more-rtl' => 'FontAwesome:chevron-circle-left',
            'my-account' => 'FontAwesome:user-circle-o',
            'my-account-notification' => 'Alias:notification',
            'my-account-warning' => 'Alias:warning',
            'notification' => 'FontAwesome:bell',
            'offcanvas-hide-left' => 'FontAwesome:angle-right',
            'offcanvas-hide-right' => 'FontAwesome:angle-left',
            'offcanvas-show-left' => 'FontAwesome:angle-double-left',
            'offcanvas-show-right' => 'FontAwesome:angle-double-right',
            'options' => 'FontAwesome:gear',
            'overdrive' => 'FontAwesome:download',
            'overdrive-cancel-hold' => 'Alias:ui-cancel',
            'overdrive-checkout' => 'FontAwesome:arrow-left',
            'overdrive-checkout-rtl' => 'FontAwesome:arrow-right',
            'overdrive-download' => 'FontAwesome:download',
            'overdrive-edit-hold' => 'Alias:ui-edit',
            'overdrive-edit-hold-suspension' => 'FontAwesome:calendar',
            'overdrive-help' => 'FontAwesome:question-circle',
            'overdrive-place-hold' => 'Alias:place-hold',
            'overdrive-return' => 'FontAwesome:undo',
            'overdrive-return-rtl' => 'FontAwesome:undo',
            'overdrive-sign-in' => 'FontAwesome:sign-in',
            'overdrive-success' => 'FontAwesome:check',
            'overdrive-suspend-hold' => 'Alias:place-hold',
            'overdrive-warning' => 'Alias:warning',
            'page-first' => 'FontAwesome:angle-double-left',
            'page-first-rtl' => 'FontAwesome:angle-double-right',
            'page-last' => 'FontAwesome:angle-double-right',
            'page-last-rtl' => 'FontAwesome:angle-double-left',
            'page-next' => 'FontAwesome:angle-right',
            'page-next-rtl' => 'FontAwesome:angle-left',
            'page-prev' => 'FontAwesome:angle-left',
            'page-prev-rtl' => 'FontAwesome:angle-right',
            'place-hold' => 'FontAwesome:flag',
            'place-ill-request' => 'FontAwesome:exchange',
            'place-recall' => 'FontAwesome:flag',
            'place-storage-retrieval' => 'FontAwesome:truck',
            'print' => 'FontAwesome:print',
            'profile' => 'FontAwesome:user',
            'profile-card-delete' => 'Alias:ui-delete',
            'profile-card-edit' => 'Alias:ui-edit',
            'profile-change-password' => 'FontAwesome:key',
            'profile-delete' => 'Alias:ui-delete',
            'profile-edit' => 'Alias:ui-edit',
            'profile-email' => 'FontAwesome:envelope',
            'profile-sms' => 'FontAwesome:phone',
            'qrcode' => 'FontAwesome:qrcode',
            'rating-half' => 'FontAwesome:star-half',
            'rating-full' => 'FontAwesome:star',
            'search' => 'FontAwesome:search',
            'search-delete' => 'Alias:ui-delete',
            'search-filter-remove' => 'FontAwesome:times',
            'search-rss' => 'FontAwesome:rss',
            'search-save' => 'Alias:ui-save',
            'search-schedule-alert' => 'FontAwesome:exclamation-circle',
            'send-email' => 'FontAwesome:envelope',
            'send-sms' => 'FontAwesome:phone',
            'sign-in' => 'FontAwesome:sign-in',
            'sign-out' => 'FontAwesome:sign-out',
            'spinner' => 'FontAwesome:spinner:icon--spin',
            'status-available' => 'FontAwesome:check',
            'status-pending' => 'FontAwesome:clock-o',
            'status-ready' => 'FontAwesome:bell',
            'status-unavailable' => 'FontAwesome:times',
            'status-unknown' => 'FontAwesome:circle',
            'tag-add' => 'Alias:ui-add',
            'tag-remove' => 'Alias:ui-remove',
            'tree-context' => 'FontAwesome:sitemap',
            'truncate-less' => 'FontAwesome:arrow-up',
            'truncate-more' => 'FontAwesome:arrow-down',
            'ui-add' => 'FontAwesome:plus-circle',
            'ui-cancel' => 'FontAwesome:ban',
            'ui-close' => 'FontAwesome:times',
            'ui-delete' => 'FontAwesome:trash-o',
            'ui-dots-menu' => 'FontAwesome:ellipsis-h',
            'ui-edit' => 'FontAwesome:edit',
            'ui-failure' => 'FontAwesome:times',
            'ui-menu' => 'FontAwesome:bars',
            'ui-remove' => 'FontAwesome:times',
            'ui-reset-search' => 'Alias:ui-remove',
            'ui-save' => 'FontAwesome:floppy-o',
            'ui-success' => 'FontAwesome:check',
            'user-checked-out' => 'FontAwesome:book',
            'user-favorites' => 'FontAwesome:star',
            'user-holds' => 'FontAwesome:flag',
            'user-ill-requests' => 'FontAwesome:exchange',
            'user-list' => 'FontAwesome:list',
            'user-list-add' => 'FontAwesome:bookmark-o',
            'user-list-delete' => 'Alias:ui-delete',
            'user-list-edit' => 'Alias:ui-edit',
            'user-list-entry-edit' => 'Alias:ui-edit',
            'user-list-remove' => 'Alias:ui-remove',
            'user-loan-history' => 'FontAwesome:history',
            'user-public-list-indicator' => 'FontAwesome:globe',
            'user-storage-retrievals' => 'FontAwesome:archive',
            'view-grid' => 'FontAwesome:th',
            'view-list' => 'FontAwesome:list',
            'view-visual' => 'FontAwesome:th-large',
            'warning' => 'FontAwesome:exclamation-triangle',
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
