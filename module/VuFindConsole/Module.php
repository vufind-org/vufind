<?php
/**
 * ZF2 module definition for the VuFind console module
 *
 * PHP version 5
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
 * @package  Module
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
namespace VuFindConsole;
use Zend\Console\Adapter\AdapterInterface as Console;

/**
 * ZF2 module definition for the VuFind console module
 *
 * @category VuFind
 * @package  Module
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
class Module implements \Zend\ModuleManager\Feature\ConsoleUsageProviderInterface
{
    /**
     * Get module configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * Get autoloader configuration
     *
     * @return array
     */
    public function getAutoloaderConfig()
    {
        return [
            'Zend\Loader\StandardAutoloader' => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ],
            ],
        ];
    }

    /**
     * Return usage information
     *
     * @param Console $console Console adapter
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConsoleUsage(Console $console)
    {
        return [
            'generate extendservice' => 'Override a service with a new child class',
            'generate nontabrecordaction' => 'Add routes for non-tab record action',
            'generate dynamicroute' => 'Add a dynamic route',
            'generate recordroute' => 'Add a record route',
            'generate staticroute' => 'Add a static route',
            'harvest harvest_oai' => 'OAI-PMH harvester',
            'harvest merge-marc' => 'MARC merge tool',
            'import import-xsl' => 'XSLT importer',
            'import webcrawl' => 'Web crawler',
            'language addusingtemplate' => 'Build new language strings from '
                . 'existing ones using a template',
            'language copystring' => 'Copy one language string to another',
            'language delete' => 'Remove a language string from all files',
            'language normalize' => 'Normalize a directory of language files',
            'util cleanup_record_cache' => 'Remove unused records from the cache',
            'util commit' => 'Solr commit tool',
            'util createHierarchyTrees' => 'Cache populator for hierarchies',
            'util cssBuilder' => 'LESS compiler',
            'util deletes' => 'Tool for deleting Solr records',
            'util expire_searches' => 'Database search table cleanup',
            'util expire_sessions' => 'Database session table cleanup',
            'util index_reserves' => 'Solr reserves indexer',
            'util optimize' => 'Solr optimize tool',
            'util sitemap' => 'XML sitemap generator',
            'util switch_db_hash' => 'Switch the hashing algorithm in the database '
                . 'and config. Expects new algorithm and (optional) new key as'
                . ' parameters.',
            'util suppressed' => 'Remove ILS-suppressed records from Solr',
        ];
    }
}
