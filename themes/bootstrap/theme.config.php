<?php
return array(
    'extends' => 'root',
    'css' => array(
        'bootstrap.min.css',
        'bootstrap-responsive.min.css',
        'font-awesome.css',
        'font-awesome-ie7.min.css',
        'slider.css',
        'screen.css',
        'print.css:print'
    ),
    'js' => array(
        'core/jquery.min.js',
        'core/bootstrap.js',
        'common.js',
        'lightbox.js',
        'rc4.js'
    ),
    'favicon' => 'vufind-favicon.ico',
    'helpers' => array(
        'factories' => array(
            'flashmessages' => function ($sm) {
                $messenger = $sm->getServiceLocator()->get('ControllerPluginManager')
                    ->get('FlashMessenger');
                return new \VuFind\View\Helper\Bootstrap\Flashmessages($messenger);
            },
            'layoutclass' => function ($sm) {
                $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
                $left = !isset($config->Site->sidebarOnLeft)
                    ? false : $config->Site->sidebarOnLeft;
                return new \VuFind\View\Helper\Bootstrap\LayoutClass($left);
            },
        ),
        'invokables' => array(
            'search' => 'VuFind\View\Helper\Bootstrap\Search',
            'vudl' => 'VuDL\View\Helper\Bootstrap\VuDL',
        )
    )
);
