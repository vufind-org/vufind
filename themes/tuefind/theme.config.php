<?php
return [
    'extends' => 'bootstrap3',
    'helpers' => [
        'factories' => [
            'VuFind\View\Helper\Root\Piwik' => 'TueFind\View\Helper\Root\PiwikFactory',
            'TueFind\View\Helper\Root\HelpText' => 'TueFind\View\Helper\Root\HelpTextFactory',
            'TueFind\View\Helper\TueFind\TueFind' => 'TueFind\View\Helper\TueFind\Factory',
        ],
        'aliases' => [
            'helptext' => 'TueFind\View\Helper\Root\HelpText',
            'helpText' => 'TueFind\View\Helper\Root\HelpText',
            'HelpText' => 'TueFind\View\Helper\Root\HelpText',
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
