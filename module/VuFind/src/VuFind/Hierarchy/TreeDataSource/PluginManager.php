<?php
/**
 * Hierarchy tree data source plugin manager
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  HierarchyTree_DataSource
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
namespace VuFind\Hierarchy\TreeDataSource;

/**
 * Hierarchy tree data source plugin manager
 *
 * @category VuFind
 * @package  HierarchyTree_DataSource
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'solr' => 'VuFind\Hierarchy\TreeDataSource\Solr',
        'xmlfile' => 'VuFind\Hierarchy\TreeDataSource\XMLFile',
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        'VuFind\Hierarchy\TreeDataSource\Solr' =>
            'VuFind\Hierarchy\TreeDataSource\SolrFactory',
        'VuFind\Hierarchy\TreeDataSource\XMLFile' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return 'VuFind\Hierarchy\TreeDataSource\AbstractBase';
    }
}
