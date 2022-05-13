<?php
return [
    'extends' => 'bootstrap3',
    'helpers' => [
        'factories' => [
            'TueFind\View\Helper\Root\Matomo' => 'TueFind\View\Helper\Root\MatomoFactory',
            'TueFind\View\Helper\Root\Url' => 'VuFind\View\Helper\Root\UrlFactory',
            'TueFind\View\Helper\Root\RecordDataFormatter' => 'TueFind\View\Helper\Root\RecordDataFormatterFactory',
            'TueFind\View\Helper\Root\SearchTabs' => 'VuFind\View\Helper\Root\SearchTabsFactory',
            'TueFind\View\Helper\TueFind\Authority' => 'TueFind\View\Helper\TueFind\AuthorityFactory',
            'TueFind\View\Helper\TueFind\TueFind' => 'TueFind\View\Helper\TueFind\Factory',
        ],
        'aliases' => [
            'authority' => 'TueFind\View\Helper\TueFind\Authority',
            'url' => 'TueFind\View\Helper\Root\Url',
            'Url' => 'TueFind\View\Helper\Root\Url',
            'matomo' => 'TueFind\View\Helper\Root\Matomo',
            'recordDataFormatter' => 'TueFind\View\Helper\Root\RecordDataFormatter',
            'searchTabs' => 'TueFind\View\Helper\Root\SearchTabs',
            'tuefind' => 'TueFind\View\Helper\TueFind\TueFind',
        ],
    ],
    'css' => [
        'vendor/jquery-ui.min.css',
        'vendor/keyboard-basic.css',
        'vendor/keyboard.css',
        'vendor/keyboard-dark.css',
        'vendor/keyboard-previewkeyset.css',
        'botprotect.css',
        'keyboard-tuefind.css'
    ],
    'js' => [
        'tuefind.js',
        'vendor/jquery-ui.min.js',
        'vendor/jquery.keyboard.js',
        'vendor/keyboard-layouts-greywyvern.js',
        'virtualkeyboard.js'
    ],
];
