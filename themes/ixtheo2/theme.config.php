<?php
return [
    'extends' => 'tuefind2',
    'favicon' => 'ixtheo-favicon.ico',
    'css' => [
        'vendor/cw/leaflet.css',
        'vendor/cw/swiper.css',
    ],
    'js' => [
        'ixtheo.js',
        'ixtheo2.js',
        'vendor/cw/leaflet.js',
        'vendor/cw/swiper.js',
    ],
    'helpers' => [
        'factories' => [
            'TueFind\View\Helper\Root\RecordDataFormatter' => 'IxTheo\View\Helper\Root\RecordDataFormatterFactory',
            'IxTheo\View\Helper\Root\Browse' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'IxTheo\View\Helper\Root\Citation' => 'VuFind\View\Helper\Root\CitationFactory',
            'IxTheo\View\Helper\Root\Record' => 'VuFind\View\Helper\Root\RecordFactory',
            'IxTheo\View\Helper\TueFind\Authority' => 'TueFind\View\Helper\TueFind\AuthorityFactory',
            'IxTheo\View\Helper\IxTheo\IxTheo' => 'IxTheo\View\Helper\IxTheo\Factory',
        ],
        'aliases' => [
            'authority' => 'IxTheo\View\Helper\TueFind\Authority',
            'browse' => 'IxTheo\View\Helper\Root\Browse',
            'citation' => 'IxTheo\View\Helper\Root\Citation',
            'record' => 'IxTheo\View\Helper\Root\Record',
            'ixtheo' => 'IxTheo\View\Helper\IxTheo\IxTheo',
            'IxTheo' => 'IxTheo\View\Helper\IxTheo\IxTheo',
        ],
    ],
];
