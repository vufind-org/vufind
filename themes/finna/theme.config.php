<?php
return array(
    'extends' => 'bootstrap3',
    'helpers' => array(
        'factories' => array(
            'header' => 'Finna\View\Helper\Root\Factory::getHeader'
        )
    ),
    'css' => array(
        'vendor/dataTables.bootstrap.css',
        'dataTables.bootstrap.custom.css',
    ),
    'js' => array(
        'finna.js',
        'vendor/jquery.dataTables.js',
        'vendor/dataTables.bootstrap.js'
    ),
    'less' => array(
        'active' => false
    ),
);
