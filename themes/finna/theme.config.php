<?php
return array(
    'extends' => 'bootstrap3',
    'helpers' => array(
        'factories' => array(
            'content' => 'Finna\View\Helper\Root\Factory::getContent',
            'header' => 'Finna\View\Helper\Root\Factory::getHeader',
            'record' => 'Finna\View\Helper\Root\Factory::getRecord',
            'recordImage' => 'Finna\View\Helper\Root\Factory::getRecordImage',
            'searchTabs' => 'Finna\View\Helper\Root\Factory::getSearchTabs',
            'navibar' => 'Finna\View\Helper\Root\Factory::getNavibar'
        ),
        'invokables' => array(
            'search' => 'Finna\View\Helper\Root\Search',
            'truncateUrl' => 'Finna\View\Helper\Root\TruncateUrl'
        )
    ),
    'css' => array(
        'vendor/dataTables.bootstrap.css',
        'vendor/magnific-popup.css',
        'dataTables.bootstrap.custom.css',
        'vendor/slick.css',
        'finna.css'
    ),
    'js' => array(
        'finna.js',
        'image-popup.js',
        'finna-layout.js',
        'vendor/jquery.dataTables.js',
        'vendor/dataTables.bootstrap.js',
        'vendor/jquery.magnific-popup.min.js',
        'vendor/slick.min.js'
    ),
    'less' => array(
        'active' => false
    ),
);
