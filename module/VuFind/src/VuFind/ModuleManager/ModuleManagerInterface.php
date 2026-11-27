<?php

/**
 * Module manager interface.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  ModuleManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ModuleManager;

/**
 * Module manager interface.
 *
 * @category VuFind
 * @package  ModuleManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
interface ModuleManagerInterface
{
    /**
     * Load modules.
     *
     * @return void
     */
    public function loadModules(): void;

    /**
     * Load a specific module by name.
     *
     * @param string $moduleName Module name
     *
     * @return mixed Module's Module class
     */
    public function loadModule(string $moduleName): mixed;

    /**
     * Get an array of loaded modules.
     *
     * @param bool $loadModules If true, load modules if they're not already loaded
     *
     * @return array An array of Module objects, keyed by module name
     */
    public function getLoadedModules(bool $loadModules): array;

    /**
     * Get the list of modules.
     *
     * @return array
     */
    public function getModules(): array;

    /**
     * Set the list of modules.
     *
     * @param array $modules Module names
     *
     * @return static
     */
    public function setModules(array $modules): static;

    /**
     * Get merged module configuration.
     *
     * @return array
     */
    public function getMergedConfig(): array;
}
