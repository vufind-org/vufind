<?php
/**
 * CLI Controller Module
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2017.
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
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
namespace FinnaConsole\Controller;

/**
 * This controller handles various command-line tools
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class UtilController extends \VuFindConsole\Controller\UtilController
{
    /**
     * Sends reminders for expiring user accounts
     *
     * @return \Zend\Console\Response
     */
    public function accountExpirationRemindersAction()
    {
        return $this->runService('Finna\AccountExpirationReminders');
    }

    /**
     * Sends due date reminders.
     *
     * @return \Zend\Console\Response
     */
    public function dueDateRemindersAction()
    {
        return $this->runService('Finna\DueDateReminders');
    }

    /**
     * Encypt catalog passwords.
     *
     * @return \Zend\Console\Response
     */
    public function encryptCatalogPasswordsAction()
    {
        return $this->runService('Finna\EncryptCatalogPasswords');
    }

    /**
     * Command-line tool to clear unwanted entries
     * from finna cache database table.
     *
     * @return \Zend\Console\Response
     */
    public function expireFinnaCacheAction()
    {
        $request = $this->getRequest();
        if ($request->getParam('help') || $request->getParam('h')) {
            return $this->expirationHelp('cache entries');
        }

        return $this->expire(
            'FinnaCache',
            '%%count%% expired cache entries deleted.',
            'No expired cache entries to delete.'
        );
    }

    /**
     * Command-line tool to clear unwanted entries
     * from session database table.
     *
     * @return \Zend\Console\Response
     */
    public function expiresessionsAction()
    {
        $request = $this->getRequest();
        if ($request->getParam('help') || $request->getParam('h')) {
            return $this->expirationHelp('sessions');
        }

        return $this->expire(
            'Session',
            '%%count%% expired sessions deleted.',
            'No expired sessions to delete.',
            0.3
        );
    }

    /**
     * Delete expired user accounts.
     *
     * @return \Zend\Console\Response
     */
    public function expireUsersAction()
    {
        return $this->runService('Finna\ExpireUsers');
    }

    /**
     * Import comments
     *
     * @return \Zend\Console\Response
     */
    public function importCommentsAction()
    {
        return $this->runService('Finna\ImportComments');
    }

    /**
     * Process unregistered online paymenets.
     *
     * @return \Zend\Console\Response
     */
    public function onlinePaymentMonitorAction()
    {
        return $this->runService('Finna\OnlinePaymentMonitor');
    }

    /**
     * Send scheduled alerts.
     *
     * @return \Zend\Console\Response
     */
    public function scheduledAlertsAction()
    {
        $controller = $this->serviceLocator->get('ControllerManager')
            ->get(\VuFindConsole\Controller\ScheduledSearchController::class);
        $controller->setRequest($this->getRequest());
        return $controller->notifyAction();
    }

    /**
     * Update search hashes. One-off after VuFind 1 migration.
     *
     * @return \Zend\Console\Response
     */
    public function updateSearchHashesAction()
    {
        return $this->runService('Finna\UpdateSearchHashes');
    }

    /**
     * Verify record links.
     *
     * @return \Zend\Console\Response
     */
    public function verifyRecordLinksAction()
    {
        return $this->runService('Finna\VerifyRecordLinks');
    }

    /**
     * Verify resource metadata.
     *
     * @return \Zend\Console\Response
     */
    public function verifyResourceMetadataAction()
    {
        return $this->runService('Finna\VerifyResourceMetadata');
    }

    /**
     * Helper function for running a service.
     *
     * @param string $service Service name.
     *
     * @return boolean success
     */
    protected function runService($service)
    {
        $arguments = $this->getRequest()->getParams()->toArray();
        $arguments = array_splice($arguments, 2, -2);
        $sl = $this->serviceLocator;
        // Disable sharing of mailer so that every time an instance is requested a
        // new one is created. This avoids sharing an SMTP connection that might time
        // out during a long execution.
        $sl->setShared('VuFind\Mailer', false);
        $service = $sl->get($service);
        $service->initLogging();
        return $service->run($arguments, $this->getRequest())
            ? $this->getSuccessResponse()
            : $this->getFailureResponse();
    }
}
