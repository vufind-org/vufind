<?php

/**
 * VuFind Action Feature Trait - Configuration file path methods
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Feature;

/**
 * VuFind Action Feature Trait - Configuration file path methods
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait ConfigPathTrait
{
    /**
     * Get path to base configuration file
     *
     * @param string $filename Configuration file name
     *
     * @return string
     */
    protected function getBaseConfigFilePath(string $filename): string
    {
        $resolver = $this->getService(\VuFind\Config\PathResolver::class);
        return $resolver->getBaseConfigPath($filename);
    }

    /**
     * Get path to local configuration file (even if it does not yet exist)
     *
     * @param string $filename Configuration file name
     *
     * @return string
     */
    protected function getForcedLocalConfigPath(string $filename): string
    {
        $resolver = $this->getService(\VuFind\Config\PathResolver::class);
        return $resolver->getLocalConfigPath($filename, null, true);
    }
}
