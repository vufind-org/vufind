<?php
return array(
    'extends' => 'bootstrap3',
    'helpers' => array(
        'factories' => array(
            'content' => 'Finna\View\Helper\Root\Factory::getContent',
            'header' => 'Finna\View\Helper\Root\Factory::getHeader',
            'openUrl' => 'Finna\View\Helper\Root\Factory::getOpenUrl',
            'record' => 'Finna\View\Helper\Root\Factory::getRecord',
            'recordImage' => 'Finna\View\Helper\Root\Factory::getRecordImage',
            'searchTabs' => 'Finna\View\Helper\Root\Factory::getSearchTabs',
            'navibar' => 'Finna\View\Helper\Root\Factory::getNavibar',
            'indexedTotal' => 'Finna\View\Helper\Root\Factory::getTotalIndexed',
            'personaAuth' => 'Finna\View\Helper\Root\Factory::getPersonaAuth',
        ),
        'invokables' => array(
            'search' => 'Finna\View\Helper\Root\Search',
            'truncateUrl' => 'Finna\View\Helper\Root\TruncateUrl',
            'checkboxFacetAvailables' =>
                'Finna\View\Helper\Root\CheckboxFacetAvailables',
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
        'persona.js',
        'vendor/jquery.dataTables.js',
        'vendor/dataTables.bootstrap.js',
        'vendor/jquery.inview.min.js',
        'vendor/jquery.magnific-popup.min.js',
        'vendor/jquery.cookie-1.4.1.min.js',
        'vendor/slick.min.js'
    ),
    'less' => array(
        'active' => false
    ),
);
