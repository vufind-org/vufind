<?php
return array(
    'extends' => 'bootstrap',
    'less' => array(
        'style.less'
    ),
    'css' => array(
        'icons.css',
        'style.css',
    ),
    'helpers' => array(
        'factories' => array(
            'layoutclass' => function ($sm) {
                $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
                $left = !isset($config->Site->sidebarOnLeft) ? false : $config->Site->sidebarOnLeft;
                return new \VuFind\View\Helper\Bootprint\LayoutClass($left);
            }
        )
    )
);
