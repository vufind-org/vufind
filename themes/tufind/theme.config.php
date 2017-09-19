<?php
return array(
    'extends' => 'bootstrap3',
    'helpers' => [
        'factories' => [
            'helptext' => 'TuFind\View\Helper\Root\Factory::getHelpText',
        ],
    ],
    'js' => [
        'tufind.js',
    ],
);
