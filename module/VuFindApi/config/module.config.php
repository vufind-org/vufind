<?php

namespace VuFindApi\Module\Configuration;

$config = [
    'service_manager' => [
        'factories' => [
            'VuFindApi\Formatter\FacetFormatter' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'VuFindApi\Formatter\RecordFormatter' => 'VuFindApi\Formatter\RecordFormatterFactory',
        ],
    ],
    'vufind' => [
        'action_config' => [
            // Note: AdminApi not listed as it has hard-coded permission to ensure it doesn't get included in API spec
            // unless permitted.

            // Record API (Authority backend)
            'vufindapi_authority_record' => [
                'actionIds' => [
                    'authorityapi/record',
                ],
                'accessPermission' => 'access.api.Record',
                'backendId' => 'SolrAuth',
                'customConfig' => [
                    'recordFieldConfigFile' => 'AuthorityApiRecordFields',
                ],
            ],

            // Search API (Authority backend)
            'vufindapi_authority_search' => [
                'actionIds' => [
                    'authorityapi/search',
                ],
                'accessPermission' => 'access.api.Search',
                'backendId' => 'SolrAuth',
                'customConfig' => [
                    'recordFieldConfigFile' => 'AuthorityApiRecordFields',
                    // For API spec:
                    'recordRoute' => 'authority/record',
                    'searchRoute' => 'authority/search',
                    'indexLabel' => 'authority',
                    'modelPrefix' => 'Authority',
                ],
            ],

            // Record API (default backend)
            'vufindapi_default_record' => [
                'actionIds' => [
                    'searchapi/record',
                ],
                'accessPermission' => 'access.api.Record',
                'backendId' => 'Solr',
            ],

            // Search API (default backend)
            'vufindapi_default_search' => [
                'actionIds' => [
                    'searchapi/search',
                ],
                'accessPermission' => 'access.api.Search',
                'backendId' => 'Solr',
            ],

            // Record API (Search2 backend)
            'vufindapi_search2_record' => [
                'actionIds' => [
                    'search2api/record',
                ],
                'accessPermission' => 'access.api.Record',
                'backendId' => 'Search2',
            ],

            // Search API (Search2 backend)
            'vufindapi_search2_search' => [
                'actionIds' => [
                    'search2api/search',
                ],
                'accessPermission' => 'access.api.Search',
                'backendId' => 'Solr',
                'customConfig' => [
                    // For API spec:
                    'recordRoute' => 'index2/record',
                    'searchRoute' => 'index2/search',
                    'indexLabel' => 'secondary',
                    'modelPrefix' => 'Secondary',
                ],
            ],

            // Record API (SolrWeb backend)
            'vufindapi_web_record' => [
                'actionIds' => [
                    'webapi/record',
                ],
                'accessPermission' => 'access.api.Record',
                'backendId' => 'SolrWeb',
                'customConfig' => [
                    'recordFieldConfigFile' => 'WebApiRecordFields',
                ],
            ],

            // Search API (SolrWeb backend)
            'vufindapi_web_search' => [
                'actionIds' => [
                    'webapi/search',
                ],
                'accessPermission' => 'access.api.Search',
                'backendId' => 'SolrWeb',
                'customConfig' => [
                    'recordFieldConfigFile' => 'WebApiRecordFields',
                    // For API spec:
                    'recordRoute' => 'web/record',
                    'searchRoute' => 'web/search',
                    'indexLabel' => 'website',
                    'modelPrefix' => 'Web',
                ],
            ],
        ],
        'plugin_managers' => [
            'action' => [
                'autodiscovery_namespaces' => [
                    'VuFindApi\Action' => true,
                ],
                // Use alias for each action so that they can also be queried for API specs (see below):
                'aliases' => [
                    'adminapi/clearcache' => \VuFindApi\Action\AdminApi\ClearCacheAction::class,
                    'authorityapi/record' => \VuFindApi\Action\SearchApi\RecordAction::class,
                    'authorityapi/search' => \VuFindApi\Action\SearchApi\SearchAction::class,
                    'searchapi/record' => \VuFindApi\Action\SearchApi\RecordAction::class,
                    'searchapi/search' => \VuFindApi\Action\SearchApi\SearchAction::class,
                    'search2api/record' => \VuFindApi\Action\SearchApi\RecordAction::class,
                    'search2api/search' => \VuFindApi\Action\SearchApi\SearchAction::class,
                    'webapi/record' => \VuFindApi\Action\SearchApi\RecordAction::class,
                    'webapi/search' => \VuFindApi\Action\SearchApi\SearchAction::class,
                ],
            ],
        ],
    ],
    'vufind_api' => [
        'actions_for_specs' => [
            'adminapi/clearcache',
            'authorityapi/record',
            'authorityapi/search',
            'searchapi/record',
            'searchapi/search',
            'search2api/record',
            'search2api/search',
            'webapi/record',
            'webapi/search',
        ],
    ],
    'router' => [
        'routes' => [
            'adminClearCacheApiv1' => [
                'type' => 'Laminas\Router\Http\Literal',
                'verb' => 'delete,options',
                'options' => [
                    'route'    => '/api/v1/admin/cache',
                    'defaults' => [
                        'controller' => 'AdminApi',
                        'action'     => 'clearCache',
                    ],
                ],
            ],
            'apiHome' => [
                'type' => 'Laminas\Router\Http\Segment',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api[/v1][/]',
                    'defaults' => [
                        'controller' => 'Api',
                        'action'     => 'Index',
                    ],
                ],
            ],
            'searchApiv1' => [
                'type' => 'Laminas\Router\Http\Literal',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api/v1/search',
                    'defaults' => [
                        'controller' => 'SearchApi',
                        'action'     => 'search',
                    ],
                ],
            ],
            'recordApiv1' => [
                'type' => 'Laminas\Router\Http\Literal',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api/v1/record',
                    'defaults' => [
                        'controller' => 'SearchApi',
                        'action'     => 'record',
                    ],
                ],
            ],
            'search2Apiv1' => [
                'type' => 'Laminas\Router\Http\Literal',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api/v1/index2/search',
                    'defaults' => [
                        'controller' => 'Search2Api',
                        'action'     => 'search',
                    ],
                ],
            ],
            'record2Apiv1' => [
                'type' => 'Laminas\Router\Http\Literal',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api/v1/index2/record',
                    'defaults' => [
                        'controller' => 'Search2Api',
                        'action'     => 'record',
                    ],
                ],
            ],
            'authoritysearchApiv1' => [
                'type' => 'Laminas\Router\Http\Literal',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api/v1/authority/search',
                    'defaults' => [
                        'controller' => 'AuthorityApi',
                        'action'     => 'search',
                    ],
                ],
            ],
            'authorityrecordApiv1' => [
                'type' => 'Laminas\Router\Http\Literal',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api/v1/authority/record',
                    'defaults' => [
                        'controller' => 'AuthorityApi',
                        'action'     => 'record',
                    ],
                ],
            ],
            'websearchApiv1' => [
                'type' => 'Laminas\Router\Http\Literal',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api/v1/web/search',
                    'defaults' => [
                        'controller' => 'WebApi',
                        'action'     => 'search',
                    ],
                ],
            ],
            'webrecordApiv1' => [
                'type' => 'Laminas\Router\Http\Literal',
                'verb' => 'get,post,options',
                'options' => [
                    'route'    => '/api/v1/web/record',
                    'defaults' => [
                        'controller' => 'WebApi',
                        'action'     => 'record',
                    ],
                ],
            ],
        ],
    ],
];

return $config;
