<?php
return [
    'extends' => 'tuefind2',
    'favicon' => 'ixtheo-favicon.ico',
    'js' => [
        'scripts.js',
    ],
    'helpers' => [
        'factories' => [
            'IxTheo\View\Helper\Root\Browse' => 'Zend\ServiceManager\Factory\InvokableFactory',
            'IxTheo\View\Helper\Root\Citation' => 'IxTheo\View\Helper\Root\CitationFactory',
            'IxTheo\View\Helper\Root\Record' => 'IxTheo\View\Helper\Root\RecordFactory',
            'IxTheo\View\Helper\IxTheo\IxTheo' => 'Zend\ServiceManager\Factory\InvokableFactory',
        ],
        'aliases' => [
            'browse' => 'IxTheo\View\Helper\Root\Browse',
            'citation' => 'IxTheo\View\Helper\Root\Citation',
            'record' => 'IxTheo\View\Helper\Root\Record',
            'ixtheo' => 'IxTheo\View\Helper\IxTheo\IxTheo',
            'IxTheo' => 'IxTheo\View\Helper\IxTheo\IxTheo',
        ],
    ],
];
