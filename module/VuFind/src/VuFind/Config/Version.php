<?php
/**
 * Version check utility
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Config;

/**
 * Version check utility
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Version
{
    /**
     * Extract version number from the build.xml file of the running instance or
     * another instance pointed to by $dir
     *
     * @param string $dir Optional directory containing build.xml
     *
     * @throws \Exception
     * @return string
     */
    public static function getBuildVersion($dir = '')
    {
        static $cachedVersions = [];

        if ($dir === '') {
            $dir = realpath(APPLICATION_PATH);
        }

        if (!isset($cachedVersions[$dir])) {
            $file = $dir . '/build.xml';
            $xml = file_exists($file) ? simplexml_load_file($file) : false;
            if (!$xml) {
                throw new \Exception('Cannot load ' . $file . '.');
            }
            $parts = $xml->xpath('/project/property[@name="version"]/@value');
            $cachedVersions[$dir] = (string)$parts[0];
        }

        return $cachedVersions[$dir];
    }
}
