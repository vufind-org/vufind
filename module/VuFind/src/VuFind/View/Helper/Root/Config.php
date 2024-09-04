<?php

/**
 * Config view helper
 *
 * PHP version 8
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
     * Display date format
     *
     * @var ?string
     */
    protected $displayDateFormat = null;

    /**
     * Display time format
     *
     * @var ?string
     */
    protected $displayTimeFormat = null;

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
     * Should covers be loaded via AJAX?
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

    /**
     * Get offcanvas sidebar side
     *
     * @return ?string 'left', 'right' or null for no offcanvas
     */
    public function offcanvasSide(): ?string
    {
        $config = $this->get('config');
        if (!($config->Site->offcanvas ?? false)) {
            return null;
        }
        return ($config->Site->sidebarOnLeft ?? false)
            ? 'left'
            : 'right';
    }

    /**
     * Get date display format
     *
     * @return string
     */
    public function dateFormat(): string
    {
        if (null === $this->displayDateFormat) {
            $config = $this->get('config');
            $this->displayDateFormat = $config->Site->displayDateFormat ?? 'm-d-Y';
        }
        return $this->displayDateFormat;
    }

    /**
     * Get time display format
     *
     * @return string
     */
    public function timeFormat(): string
    {
        if (null === $this->displayTimeFormat) {
            $config = $this->get('config');
            $this->displayTimeFormat = $config->Site->displayTimeFormat ?? 'H:i';
        }
        return $this->displayTimeFormat;
    }

    /**
     * Get date+time display format
     *
     * @param string $separator String between date and time
     *
     * @return string
     */
    public function dateTimeFormat($separator = ' '): string
    {
        return $this->dateFormat() . $separator . $this->timeFormat();
    }
}
