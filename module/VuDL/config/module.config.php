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
                        return new \VuDL\RecordDriver\SolrVudl(
                            $sm->getServiceLocator()->get('VuFind\Config')->get('config'),
                            null,
                            $sm->getServiceLocator()->get('VuFind\Config')->get('searches')
                        );
                    }
                )
            )
        )
    ),
    'router' => array(
        'routes' => array(
            'vudl-redirect' => array(
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/:collection[/:file]',
                    'constraints' => array(
                        'collection' => 'Americana|Autographed%20Books%20Collection|Catholica%20Collection|Contributions%20from%20Augustinian%20Theologians%20and%20Scholars|Cuala%20Press%20Broadside%20Collection|Flora,%20Fauna,%20and%20the%20Human%20Form|Hubbard%20Collection|Image%20Collection|Independence%20Seaport%20Museum|Joseph%20McGarrity%20Collection|La%20Salle%20University|Manuscript%20Collection|Pennsylvaniana|Philadelphia%20Ceili%20Group|Rambles,%20Travels,%20and%20Maps|Saint%20Augustine%20Reference%20Library|Sherman%20Thackara%20Collection|Villanova%20Digital%20Collection|World',
                        'file'       => '.*',
                    ),
                    'defaults' => array(
                        'controller' => 'Redirect',
                        'action'     => 'redirect',
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
                        'id'         => 'vudl:3'
                    )
                )
            ),
            'vudl-default-item' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/Item[/]',
                    'defaults' => array(
                        'controller' => 'Collection',
                        'action'     => 'Home',
                        'id'         => 'vudl:3'
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
            'vudl-copyright' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/copyright.html',
                    'defaults' => array(
                        'controller' => 'VuDL',
                        'action'     => 'Copyright',
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
              
            'files' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/files/:id/:type'
                )
            )
        )
    ),
    'vudl' => array(
        'page_length' => 16,
        'licenses' => array(
            'creativecommons.org' => 'CC',
            'villanova.edu' => 'VU'
        ),
        'routes' => array(
            'tiff'  => 'page',
            'flac' => 'audio',
            'mp3' => 'audio',
            'mpeg' => 'audio',
            'octet-stream' => 'audio',
            'ogg' => 'audio',
            'x-flac' => 'audio',
            'mp4' => 'video',
            'ogv' => 'video',
            'webmv' => 'video',
            'pdf' => 'download',
            'msword' => 'download'
        ),        
        'url_base' => 'http://hades.library.villanova.edu:8088/fedora/objects/',
        'query_url' => 'http://hades.library.villanova.edu:8088/fedora/risearch',
        'root_id' => 'vudl:3'
    ),
    'access' => array(
        'ip_range' => array(
            '153.104',
            '127.0.0.1','::1'
        ),
        'proxy_url' => 'http://ezproxy.villanova.edu/login?url='
    )
);

return $config;

