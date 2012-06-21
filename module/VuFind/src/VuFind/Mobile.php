<?php
/**
 * Mobile Device Detection Wrapper
 *
 * PHP version 5
 *
 * This file is a wrapper around the mobileesp library for browser detection.
 * We chose mobileesp as VuFind's default option because it is fairly robust
 * and has an Apache license which allows free redistribution.  However, it
 * is not the only option available.  You can override this file in your local
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://code.google.com/p/mobileesp/ MobileESP Project
 */
namespace VuFind;
use VuFind\Config\Reader as ConfigReader;

/**
 * Mobile Device Detection Wrapper
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://code.google.com/p/mobileesp/ MobileESP Project
 */
class Mobile
{
    /**
     * Function to detect if a mobile device is being used.
     *
     * @return bool
     */
    public static function detect()
    {
        // Do the most exhaustive device detection possible; other method calls
        // may be used instead of DetectMobileLong if you want to target a narrower
        // class of devices.
        /* TODO: implement this
        $mobile = new uagent_info();
        return $mobile->DetectMobileLong();
         */
        return false;
    }

    /**
     * Function to check whether mobile theme is configured.
     *
     * @return bool
     */
    public static function enabled()
    {
        $config = ConfigReader::getConfig();
        return isset($config->Site->mobile_theme);
    }
}