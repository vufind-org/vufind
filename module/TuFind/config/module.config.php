<?php
namespace TuFind\Module\Config;

$config = array(
    'controllers' => array(
        'invokables' => array(
            'proxy' => 'TuFind\Controller\ProxyController',
            'pdaproxy' => 'TuFind\Controller\PDAProxyController'
        ),
    ),
    'router' => array(
        'routes' => array(
            'proxy-load' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/Proxy/Load',
                    'defaults' => array(
                        'controller' => 'Proxy',
                        'action'     => 'Load',
                    )
                )
            ),
            'pdaproxy-load' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/PDAProxy/Load',
                    'defaults' => array(
                        'controller' => 'PDAProxy',
                        'action'     => 'Load',
                    )
                )
            )
        )
    )
);

return $config;
