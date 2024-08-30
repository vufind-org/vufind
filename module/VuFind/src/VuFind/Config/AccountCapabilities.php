<?php

/**
 * Class to determine which account capabilities are available, based on
 * configuration and other factors.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2015.
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
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Config;

use Laminas\Config\Config;
use VuFind\Auth\Manager as AuthManager;

use function in_array;

/**
 * Class to determine which account capabilities are available, based on
 * configuration and other factors.
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AccountCapabilities
{
    /**
     * Function to fetch auth manager
     *
     * @var callable
     */
    protected $authCallback;

    /**
     * Constructor
     *
     * @param Config   $config  Top-level configuration
     * @param callable $getAuth Function to fetch auth manager
     */
    public function __construct(protected Config $config, callable $getAuth)
    {
        $this->authCallback = $getAuth;
    }

    /**
     * Get authentication manager
     *
     * @return AuthManager
     */
    protected function getAuth(): AuthManager
    {
        return ($this->authCallback)();
    }

    /**
     * Get comment setting.
     *
     * @return string
     */
    public function getCommentSetting()
    {
        if (!$this->isAccountAvailable()) {
            return 'disabled';
        }
        return isset($this->config->Social->comments)
            && $this->config->Social->comments === 'disabled'
            ? 'disabled' : 'enabled';
    }

    /**
     * Get list setting.
     *
     * @return string
     */
    public function getListSetting()
    {
        if (!$this->isAccountAvailable()) {
            return 'disabled';
        }
        $setting = isset($this->config->Social->lists)
            ? trim(strtolower($this->config->Social->lists)) : 'enabled';
        if (!$setting) {
            $setting = 'disabled';
        }
        $legal = ['enabled', 'disabled', 'public_only', 'private_only'];
        if (!in_array($setting, $legal)) {
            $setting = 'enabled';
        }
        return $setting;
    }

    /**
     * Get saved search setting.
     *
     * @return string
     */
    public function getSavedSearchSetting()
    {
        if (!$this->isAccountAvailable()) {
            return 'disabled';
        }
        return isset($this->config->Site->allowSavedSearches)
            && !$this->config->Site->allowSavedSearches
            ? 'disabled' : 'enabled';
    }

    /**
     * Get tag setting.
     *
     * @return string
     */
    public function getTagSetting()
    {
        if (!$this->isAccountAvailable()) {
            return 'disabled';
        }
        return isset($this->config->Social->tags)
            && $this->config->Social->tags === 'disabled'
            ? 'disabled' : 'enabled';
    }

    /**
     * Get list tag setting.
     *
     * @return string
     */
    public function getListTagSetting()
    {
        if (!$this->isAccountAvailable()) {
            return 'disabled';
        }
        return $this->config->Social->listTags ?? 'disabled';
    }

    /**
     * Is scheduled search enabled?
     *
     * @return bool
     */
    public function isScheduledSearchEnabled(): bool
    {
        return $this->config->Account->schedule_searches ?? false;
    }

    /**
     * Get SMS setting ('enabled' or 'disabled').
     *
     * @return string
     */
    public function getSmsSetting()
    {
        return isset($this->config->Mail->sms)
            && $this->config->Mail->sms === 'disabled'
            ? 'disabled' : 'enabled';
    }

    /**
     * Get email action setting ('enabled', 'require_login' or 'disabled').
     *
     * @return string
     */
    public function getEmailActionSetting(): string
    {
        return $this->config?->Mail?->email_action ??
            (($this->config?->Mail?->require_login ?? true) ? 'require_login' : 'enabled');
    }

    /**
     * Check if emailing of records and searches is available.
     *
     * @return bool
     */
    public function isEmailActionAvailable(): bool
    {
        $emailActionSettings = $this->getEmailActionSetting();
        return $emailActionSettings === 'enabled'
            || $emailActionSettings === 'require_login' && $this->getAuth()->loginEnabled();
    }

    /**
     * Is a user account capable of saving data currently available?
     *
     * @return bool
     */
    protected function isAccountAvailable()
    {
        // We can't use account features if login is broken or privacy is on:
        $auth = $this->getAuth();
        return $auth->loginEnabled() && !$auth->inPrivacyMode();
    }

    /**
     * Check if record ratings can be removed
     *
     * @return bool
     */
    public function isRatingRemovalAllowed(): bool
    {
        return (bool)($this->config->Social->remove_rating ?? true);
    }

    /**
     * Are library cards enabled and supported?
     *
     * @return bool
     */
    public function libraryCardsEnabled(): bool
    {
        return ($this->config->Catalog->library_cards ?? false) && !$this->getAuth()->inPrivacyMode();
    }
}
