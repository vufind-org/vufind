<?php
return [
    'extends' => 'tuefind2',
    'favicon' => 'ixtheo-favicon.ico',
    'js' => [
        'scripts.js',
    ],
    'helpers' => [
        'factories' => [
            'citation' => 'IxTheo\View\Helper\Root\Factory::getCitation',
            'record' => 'IxTheo\View\Helper\Root\Factory::getRecord',
            'ixtheo' => 'IxTheo\View\Helper\Root\Factory::getIxTheo',
        ],
        'invokables' => [
            'browse' => 'IxTheo\View\Helper\Root\Browse',
        ],
    ],
];
