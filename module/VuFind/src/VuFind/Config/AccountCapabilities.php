<?php
/**
 * Class to determine which account capabilities are available, based on
 * configuration and other factors.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Config;
use Zend\Config\Config;

/**
 * Class to determine which account capabilities are available, based on
 * configuration and other factors.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class AccountCapabilities
{
    /**
     * VuFind configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param Config $config VuFind configuration
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Get comment setting.
     *
     * @return string
     */
    public function getCommentSetting()
    {
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
        $setting = isset($this->config->Social->lists)
            ? trim(strtolower($this->config->Social->lists)) : 'enabled';
        if (!$setting) {
            $setting = 'disabled';
        }
        $whitelist = ['enabled', 'disabled', 'public_only', 'private_only'];
        if (!in_array($setting, $whitelist)) {
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
        return isset($this->config->Social->tags)
            && $this->config->Social->tags === 'disabled'
            ? 'disabled' : 'enabled';
    }
}
