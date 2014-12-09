<?php
return array(
    'extends' => 'bootstrap3',
    'css' => array(
        'vendor/dataTables.bootstrap.css',
        'dataTables.bootstrap.custom.css',
    ),
    'js' => array(
        'vendor/jquery.dataTables.js',
        'vendor/dataTables.bootstrap.js'
    ),
    'helpers' => array(
        'invokables' => array(
            'safemoneyformat' => 'Finna\View\Helper\Finna\SafeMoneyFormat',
        )
    ),
);
