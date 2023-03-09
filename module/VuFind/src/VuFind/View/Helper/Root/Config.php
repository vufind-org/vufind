<?php

/**
 * Config view helper
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use VuFind\Config\PluginManager;

/**
 * Config view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Config extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Configuration plugin manager
     *
     * @var PluginManager
     */
    protected $configLoader;

    /**
     * Config constructor.
     *
     * @param PluginManager $configLoader Configuration loader
     */
    public function __construct(PluginManager $configLoader)
    {
        $this->configLoader = $configLoader;
    }

    /**
     * Get the specified configuration.
     *
     * @param string $config Name of configuration
     *
     * @return \Laminas\Config\Config
     */
    public function get($config)
    {
        return $this->configLoader->get($config);
    }

    /**
     * Is non-Javascript support enabled?
     *
     * @return bool
     */
    public function nonJavascriptSupportEnabled()
    {
        return $this->get('config')->Site->nonJavascriptSupportEnabled ?? false;
    }

    /**
     * Should be covers loaded via AJAX?
     *
     * @return bool
     */
    public function ajaxCoversEnabled()
    {
        return $this->get('config')->Content->ajaxcovers ?? false;
    }

    /**
     * Should we limit the number of items displayed on the full record?
     *
     * @return int
     */
    public function getHoldingsItemLimit()
    {
        $limit = $this->get('config')->Record->holdingsItemLimit;
        return $limit ? (int)$limit : PHP_INT_MAX;
    }

    /**
     * Should we limit the number of subjects displayed on the full record?
     *
     * @return int
     */
    public function getRecordSubjectLimit()
    {
        $limit = $this->get('config')->Record->subjectLimit;
        return $limit ? (int)$limit : PHP_INT_MAX;
    }

    /**
     * Check if index record should always be displayed (i.e. also when a
     * format-specific template is available)
     *
     * @return bool
     */
    public function alwaysDisplayIndexRecordInStaffView(): bool
    {
        return (bool)($this->get('config')->Record
            ->alwaysDisplayIndexRecordInStaffView ?? false);
    }
}
