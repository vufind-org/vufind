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
        'defaultSet' => 'FontAwesome6',
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
            'FontAwesome6' => [
                // Specifically Font Awesome 4.7
                'template' => 'font',
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
            'addthis-bookmark' => 'fa-regular fa-bookmark-o',
            'barcode' => 'fa-solid fa-barcode',
            'browzine-issue' => 'Alias:format-serial',
            'browzine-pdf' => 'fa-regular fa-file-pdf-o',
            'browzine-retraction' => 'fa-solid fa-exclamation',
            'cart' => 'fa-solid fa-briefcase',
            'cart-add' => 'fa-solid fa-plus',
            'cart-empty' => 'fa-solid fa-xmark',
            'cart-remove' => 'fa-solid fa-circle-minus',
            'cite' => 'fa-solid fa-asterisk',
            'cited-by' => 'fa-solid fa-asterisk',
            'cites' => 'fa-solid fa-asterisk',
            'collapse' => 'Collapse:_', // uses the icons below
            'collapse-close' => 'fa-solid fa-chevron-up',
            'collapse-open' => 'fa-solid fa-chevron-down',
            'cover-replacement' => 'fa-solid fa-clipboard-question',
            'currency-eur' => 'fa-solid fa-euro-sign',
            'currency-gbp' => 'fa-solid fa-sterling-sign',
            'currency-inr' => 'fa-solid fa-rupee-sign',
            'currency-jpy' => 'fa-solid fa-yen-sign',
            'currency-krw' => 'fa-solid fa-won-sign',
            'currency-rmb' => 'fa-solid fa-yen-sign',
            'currency-rub' => 'fa-solid fa-ruble-sign',
            'currency-try' => 'fa-solid fa-lira-sign',
            'currency-usd' => 'fa-solid fa-dollar-sign',
            'currency-won' => 'fa-solid fa-won-sign',
            'currency-yen' => 'fa-solid fa-yen-sign',
            'dropdown-caret' => 'fa-solid fa-caret-down',
            'export' => 'fa-solid fa-file-export',
            'external-link' => 'fa-solid fa-link',
            'facet-applied' => 'fa-solid fa-check',
            'facet-checked' => 'fa-solid fa-square-check',
            'facet-collapse' => 'fa-solid fa-caret-down',
            'facet-exclude' => 'fa-solid fa-xmark',
            'facet-expand' => 'fa-solid fa-caret-right',
            'facet-noncollapsible' => 'fa-solid fa-ban',
            'facet-unchecked' => 'fa-regular fa-square',
            'feedback' => 'fa-solid fa-envelope',
            'format-atlas' => 'fa-regular fa-map',
            'format-book' => 'fa-solid fa-book',
            'format-braille' => 'fa-regular fa-hand',
            'format-cdrom' => 'fa-regular fa-compact-disc',
            'format-chart' => 'fa-solid fa-chart-area',
            'format-chipcartridge' => 'fa-solid fa-microchip',
            'format-collage' => 'fa-regular fa-file-image',
            'format-default' => 'fa-solid fa-book',
            'format-disccartridge' => 'fa-regular fa-floppy-disk',
            'format-drawing' => 'fa-solid fa-pencil',
            'format-ebook' => 'fa-solid fa-book-atlas',
            'format-electronic' => 'fa-regular fa-file-zipper',
            'format-file' => 'fa-regular fa-file',
            'format-filmstrip' => 'fa-solid fa-film',
            'format-flashcard' => 'fa-regular fa-images',
            'format-floppydisk' => 'fa-regular fa-floppy-disk',
            'format-folder' => 'fa-regular fa-folder-closed',
            'format-globe' => 'fa-solid fa-globe',
            'format-journal' => 'fa-regular fa-newspaper',
            'format-kit' => 'fa-solid fa-toolbox',
            'format-manuscript' => 'fa-solid fa-scroll',
            'format-map' => 'fa-regular fa-map',
            'format-microfilm' => 'fa-solid fa-film',
            'format-motionpicture' => 'fa-solid fa-clapperboard',
            'format-musicalscore' => 'fa-solid fa-music',
            'format-musicrecording' => 'fa-solid fa-microphone-lines',
            'format-newspaper' => 'fa-regular fa-newspaper',
            'format-online' => 'fa-solid fa-network-wired',
            'format-painting' => 'fa-solid fa-brush',
            'format-photo' => 'fa-regular fa-image',
            'format-photonegative' => 'fa-solid fa-photo-film',
            'format-physicalobject' => 'fa-solid fa-box-archive',
            'format-print' => 'fa-regular fa-image',
            'format-sensorimage' => 'fa-solid fa-camera-retro',
            'format-serial' => 'fa fa-file-text-o',
            'format-slide' => 'fa-solid fa-film',
            'format-software' => 'fa-solid fa-laptop-code',
            'format-soundcassette' => 'fa-solid fa-headphones-simple',
            'format-sounddisc' => 'fa-solid fa-compact-disc',
            'format-soundrecording' => 'fa-solid fa-microphone-lines',
            'format-tapecartridge' => 'fa-solid fa-headphones-simple',
            'format-tapecassette' => 'fa-solid fa-headphones-simple',
            'format-tapereel' => 'fa-solid fa-tape',
            'format-transparency' => 'fa-solid fa-person-chalkboard',
            'format-unknown' => 'fa-solid fa-icons',
            'format-video' => 'fa-regular fa-circle-play',
            'format-videocartridge' => 'fa-solid fa-video',
            'format-videocassette' => 'fa-solid fa-video',
            'format-videodisc' => 'fa-solid fa-compact-disc',
            'format-videoreel' => 'fa-solid fa-tape',
            'hierarchy-collapse' => 'Alias:facet-collapse',
            'hierarchy-collection' => 'fa-regular fa-folder-open',
            'hierarchy-expand' => 'Alias:facet-expand',
            'hierarchy-noncollapsible' => 'Alias:facet-noncollapsible',
            'hierarchy-record' => 'fa-regular fa-file',
            'hierarchy-tree' => 'fa-solid fa-folder-tree',
            'lightbox-close' => 'fa-solid fa-xmark',
            'more' => 'fa-solid fa-circle-chevron-right',
            'more-rtl' => 'fa-solid fa-circle-chevron-left',
            'my-account' => 'fa-regular fa-circle-user',
            'my-account-notification' => 'Alias:notification',
            'my-account-warning' => 'Alias:warning',
            'notification' => 'fa-solid fa-bell',
            'offcanvas-hide-left' => 'fa-solid fa-angle-right',
            'offcanvas-hide-right' => 'fa-solid fa-angle-left',
            'offcanvas-show-left' => 'fa-solid fa-angles-left',
            'offcanvas-show-right' => 'fa-solid fa-angles-right',
            'options' => 'fa-solid fa-sliders',
            'overdrive' => 'fa-solid fa-cloud-arrow-down',
            'overdrive-cancel-hold' => 'Alias:ui-cancel',
            'overdrive-checkout' => 'fa-solid fa-arrow-left',
            'overdrive-checkout-rtl' => 'fa-solid fa-arrow-right',
            'overdrive-download' => 'fa-solid fa-download',
            'overdrive-edit-hold' => 'Alias:ui-edit',
            'overdrive-edit-hold-suspension' => 'fa-regular fa-hourglass-half',
            'overdrive-help' => 'fa-solid fa-circle-question',
            'overdrive-place-hold' => 'Alias:place-hold',
            'overdrive-return' => 'fa-solid fa-arrow-rotate-right',
            'overdrive-return-rtl' => 'fa-solid fa-arrow-rotate-left',
            'overdrive-sign-in' => 'fa-regular fa-user',
            'overdrive-success' => 'fa-regular fa-circle-check',
            'overdrive-suspend-hold' => 'Alias:place-hold',
            'overdrive-warning' => 'Alias:warning',
            'overdrive-warning' => 'Alias:warning',
            'page-first' => 'fa-solid fa-angles-left',
            'page-first-rtl' => 'fa-solid fa-angles-right',
            'page-last' => 'fa-solid fa-angles-right',
            'page-last-rtl' => 'fa-solid fa-angles-left',
            'page-next' => 'fa-solid fa-angle-right',
            'page-next-rtl' => 'fa-solid fa-angle-left',
            'page-prev' => 'fa-solid fa-angle-left',
            'page-prev-rtl' => 'fa-solid fa-angle-right',
            'place-hold' => 'fa-solid fa-book-bookmark',
            'place-ill-request' => 'fa-solid fa-arrow-right-arrow-left',
            'place-recall' => 'fa-solid fa-arrow-right-to-bracket',
            'place-storage-retrieval' => 'fa-solid fa-dolly',
            'print' => 'fa-solid fa-print',
            'profile' => 'fa-solid fa-circle-user',
            'profile-card-delete' => 'Alias:ui-delete',
            'profile-card-edit' => 'fa-solid fa-user-pen',
            'profile-change-password' => 'fa-solid fa-key',
            'profile-delete' => 'Alias:ui-delete',
            'profile-edit' => 'fa-solid fa-user-pen',
            'profile-email' => 'fa-solid fa-at',
            'profile-sms' => 'fa-solid fa-mobile-screen',
            'qrcode' => 'fa-solid fa-qrcode',
            'rating-full' => 'fa-solid fa-star',
            'rating-half' => 'fa-solid fa-star-half-stroke',
            'search' => 'fa-solid fa-magnifying-glass',
            'search-delete' => 'Alias:ui-delete',
            'search-delete' => 'Alias:ui-delete',
            'search-filter-remove' => 'fa-solid fa-xmark',
            'search-rss' => 'fa-solid fa-square-rss',
            'search-save' => 'Alias:ui-save',
            'search-schedule-alert' => 'fa-solid fa-bell',
            'send-email' => 'fa-solid fa-share-from-square',
            'send-sms' => 'fa-solid fa-paper-plane',
            'sign-in' => 'fa-solid fa-right-to-bracket',
            'sign-out' => 'fa-solid fa-person-walking-arrow-right',
            'spinner' => 'fa-solid fa-spinner:icon--spin',
            'status-available' => 'fa-solid fa-check',
            'status-pending' => 'fa-regular fa-clock',
            'status-ready' => 'fa-solid fa-bell',
            'status-unavailable' => 'fa-solid fa-xmark',
            'status-unknown' => 'fa-solid fa-circle-question',
            'tag-add' => 'Alias:ui-add',
            'tag-remove' => 'Alias:ui-remove',
            'tree-context' => 'fa-solid fa-folder-tree',
            'truncate-less' => 'fa-solid fa-arrow-up',
            'truncate-more' => 'fa-solid fa-arrow-down',
            'ui-add' => 'fa-solid fa-circle-plus',
            'ui-cancel' => 'fa-solid fa-ban',
            'ui-close' => 'fa-solid fa-xmark',
            'ui-delete' => 'fa-solid fa-trash',
            'ui-dots-menu' => 'fa-solid fa-ellipsis',
            'ui-edit' => 'fa-solid fa-pencil',
            'ui-failure' => 'fa-solid fa-xmark',
            'ui-menu' => 'fa-solid fa-bars',
            'ui-remove' => 'fa-solid fa-xmark',
            'ui-reset-search' => 'Alias:ui-remove',
            'ui-save' => 'fa-solid fa-floppy-disk',
            'ui-success' => 'fa-solid fa-check',
            'user-checked-out' => 'fa-solid fa-book',
            'user-favorites' => 'fa-solid fa-heart',
            'user-holds' => 'fa-solid fa-book-bookmark',
            'user-ill-requests' => 'fa-solid fa-arrow-right-arrow-left',
            'user-list' => 'fa-solid fa-list-check',
            'user-list-add' => 'fa-solid fa-plus',
            'user-list-delete' => 'Alias:ui-delete',
            'user-list-edit' => 'Alias:ui-edit',
            'user-list-entry-edit' => 'Alias:ui-edit',
            'user-list-remove' => 'Alias:ui-remove',
            'user-loan-history' => 'fa-solid fa-clock-rotate-left',
            'user-public-list-indicator' => 'fa-solid fa-eye',
            'user-storage-retrievals' => 'fa-solid fa-dolly',
            'view-grid' => 'fa-solid fa-border-all',
            'view-list' => 'fa-solid fa-list-ol',
            'view-visual' => 'fa-solid fa-border-none',
            'warning' => 'fa-solid fa-circle-exclamation',
        ],
    ],
];
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
