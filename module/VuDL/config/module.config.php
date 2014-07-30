<?php
namespace VuDL\Module\Configuration;

$config = array(
    'controllers' => array(
        'invokables' => array(
            'vudl' => 'VuDL\Controller\VudlController'
        ),
    ),
    'service_manager' => array(
        'factories' => array(
            'VuDL\Connection\Manager' => 'VuDL\Factory::getConnectionManager',
            'VuDL\Connection\Fedora' => 'VuDL\Factory::getConnectionFedora',
            'VuDL\Connection\Solr' => 'VuDL\Factory::getConnectionSolr',
        ),
    ),
    'vufind' => array(
        'plugin_managers' => array(
            'recorddriver' => array(
                'factories' => array(
                    'solrvudl' => 'VuDL\Factory::getRecordDriver',
                ),
            ),
        ),
    ),
    'router' => array(
        'routes' => array(
            'files' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/files/:id/:type'
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
            'vudl-default-collection' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/Collection[/]',
                    'defaults' => array(
                        'controller' => 'VuDL',
                        'action'     => 'Collections'
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
            'vudl-home' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/VuDL/Home[/]',
                    'defaults' => array(
                        'controller' => 'VuDL',
                        'action'     => 'Home',
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
            'vudl-sibling' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/Vudl/Sibling/',
                    'defaults' => array(
                        'controller' => 'VuDL',
                        'action'     => 'Sibling'
                    )
                )
            ),
        )
    ),
);

return $config;
