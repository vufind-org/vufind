<?php
return array(
    'extends' => 'bootstrap',
    'css' => array(
        'icons.css',
        'style.css',
    ),
    'helpers' => array(
        'factories' => array(
            'layoutclass' => array('VuFind\View\Helper\Bootprint\Factory', 'getLayoutClass'),
        )
    )
);
