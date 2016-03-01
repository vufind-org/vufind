<?php
/**
 * CLI Controller Module
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class UtilController extends \VuFindConsole\Controller\UtilController
{
    /**
     * Clears expired MetaLib searches from the database.
     *
     * @return \Zend\Console\Response
     */
    public function clearMetaLibSearchAction()
    {
        return $this->runService('Finna\ClearMetaLibSearch');
    }

    /**
     * Anonymizes all the expired user accounts.
     *
     * @return \Zend\Console\Response
     */
    public function expireUsersAction()
    {
        return $this->runService('Finna\ExpireUsers');
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
        return $this->runService('Finna\ScheduledAlerts');
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
     * Helper function for running a service.
     *
     * @param string $service Service name.
     *
     * @return boolean success
     */
    protected function runService($service)
    {
        $arguments = $this->consoleOpts->getRemainingArgs();
        $service = $this->getServiceLocator()->get($service);
        $service->initLogging();
        return $service->run($arguments)
            ? $this->getSuccessResponse()
            : $this->getFailureResponse();
    }
}
