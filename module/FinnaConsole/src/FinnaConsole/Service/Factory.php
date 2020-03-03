<?php
/**
 * Factory for various top-level VuFind services.
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
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace FinnaConsole\Service;

use Zend\Console\Console;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for various top-level VuFind services.
 *
 * @category VuFind
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Factory
{
    /**
     * Construct the console service for reminding on expiring user accounts
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \FinnaConsole\Service\AccountExpirationReminders
     */
    public static function getAccountExpirationReminders(ServiceManager $sm)
    {
        $table = $sm->get(\VuFind\Db\Table\PluginManager::class)->get('User');
        $renderer = $sm->get('ViewRenderer');
        $configReader = $sm->get(\VuFind\Config\PluginManager::class);
        $translator = $sm->get(\Zend\Mvc\I18n\Translator::class);

        return new AccountExpirationReminders(
            $table, $renderer, $configReader, $translator, $sm
        );
    }

    /**
     * Construct the console service for sending due date reminders.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \FinnaConsole\Service\DueDateReminder
     */
    public static function getDueDateReminders(ServiceManager $sm)
    {
        $tableManager = $sm->get(\VuFind\Db\Table\PluginManager::class);
        $userTable = $tableManager->get('user');
        $dueDateReminderTable = $tableManager->get('duedatereminder');

        $catalog = $sm->get(\VuFind\ILS\Connection::class);
        $configReader = $sm->get(\VuFind\Config\PluginManager::class);
        $renderer = $sm->get('ViewRenderer');
        $loader = $sm->get(\VuFind\Record\Loader::class);
        $hmac = $sm->get(\VuFind\Crypt\HMAC::class);
        $translator = $sm->get(\Zend\Mvc\I18n\Translator::class);

        return new DueDateReminders(
            $userTable, $dueDateReminderTable, $catalog,
            $configReader, $renderer, $loader, $hmac, $translator, $sm
        );
    }

    /**
     * Construct the console service for encrypting catalog passwords.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \FinnaConsole\Service\EncryptCatalogPasswords
     */
    public static function getEncryptCatalogPasswords(ServiceManager $sm)
    {
        $table = $sm->get(\VuFind\Db\Table\PluginManager::class)->get('User');
        $config = $sm->get(\VuFind\Config\PluginManager::class)->get('config');

        return new EncryptCatalogPasswords($table, $config);
    }

    /**
     * Construct the console service for anonymizing expired users accounts.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \FinnaConsole\Service\ExpireUsers
     */
    public static function getExpireUsers(ServiceManager $sm)
    {
        $table = $sm->get(\VuFind\Db\Table\PluginManager::class)->get('User');
        $config = $sm->get(\VuFind\Config\PluginManager::class)->get('config');
        $removeComments = $config->Authentication->delete_comments_with_user ?? true;
        return new ExpireUsers($table, $removeComments);
    }

    /**
     * Construct the console service for importing comments.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \FinnaConsole\Service\ImportComments
     */
    public static function getImportComments(ServiceManager $sm)
    {
        $tableManager = $sm->get(\VuFind\Db\Table\PluginManager::class);
        return new ImportComments(
            $tableManager->get('Comments'),
            $tableManager->get('CommentsRecord'),
            $tableManager->get('Resource')
        );
    }

    /**
     * Construct the console service for sending scheduled alerts.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \FinnaConsole\Service\OnlinePaymentMonitor
     */
    public static function getOnlinePaymentMonitor(ServiceManager $sm)
    {
        $catalog = $sm->get(\VuFind\ILS\Connection::class);
        $tableManager = $sm->get(\VuFind\Db\Table\PluginManager::class);
        $transactionTable = $tableManager->get('transaction');
        $userTable = $tableManager->get('user');
        $configReader = $sm->get(\VuFind\Config\PluginManager::class);
        $viewManager = $sm->get('ViewManager');
        $viewRenderer = $sm->get('ViewRenderer');

        return new OnlinePaymentMonitor(
            $catalog, $transactionTable, $userTable, $configReader, $sm,
            $viewManager, $viewRenderer
        );
    }

    /**
     * Construct the console service for updating search hashes.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \FinnaConsole\Service\UpdateSearchHashes
     */
    public static function getUpdateSearchHashes(ServiceManager $sm)
    {
        $table = $sm->get(\VuFind\Db\Table\PluginManager::class)->get('Search');
        $manager = $sm->get(\VuFind\Search\Results\PluginManager::class);
        return new UpdateSearchHashes($table, $manager);
    }

    /**
     * Construct the console service for verifying record links.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \FinnaConsole\Service\VerifyRecordLinks
     */
    public static function getVerifyRecordLinks(ServiceManager $sm)
    {
        $backendManager = $sm->get(\VuFind\Search\BackendManager::class);
        $tableManager = $sm->get(\VuFind\Db\Table\PluginManager::class);
        return new VerifyRecordLinks(
            $tableManager->get('Comments'),
            $tableManager->get('CommentsRecord'),
            $tableManager->get('Resource'),
            $backendManager->get('Solr')
        );
    }

    /**
     * Construct the console service for verifying resource metadata.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \FinnaConsole\Service\VerifyResourceMetadata
     */
    public static function getVerifyResourceMetadata(ServiceManager $sm)
    {
        $resourceTable = $sm->get(\VuFind\Db\Table\PluginManager::class)
            ->get('Resource');
        $dateConverter = $sm->get(\VuFind\Date\Converter::class);
        $recordLoader = $sm->get(\VuFind\Record\Loader::class);

        return new VerifyResourceMetadata(
            $resourceTable, $dateConverter, $recordLoader
        );
    }
}
