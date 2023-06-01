<?php

/**
 * Trait for plugin managers that allows service names to be normalized to lowercase
 * (for backward compatibility with ServiceManager v2).
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017.
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
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ServiceManager;

/**
 * Trait for plugin managers that allows service names to be normalized to lowercase
 * (for backward compatibility with ServiceManager v2).
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait LowerCaseServiceNameTrait
{
    /**
     * Retrieve a plugin
     *
     * @param string     $name    Name of plugin
     * @param null|array $options Options to use when creating the instance.
     *
     * @return mixed
     */
    public function get($name, array $options = null)
    {
        return parent::get($this->getNormalizedServiceName($name), $options);
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has($id)
    {
        return parent::has($this->getNormalizedServiceName($id));
    }

    /**
     * Hack for backward compatibility with services defined under
     * ServiceManager v2, when service names were case-insensitive.
     * TODO: set up aliases and/or normalize code to eliminate the need for this.
     *
     * @param string $name Service name
     *
     * @return string
     */
    protected function getNormalizedServiceName($name)
    {
        if (
            $name != ($lower = strtolower($name))
            && (isset($this->services[$lower]) || isset($this->factories[$lower])
            || isset($this->aliases[$lower]))
        ) {
            return $lower;
        }
        return $name;
    }
}
