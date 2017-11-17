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
            'citation' => 'IxTheo\View\Helper\Root\Factory::getCitation',
            'piwik' => 'IxTheo\View\Helper\Root\Factory::getPiwik',
            'record' => 'IxTheo\View\Helper\Root\Factory::getRecord',
        ],
        'invokables' => [
            'browse' => 'IxTheo\View\Helper\Root\Browse',
            'ixTheoClassification' => 'IxTheo\View\Helper\IxTheo\Classification',
        ],
    ],
];
