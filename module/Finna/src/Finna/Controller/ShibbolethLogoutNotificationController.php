<?php
/**
 * Shibboleth Logout Notification API Controller
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace Finna\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * Handles Shibboleth back-channel logout notifications.
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ShibbolethLogoutNotificationController
    extends \VuFind\Controller\ShibbolethLogoutNotificationController
{
    /**
     * Is R2 search enabled?
     *
     * @var bool
     */
    protected $r2Enabled;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm        Service locator
     * @param bool                    $r2Enabled Is R2 search enabled?
     */
    public function __construct(ServiceLocatorInterface $sm, $r2Enabled)
    {
        $this->r2Enabled = $r2Enabled;
        parent::__construct($sm);
    }

    /**
     * Logout notification handler
     *
     * @param string $sessionId External session id
     *
     * @return void
     */
    public function logoutNotification($sessionId)
    {
        if ($this->r2Enabled) {
            $table = $this->getTable('ExternalSession');
            $row = $table->getByExternalSessionId(trim($sessionId));
            if (empty($row)) {
                return;
            }

            $remsId = $row['session_id'] . 'REMS';
            $remsRow = $table->select(['session_id' => $remsId])->current();
            if ($remsUsername = ($remsRow['external_session_id'] ?? '')) {
                $this->serviceLocator->get(\Finna\Service\RemsService::class)
                    ->closeOpenApplicationsForUser($remsUsername);
            }
        }
        parent::logoutNotification($sessionId);
    }
}
