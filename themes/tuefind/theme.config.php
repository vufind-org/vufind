<?php
return array(
    'extends' => 'bootstrap3',
    'helpers' => [
        'factories' => [
            'helptext' => 'TueFind\View\Helper\Root\Factory::getHelpText',
        ],
    ],
    'js' => [
        'tuefind.js',
    ],
);
