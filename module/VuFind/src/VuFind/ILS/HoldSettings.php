<?php
/**
 * ILS Hold Settings Class
 *
 * This class is responsible for determining hold settings for VuFind based
 * on configuration and defaults.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace VuFind\ILS;

/**
 * ILS Hold Settings Class
 *
 * This class is responsible for determining hold settings for VuFind based
 * on configuration and defaults.
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class HoldSettings
{
    /**
     * ILS configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config Configuration representing the [Catalog]
     * section of config.ini
     */
    public function __construct(\Zend\Config\Config $config)
    {
        $this->config = $config;
    }

    /**
     * Get Holds Mode
     *
     * This is responsible for returning the holds mode
     *
     * @return string The Holds mode
     */
    public function getHoldsMode()
    {
        return isset($this->config->holds_mode) ? $this->config->holds_mode : 'all';
    }

    /**
     * Get Title Holds Mode
     *
     * This is responsible for returning the Title holds mode
     *
     * @return string The Title Holds mode
     */
    public function getTitleHoldsMode()
    {
        return isset($this->config->title_level_holds_mode)
            ? $this->config->title_level_holds_mode : 'disabled';
    }
}
