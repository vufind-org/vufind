<?php
return array(
    'extends' => 'bootstrap3',
    'helpers' => array(
        'factories' => array(
            'content' => 'Finna\View\Helper\Root\Factory::getContent',
            'feed' => 'Finna\View\Helper\Root\Factory::getFeed',
            'header' => 'Finna\View\Helper\Root\Factory::getHeader',
            'holdingsDetailsMode' => 'Finna\View\Helper\Root\Factory::getHoldingsDetailsMode',
            'imageSrc' => 'Finna\View\Helper\Root\Factory::getImageSrc',
            'indexedTotal' => 'Finna\View\Helper\Root\Factory::getTotalIndexed',
            'logoutMessage' => 'Finna\View\Helper\Root\Factory::getLogoutMessage',
            'navibar' => 'Finna\View\Helper\Root\Factory::getNavibar',
            'openUrl' => 'Finna\View\Helper\Root\Factory::getOpenUrl',
            'organisationsList'
                => 'Finna\View\Helper\Root\Factory::getOrganisationsList',
            'personaAuth' => 'Finna\View\Helper\Root\Factory::getPersonaAuth',
            'primo' => 'Finna\View\Helper\Root\Factory::getPrimo',
            'record' => 'Finna\View\Helper\Root\Factory::getRecord',
            'recordImage' => 'Finna\View\Helper\Root\Factory::getRecordImage',
            'searchTabs' => 'Finna\View\Helper\Root\Factory::getSearchTabs',
        ),
        'invokables' => array(
            'checkboxFacetCounts' =>
                'Finna\View\Helper\Root\CheckboxFacetCounts',
            'search' => 'Finna\View\Helper\Root\Search',
            'translationEmpty' => 'Finna\View\Helper\Root\TranslationEmpty',
            'truncateUrl' => 'Finna\View\Helper\Root\TruncateUrl',
            'userPublicName' => 'Finna\View\Helper\Root\UserPublicName',
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
        'finna-feed.js',
        'finna-layout.js',
        'finna-persona.js',
        'finna-common.js',
        'finna-user-profile.js',
        'vendor/jquery.dataTables.js',
        'vendor/dataTables.bootstrap.js',
        'vendor/jquery.inview.min.js',
        'vendor/jquery.magnific-popup.min.js',
        'vendor/jquery.cookie-1.4.1.min.js',
        'vendor/slick.min.js',
        'vendor/jquery.touchSwipe.min.js'
    ),
    'less' => array(
        'active' => false
    ),
);
