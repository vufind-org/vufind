<?php
/**
 * Module for storing local overrides for VuFindConsole.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/dmj/vf2-proxy
 */
namespace FinnaConsole;

use Laminas\Console\Adapter\AdapterInterface as Console;

/**
 * Module for storing local overrides for VuFindConsole.
 *
 * @category VuFind
 * @package  Module
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/dmj/vf2-proxy
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
     */
    public function getConsoleBanner(Console $console)
    {
        return 'Finna';
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
            'util account_expiration_reminders'
                => 'Remind users x days before account expiration',
            'util due_date_reminders' => 'Send due date reminders',
            'util encrypt_catalog_passwords' => 'Encrypt ILS passwords in database',
            'util expire_finna_cache'
                => 'Remove expires Finna cache entries from database',
            'util expire_users' => 'Delete expired user accounts',
            'util import_comments' => 'Import comments',
            'util online_payment_monitor' => 'Process unregistered online payments',
            'util scheduled_alerts' => 'Send scheduled alerts',
            'util update_search_hashes' => 'Update search hashes',
            'util verify_record_links' => 'Verify record links',
            'util verify_resource_metadata' => 'Verify resource metadata'
        ];
    }
}
