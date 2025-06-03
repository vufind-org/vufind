<?php

/**
 * Abstract config handler base class.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2025.
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
 * @package  Config_Handlers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Config\Handler;

use VuFind\Config\Location\ConfigLocationInterface;
use VuFind\Config\Location\ConfigLocationTrait;
use VuFind\Exception\FileAccess as FileAccessException;

/**
 * Abstract config handler base class.
 *
 * @category VuFind
 * @package  Config_Handlers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class AbstractBase implements HandlerInterface
{
    use ConfigLocationTrait;

    /**
     * Create a new config location object on a path based on another config location.
     *
     * @param ConfigLocationInterface $configLocation Original config location
     * @param string                  $path           New config location path
     *
     * @return ConfigLocationInterface
     *
     * @throws FileAccessException
     */
    protected function getParentLocationOnPath(
        ConfigLocationInterface $configLocation,
        string $path
    ): ConfigLocationInterface {
        $parentLocation = $this->getConfigLocationOnPath($path);
        if ($parentLocation === null) {
            throw new FileAccessException("Error: $path does not exist.");
        }
        $parentLocation->setConfigName($configLocation->getConfigName());
        return $parentLocation;
    }
}
