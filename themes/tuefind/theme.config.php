<?php
return [
    'extends' => 'bootstrap3',
    'helpers' => [
        'factories' => [
            'helptext' => 'TueFind\View\Helper\Root\HelpTextFactory',
            'HelpText' => 'TueFind\View\Helper\Root\HelpTextFactory',
            'piwik' => 'TueFind\View\Helper\Root\PiwikFactory',
            'tuefind' => 'TueFind\View\Helper\TueFind\Factory',
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
        'vendor/keyboard-layouts-greywyvern.js',
        'virtualkeyboard.js'
    ],
];
