<?php
return [
    'extends' => 'bootstrap3',
    'helpers' => [
        'factories' => [
            'helptext' => 'TueFind\View\Helper\Root\Factory::getHelpText',
            'tuefind' => 'TueFind\View\Helper\TueFind\Factory::getTueFind',
        ],
    ],
    'css' => [
        'vendor/jquery-ui.min.css',
        'vendor/keyboard-basic.css',
        'vendor/keyboard.css',
        'vendor/keyboard-dark.css',
        'vendor/keyboard-previewkeyset.css',
        'keyboard-tuefind.css'
    ],
    'js' => [
        'tuefind.js',
        'vendor/jquery-ui.min.js',
        'vendor/jquery.keyboard.js',
        'vendor/keyboard-layouts-greywyvern.js'
    ],
];
