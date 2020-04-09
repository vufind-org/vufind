<?php
/**
 * Code module for VuFind's console functionality
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
 * @package  Module
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
namespace VuFindConsole;

use Laminas\Console\Adapter\AdapterInterface as Console;

/**
 * Code module for VuFind's console functionality
 *
 * @category VuFind
 * @package  Module
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
class Module implements \Laminas\ModuleManager\Feature\ConsoleUsageProviderInterface,
    \Laminas\ModuleManager\Feature\ConsoleBannerProviderInterface
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
            'Laminas\Loader\StandardAutoloader' => [
                'namespaces' => [
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ],
            ],
        ];
    }

    /**
     * Returns a string containing a banner text, that describes the module and/or
     * the application.
     * The banner is shown in the console window, when the user supplies invalid
     * command-line parameters or invokes the application with no parameters.
     *
     * The method is called with active Laminas\Console\Adapter\AdapterInterface that
     * can be used to directly access Console and send output.
     *
     * @param Console $console Console adapter
     *
     * @return string|null
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConsoleBanner(Console $console)
    {
        return 'VuFind';
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
            'util commit' => 'Solr commit tool',
            'util createHierarchyTrees' => 'Cache populator for hierarchies',
            'util deletes' => 'Tool for deleting Solr records',
            'util expire_external_sessions'
                => 'Database external_session table cleanup',
            'util expire_searches' => 'Database search table cleanup',
            'util expire_sessions' => 'Database session table cleanup',
            'util index_reserves' => 'Solr reserves indexer',
            'util optimize' => 'Solr optimize tool',
            'util sitemap' => 'XML sitemap generator',
            'util suppressed' => 'Remove ILS-suppressed records from Solr',
            'util switch_db_hash' => 'Switch the hashing algorithm in the database '
                . 'and config. Expects new algorithm and (optional) new key as'
                . ' parameters.',
        ];
    }
}
