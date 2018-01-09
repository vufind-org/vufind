<?php
return [
    'extends' => 'bootstrap3',
    'helpers' => [
        'factories' => [
            'helptext' => 'TueFind\View\Helper\Root\Factory::getHelpText',
        ],
        'invokables' => [
            'tuefind' => 'TueFind\View\Helper\TueFind\TueFind',
        ],
    ],
    'js' => [
        'tuefind.js',
    ],
];
