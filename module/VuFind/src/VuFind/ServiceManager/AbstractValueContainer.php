<?php

/**
 * Abstract value container.
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
 * @package  Application
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ServiceManager;

use Exception;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Psr\Container\ContainerInterface;

/**
 * Abstract value container.
 *
 * @category VuFind
 * @package  Application
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractValueContainer implements ContainerInterface
{
    /**
     * Return value by its name.
     *
     * @param string $id Identifier of the value
     *
     * @throws ServiceNotFoundException No entry was found for the identifier.
     * @return mixed
     */
    public function get(string $id)
    {
        if (!$this->has($id)) {
            throw new Exception("Value $id not found");
        }

        $method = $this->getMethodName($id);
        return $this->$method();
    }

    /**
     * Check if a value is available.
     *
     * @param string $id Identifier of the value
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        $method = $this->getMethodName($id);
        return method_exists($this, $method);
    }

    /**
     * Get method name for a value.
     *
     * @param string $id Identifier of the value
     *
     * @return string
     */
    protected function getMethodName(string $id): string
    {
        return 'getValue' . $id;
    }
}
