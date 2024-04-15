<?php

/**
 * Overdrive view helper
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

use VuFind\DigitalContent\OverdriveConnector;

/**
 * Overdrive view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Overdrive extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Overdrive connector.
     *
     * @var OverdriveConnector
     */
    protected $connector;

    /**
     * Constructor
     *
     * @param OverdriveConnector $connector Overdrive connector
     */
    public function __construct(OverdriveConnector $connector = null)
    {
        $this->connector = $connector;
    }

    /**
     * Is Overdrive content active?
     *
     * @return bool
     */
    public function showMyContentLink()
    {
        //if not configured at all, connector is null
        if (null === $this->connector) {
            return false;
        }
        $config = $this->connector->getConfig();
        if ($config->showMyContent == 'always') {
            return true;
        } elseif ($config->showMyContent == 'never') {
            return false;
        } else {
            //assume that it is accessOnly
            $result = $this->connector->getAccess();

            if (!$result->status && $result->code == 'od_account_noaccess') {
                return false;
            }
            return true;
        }
    }

    /**
     * Show the Overdrive API Admin Menu Item?
     *
     * @return bool
     */
    public function showOverdriveAdminLink()
    {
        // If not configured at all, connector is null
        if (null === $this->connector) {
            return false;
        }
        $config = $this->connector->getConfig();
        return (bool)($config->showOverdriveAdminMenu ?? false);
    }
}
