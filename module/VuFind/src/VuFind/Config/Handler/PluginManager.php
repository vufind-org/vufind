<?php

/**
 * Config handler plugin manager
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

/**
 * Config handler plugin manager
 *
 * @category VuFind
 * @package  Config_Handlers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'ini' => Ini::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        Dir::class => DirFactory::class,
        GenericFile::class => DefaultHandlerFactory::class,
        Ini::class => DefaultHandlerFactory::class,
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return HandlerInterface::class;
    }

    /**
     * Check if there is a configuration handler for a specific location.
     *
     * @param ConfigLocationInterface $configLocation Config location
     *
     * @return bool
     */
    public function hasForLocation(ConfigLocationInterface $configLocation): bool
    {
        return $this->has($configLocation->getHandler());
    }

    /**
     * Get the configuration handler for a specific location.
     *
     * @param ConfigLocationInterface $configLocation Config location
     *
     * @return HandlerInterface
     */
    public function getForLocation(ConfigLocationInterface $configLocation): HandlerInterface
    {
        return $this->get($configLocation->getHandler());
    }
}
