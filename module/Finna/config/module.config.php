<?php

/**
 * Finna Module Configuration
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Finna
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://github.com/KDK-Alli/NDL-VuFind2   NDL-VuFind2
 */

namespace Finna\Module\Configuration;

$config = array(
    'router' => array(
        'routes' => array(
            'content-page' => array(
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/Content/[:page]',
                    'constraints' => array(
                        'page'     => '[a-zA-Z][a-zA-Z0-9_-]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Contentpage',
                        'action'     => 'Content',
                    )
                ),
            ),
            'list-page' => array(
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/List[/:lid]',
                    'constraints' => array(
                        'lid'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Listpage',
                        'action'     => 'List',
                    )
                ),
            ),
            'myresearch-unsubscribe' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/MyResearch/Unsubscribe',
                    'defaults' => array(
                        'controller' => 'MyResearch',
                        'action'     => 'Unsubscribe',
                    )
                ),
            ),
            'record-feedback' => array(
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/Record/[:id]/Feedback',
                    'constraints' => array(
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ),
                    'defaults' => array(
                        'controller' => 'Record',
                        'action'     => 'Feedback',
                    )
                )
            )
        )
    ),
    'controllers' => array(
        'factories' => [
            'record' => 'Finna\Controller\Factory::getRecordController',
        ],
        'invokables' => array(
            'ajax' => 'Finna\Controller\AjaxController',
            'contentpage' => 'Finna\Controller\ContentController',
            'cover' => 'Finna\Controller\CoverController',
            'feedback' => 'Finna\Controller\FeedbackController',
            'my-research' => 'Finna\Controller\MyResearchController',
            'primo' => 'Finna\Controller\PrimoController',
            'primorecord' => 'Finna\Controller\PrimorecordController',
            'search' => 'Finna\Controller\SearchController',
            'listpage' => 'Finna\Controller\ListController',
        ),
    ),
    'service_manager' => array(
        'allow_override' => true,
        'factories' => array(
            'VuFind\CacheManager' => 'Finna\Service\Factory::getCacheManager',
            'VuFind\ILSConnection' => 'Finna\Service\Factory::getILSConnection',
            'VuFind\DbTablePluginManager' => 'Finna\Service\Factory::getDbTablePluginManager',
            'VuFind\AuthManager' => 'Finna\Auth\Factory::getManager',
        )
    ),
    // This section contains all VuFind-specific settings (i.e. configurations
    // unrelated to specific Zend Framework 2 components).
    'vufind' => array(
        'plugin_managers' => array(
            'auth' => array(
                'factories' => [
                    'ils' => 'Finna\Auth\Factory::getILS',
                    'multiils' => 'Finna\Auth\Factory::getMultiILS',
                ],
                'invokables' => array(
                    'mozillapersona' => 'Finna\Auth\MozillaPersona',
                    'shibboleth' => 'Finna\Auth\Shibboleth',
                ),
            ),
            'db_table' => [
                'factories' => [
                    'resource' => 'Finna\Db\Table\Factory::getResource',
                    'user' => 'Finna\Db\Table\Factory::getUser',
                ],
                'invokables' => array(
                    'comments' => 'Finna\Db\Table\Comments',
                    'search' => 'Finna\Db\Table\Search'
                ),
            ],
            'ils_driver' => [
                'factories' => [
                    'voyagerrestful' => 'Finna\ILS\Driver\Factory::getVoyagerRestful',
                ],
            ],
            'resolver_driver' => [
                'factories' => [
                    'sfx' => 'Finna\Resolver\Driver\Factory::getSfx',
                ],
            ],
            'search_backend' => array(
                'factories' => array(
                    'Primo' => 'Finna\Search\Factory\PrimoBackendFactory',
                    'Solr' => 'Finna\Search\Factory\SolrDefaultBackendFactory',
                ),
                'aliases' => array(
                    // Allow Solr core names to be used as aliases for services:
                    'biblio' => 'Solr',
                )
            ),
            'search_options' => [
                'abstract_factories' => ['Finna\Search\Options\PluginFactory'],
            ],
            'search_params' => [
                'abstract_factories' => ['Finna\Search\Params\PluginFactory'],
            ],
            'search_results' => array(
                'factories' => array(
                    'solr' => 'Finna\Search\Results\Factory::getSolr',
                    'primo' => 'Finna\Search\Results\Factory::getPrimo'
                )
            ),
            'content_covers' => array(
                'invokables' => array(
                    'natlibfi' => 'Finna\Content\Covers\NatLibFi'
                ),
            ),
            'recorddriver' => array(
                'factories' => array(
                    'solrdefault' => 'Finna\RecordDriver\Factory::getSolrDefault',
                    'solrmarc' => 'Finna\RecordDriver\Factory::getSolrMarc',
                    'solread' => 'Finna\RecordDriver\Factory::getSolrEad',
                    'solrlido' => 'Finna\RecordDriver\Factory::getSolrLido',
                    'solrqdc' => 'Finna\RecordDriver\Factory::getSolrQdc',
                    'primo' => 'Finna\RecordDriver\Factory::getPrimo'
                ),
            ),
            'recordtab' => array(
                'invokables' => array(
                    'componentparts' => 'Finna\RecordTab\ComponentParts',
                ),
            ),
        ),
        'recorddriver_tabs' => array(
            'Finna\RecordDriver\SolrMarc' => array(
                'tabs' => array(
                    'Holdings' => 'HoldingsILS',
                    'ComponentParts' => 'ComponentParts',
                    'TOC' => 'TOC', 'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews', 'Excerpt' => 'Excerpt',
                    'Preview' => 'preview',
                    'HierarchyTree' => 'HierarchyTree', 'Map' => 'Map',
                    'Details' => 'StaffViewMARC',
                ),
                'defaultTab' => null,
            ),
            'Finna\RecordDriver\SolrEad' => array(
                'tabs' => array(
                    'HierarchyTree' => 'HierarchyTree',
                    'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews',
                    'Map' => 'Map',
                    'Details' => 'StaffViewArray',
                ),
                'defaultTab' => null,
            ),
            'Finna\RecordDriver\SolrLido' => array(
                'tabs' => array(
                    'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews',
                    'Map' => 'Map',
                    'Details' => 'StaffViewArray',
                ),
                'defaultTab' => null,
            ),
            'Finna\RecordDriver\SolrQdc' => array(
                'tabs' => array(
                    'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews',
                    'Map' => 'Map',
                    'Details' => 'StaffViewArray',
                ),
                'defaultTab' => null,
            ),
            'Finna\RecordDriver\Primo' => array(
                'tabs' => array(
                    'UserComments' => 'UserComments',
                    'Details' => 'StaffViewArray'
                ),
                'defaultTab' => null,
            ),
        ),
    )
);

return $config;
