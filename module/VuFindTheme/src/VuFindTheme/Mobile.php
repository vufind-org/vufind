<?php

/**
 * Mobile Device Detection Wrapper
 *
 * PHP version 8
 *
 * This file is a wrapper around the mobileesp library for browser detection.
 * We chose mobileesp as VuFind's default option because it is fairly robust
 * and has an Apache license which allows free redistribution. However, it
 * is not the only option available. You can override this file in your local
 * directory if you wish to customize the detection functionality.
 *
 * Copyright (C) Villanova University 2009.
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
 * @package  Theme
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/ahand/mobileesp MobileESP Project
 */

namespace VuFindTheme;

use uagent_info;

/**
 * Mobile Device Detection Wrapper
 *
 * @category VuFind
 * @package  Theme
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/ahand/mobileesp MobileESP Project
 */
class Mobile
{
    /**
     * Mobile detection object
     *
     * @var uagent_info
     */
    protected $detector;

    /**
     * Are mobile themes enabled?
     *
     * @var bool
     */
    protected $enabled = false;

    /**
     * Constructor
     *
     * @param uagent_info $detector Detector object to wrap (null to create one)
     */
    public function __construct(uagent_info $detector = null)
    {
        $this->detector = $detector ?? new uagent_info();
    }

    /**
     * Function to detect if a mobile device is being used.
     *
     * @return bool
     */
    public function detect()
    {
        // Do the most exhaustive device detection possible; other method calls
        // may be used instead of DetectMobileLong if you want to target a narrower
        // class of devices.
        return $this->detector->DetectMobileLong();
    }

    /**
     * Function to set enabled status of mobile themes.
     *
     * @param bool $enabled Are mobile themes enabled?
     *
     * @return void
     */
    public function enable($enabled = true)
    {
        $this->enabled = $enabled;
    }

    /**
     * Function to check whether mobile theme is configured.
     *
     * @return bool
     */
    public function enabled()
    {
        return $this->enabled;
    }
}
