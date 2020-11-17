<?php
/**
 * Helper class for restricted Solr R2 search.
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
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

use Finna\Db\Row\User;
use Finna\Service\RemsService;
use VuFind\RecordDriver\AbstractBase;

/**
 * Helper class for restricted Solr R2 search.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class R2 extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Is R2 search enabled?
     *
     * @var bool
     */
    protected $enabled;

    /**
     * Current user.
     *
     * @var User|null
     */
    protected $user;

    /**
     * Is user authenticated to use R2?
     *
     * @var bool
     */
    protected $authenticated;

    /**
     * RemsService
     *
     * @var RemsService
     */
    protected $rems;

    /**
     * Constructor
     *
     * @param bool        $enabled       Is R2 enabled?
     * @param User|null   $user          Current user
     * @param bool        $authenticated Is user authenticated to use R2?
     * @param RemsService $rems          RemsService
     */
    public function __construct(
        bool $enabled, ?User $user, bool $authenticated, RemsService $rems
    ) {
        $this->enabled = $enabled;
        $this->user = $user;
        $this->authenticated = $authenticated;
        $this->rems = $rems;
    }

    /**
     * Check if R2 is available
     *
     * @return bool
     */
    public function isAvailable()
    {
        return $this->enabled;
    }

    /**
     * Check if user is authenticated to use R2.
     *
     * @return bool
     */
    public function isAuthenticated()
    {
        return $this->authenticated;
    }

    /**
     * Check if user is registered to REMS during this session.
     *
     * @param bool $checkEntitlements Also check entitlements?
     *
     * @return bool
     */
    public function isRegistered($checkEntitlements = false)
    {
        return $this->user
            && $this->rems->isUserRegisteredDuringSession($checkEntitlements);
    }

    /**
     * Check if user is has access to R2
     *
     * @param bool $ignoreCache Ignore cache?
     *
     * @return bool
     */
    public function hasUserAccess($ignoreCache = true)
    {
        return $this->user
            && $this->rems->hasUserAccess($ignoreCache);
    }

    /**
     * Render R2 registration info. Returns HTML.
     *
     * @param AbstractBase $driver Record driver
     * @param array        $params Parameters
     *
     * @return null|string
     */
    public function registeredInfo($driver, $params = null)
    {
        if (!$this->isAvailable() || !$this->user) {
            return null;
        }

        // Driver is null when the helper is called outside record page
        if (!$driver || $driver->trymethod('hasRestrictedMetadata')) {
            try {
                if (!$this->rems->hasUserAccess(true, $params['throw'] ?? false)) {
                    // Registration hint on search results page.
                    if ($params['show_register_hint'] ?? false) {
                        return
                            $this->getView()->render('Helpers/R2RegisterHint.phtml');
                    }
                    return null;
                }
            } catch (\Exception $e) {
                $translator = $this->getView()->plugin('translate');
                return '<div class="alert alert-danger">'
                    . $translator->translate('R2_rems_connect_error') . '</div>';
            }

            $warning = null;
            if ($this->rems->isSearchLimitExceeded('daily')) {
                $warning = 'R2_daily_limit_exceeded';
            } elseif ($this->rems->isSearchLimitExceeded('monthly')) {
                $warning = 'R2_monthly_limit_exceeded';
            }
            $tplParams = [
                'usagePurpose' => $this->rems->getUsagePurpose(),
                'showInfo' => !($params['hideInfo'] ?? false),
                'warning' => $warning
            ];

            return $this->getView()->render(
                'Helpers/R2RestrictedRecordRegistered.phtml', $tplParams
            );
        }

        return null;
    }

    /**
     * Render R2 registration prompt. Returns HTML.
     *
     * @param AbstractBase $driver Record driver
     * @param array        $params Parameters
     *
     * @return string|null
     */
    public function register($driver, $params = null)
    {
        if (!$this->isAvailable()) {
            return null;
        }

        // Driver is null when the helper is called outside record page
        if (!$driver || $driver->tryMethod('hasRestrictedMetadata')) {
            $restricted = $driver
                ? $driver->tryMethod('isRestrictedMetadataIncluded') : false;
            if ($restricted) {
                return null;
            }
            $blocklisted = $registered = $sessionClosed = false;
            $blocklistedDate = null;
            try {
                if ($this->rems->hasUserAccess(
                    $params['ignoreCache'] ?? false, true
                )
                ) {
                    // Already registered
                    return null;
                } else {
                    $blocklisted
                        = $this->user ? $this->rems->isUserBlocklisted() : false;
                    if ($blocklisted) {
                        $dateTime = $this->getView()->plugin('dateTime');
                        try {
                            $blocklistedDate = $dateTime->convertToDisplayDate(
                                'Y-m-d', $blocklisted
                            );
                        } catch (\Exception $e) {
                        }
                    }
                    $status = $this->rems->getAccessPermission();
                    $sessionClosed = in_array(
                        $status,
                        [RemsService::STATUS_EXPIRED, RemsService::STATUS_REVOKED]
                    );
                }
            } catch (\Exception $e) {
                $translator = $this->getView()->plugin('translate');
                return '<div class="alert alert-danger">'
                    . $translator->translate('R2_rems_connect_error') . '</div>';
            }

            $name = '';
            if (!empty($this->user->firstname ?? null)) {
                $name = $this->user->firstname;
            }
            if (!empty($this->user->lastname ?? null)) {
                if (!empty($name)) {
                    $name .= ' ';
                }
                $name .= $this->user->lastname;
            }

            $params = [
                'note' => $params['note'] ?? null,
                'warning' => $sessionClosed ? 'R2_session_expired_title' : null,
                'instructions' => $params['instructions'] ?? null,
                'showInfo' => !($params['hideInfo'] ?? false),
                'weakLogin' => $this->user && !$this->authenticated,
                'user' => $this->user,
                'name' => $name,
                'id' => $driver ? $driver->getUniqueID() : null,
                'collection' => $driver ? $driver->isCollection() : false,
                'blocklisted' => $blocklisted,
                'blocklistedDate' => $blocklistedDate,
                'formId' => 'R2Register',
            ];

            return $this->getView()->render(
                'Helpers/R2RestrictedRecordRegister.phtml', $params
            );
        }

        return null;
    }
}
