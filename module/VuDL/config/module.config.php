<?php
namespace VuDL\Module\Configuration;

$config = array(
    'controllers' => array(
        'invokables' => array(
            'redirect' => 'VuDL\Controller\RedirectController',
            'vudl' => 'VuDL\Controller\VudlController'
        ),
    ),
    'vufind' => array(
        'plugin_managers' => array(
            'recorddriver' => array(
                'factories' => array(
                    'solrvudl' => function ($sm) {
                        $driver = new \VuDL\RecordDriver\SolrVudl(
                            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
                            null,
                            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
                        );
                        $driver->setVuDLConfig(
                            $sm->getServiceLocator()->get('VuFind\Config')->get('VuDL')
                        );
                        return $driver;
                    }
                )
            )
        )
    ),
    'router' => array(
        'routes' => array(
            'files' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/files/:id/:type'
                )
            ),
            'vudl-collection' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/Collection/:id',
                    'defaults' => array(
                        'controller' => 'Collection',
                        'action'     => 'Home'
                    )
                )
            ),
            'vudl-default-collection' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/Collection[/]',
                    'defaults' => array(
                        'controller' => 'Collection',
                        'action'     => 'Home',
                        'id'         => 'root:id'
                    )
                )
            ),
            'vudl-grid' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/Grid/:id',
                    'defaults' => array(
                        'controller' => 'VuDL',
                        'action'     => 'Grid'
                    )
                )
            ),
            'vudl-record' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/Item/:id',
                    'defaults' => array(
                        'controller' => 'VuDL',
                        'action'     => 'Record'
                    )
                )
            ),
            'vudl-redirect' => array(
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/:collection[/:file]',
                    'constraints' => array(
                        'collection' => '',
                        'file'       => '.*',
                    ),
                    'defaults' => array(
                        'controller' => 'Redirect',
                        'action'     => 'redirect',
                    )
                )
            ),
            'vudl-about-php' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/about.php',
                    'defaults' => array(
                        'controller' => 'Redirect',
                        'action'     => 'About',
                    )
                )
            ),
            'vudl-collection-link' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/collections.php',
                    'defaults' => array(
                        'controller' => 'Redirect',
                        'action'     => 'Collection'
                    )
                )
            ),
            'vudl-home' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/VuDL/Home',
                    'defaults' => array(
                        'controller' => 'VuDL',
                        'action'     => 'Home',
                    )
                )
            ),
            'vudl-about' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/VuDL/About',
                    'defaults' => array(
                        'controller' => 'VuDL',
                        'action'     => 'About',
                    )
                )
            ),
        )
    ),
);

return $config;
