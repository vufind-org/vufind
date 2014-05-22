<?php
return array(
    'extends' => 'bootstrap',
    'css' => array(
        'icons.css',
        'bootprint-custom.css',
    ),
    'helpers' => array(
        'factories' => array(
            'layoutclass' => 'VuFind\View\Helper\Bootprint\Factory::getLayoutClass',
        )
    )
);
