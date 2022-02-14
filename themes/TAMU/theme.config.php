<?php
return [
    'extends' => 'bootstrap3',
    'favicon' => 'favicon.ico',
    'helpers' => [
        'factories' => [
            'TAMU\View\Helper\Root\Record' => 'VuFind\View\Helper\Root\RecordFactory'
        ],
        'aliases' => [
            'record' => 'TAMU\View\Helper\Root\Record'
        ]
    ]
];
