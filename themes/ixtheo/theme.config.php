<?php
return [
    'extends' => 'tuefind',
    'css' => [
        'compiled.css',
        'feedback.css',
        'vendor/jquery.feedback_me.css',
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
    'js' => [
        'ixtheo.js',
    ],
];
