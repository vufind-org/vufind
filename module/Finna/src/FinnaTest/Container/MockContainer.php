<?php

/**
 * Container that produces mock objects.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace FinnaTest\Container;

use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFindTest\Container\MockContainerTrait;

use function array_key_exists;

/**
 * Container that produces mock objects.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class MockContainer implements ServiceLocatorInterface
{
    use MockContainerTrait;

    /**
     * Entries
     *
     * @var array
     */
    protected array $entries = [];

    /**
     * Alias for createMock(), needed to conform to ServiceLocatorInterface.
     *
     * @param string $name    Name of service to build
     * @param ?array $options Options
     *
     * @return mixed
     */
    public function build($name, ?array $options = null)
    {
        return $this->createMock($name, $options ?? []);
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @throws NotFoundExceptionInterface  No entry was found for **this** identifier.
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     *
     * @return mixed Entry.
     */
    public function get(string $id)
    {
        if (!array_key_exists($id, $this->entries)) {
            throw new ServiceNotFoundException("$id not found");
        }
        return $this->entries[$id];
    }

    /**
     * Add an entry to the container
     *
     * @param string $id    Entry identifier
     * @param mixed  $value Value
     *
     * @return void
     */
    public function add(string $id, $value): void
    {
        $this->entries[$id] = $value;
    }
}
