<?php
/**
 * Class to determine which account capabilities are available, based on
 * configuration and other factors.
 *
 * PHP version 7
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
     * Auth manager
     *
     * @var AuthManager
     */
    protected $auth;

    /**
     * VuFind configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param Config      $config VuFind configuration
     * @param AuthManager $auth   Auth manager
     */
    public function __construct(Config $config, AuthManager $auth)
    {
        $this->auth = $auth;
        $this->config = $config;
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
     * Is a user account capable of saving data currently available?
     *
     * @return bool
     */
    protected function isAccountAvailable()
    {
        // We can't use account features if login is broken or privacy is on:
        return $this->auth->loginEnabled() && !$this->auth->inPrivacyMode();
    }

    /**
     * Check if record ratings can be removed
     *
     * @return bool
     */
    public function isRatingRemovalAllowed(): bool
    {
        return (bool)($this->Config->Social->remove_rating ?? true);
    }
}
