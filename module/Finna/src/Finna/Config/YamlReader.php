<?php
/**
 * VuFind YAML Configuration Reader
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2017.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace Finna\Config;

use VuFind\Config\Locator;

/**
 * VuFind YAML Configuration Reader
 *
 * @category VuFind
 * @package  Config
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class YamlReader extends \VuFind\Config\YamlReader
{
    /**
     * Return a configuration
     *
     * @param string $filename config file name
     *
     * @return array
     */
    public function get($filename)
    {
        // Load data if it is not already in the object's cache:
        if (!isset($this->files[$filename])) {
            $localFile = Locator::getLocalConfigPath($filename);
            if (!file_exists($localFile)) {
                $localFile = Locator::getLocalConfigPath($filename, 'config/finna');
            }
            $this->files[$filename] = $this->getFromPaths(
                Locator::getBaseConfigPath($filename), $localFile
            );
        }

        return $this->files[$filename];
    }
}
