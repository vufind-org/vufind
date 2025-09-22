<?php
return [
    'extends' => 'bootstrap3',
    'favicon' => 'favicon.ico',
    'css' => [
        'StackMap.min.css'
    ],
    'helpers' => [
        'factories' => [
            'TAMU\View\Helper\Root\Record' => 'VuFind\View\Helper\Root\RecordFactory',
            'VuFind\View\Helper\Root\RecordDataFormatter' => 'TAMU\View\Helper\Root\RecordDataFormatterFactory',
        ],
        'aliases' => [
            'record' => 'TAMU\View\Helper\Root\Record'
        ]
    ]
];
