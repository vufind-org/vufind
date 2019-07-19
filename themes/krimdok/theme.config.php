<?php
return [
    'extends' => 'tuefind',
    'helpers' => [
        'factories' => [
            'TueFind\View\Helper\Root\RecordDataFormatter' => 'KrimDok\View\Helper\Root\RecordDataFormatterFactory',
        ],
        'aliases' => [

        ],
    ],
];
