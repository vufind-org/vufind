<?php
/**
 * Container that produces mock objects.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFindTest\Container;

use Interop\Container\ContainerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Container that produces mock objects.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class MockContainer implements ContainerInterface
{
    /**
     * Disabled services.
     *
     * @var string[]
     */
    protected $disabled = [];

    /**
     * Services
     *
     * @var array
     */
    protected $services = [];

    /**
     * Common service aliases.
     *
     * @var array
     */
    protected $aliases = [
        'ViewHelperManager' => \Laminas\View\HelperPluginManager::class,
    ];

    /**
     * Test case (for building mock objects)
     *
     * @var TestCase
     */
    protected $test;

    /**
     * Constructor
     *
     * @param TestCase $test Test using the container
     */
    public function __construct(TestCase $test)
    {
        $this->test = $test;
    }

    /**
     * Create a mock object.
     *
     * @param string $id      Identifier of the service to mock out.
     * @param array  $methods Methods to mock.
     *
     * @return mixed
     */
    public function createMock($id, $methods = [])
    {
        return $this->test->getMockBuilder($id)
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
    }

    /**
     * Disable a service
     *
     * @param string $id Identifier of the entry to disable.
     *
     * @return MockContainer
     */
    public function disable($id)
    {
        // Don't double-disable a service:
        if ($this->has($id)) {
            $this->disabled[] = $id;
        }
        // Fluent interface:
        return $this;
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $rawId Identifier of the entry to look for.
     *
     * @return mixed
     */
    public function get($rawId)
    {
        $id = $this->aliases[$rawId] ?? $rawId;
        if (!isset($this->services[$id])) {
            $this->services[$id] = $this->createMock($id);
        }
        return $this->services[$id];
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * @param string $rawId Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has($rawId)
    {
        $id = $this->aliases[$rawId] ?? $rawId;
        // Assume every service exists unless explicitly disabled
        return !in_array($id, $this->disabled);
    }

    /**
     * Explicitly set an entry in the container.
     *
     * @param string $id  Identifier of the entry to set.
     * @param mixed  $obj The service to set.
     *
     * @return MockContainer
     */
    public function set($id, $obj)
    {
        $this->services[$id] = $obj;
        return $this;
    }
}
