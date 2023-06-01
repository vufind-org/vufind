<?php

/**
 * RecordTab Plugin Manager Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordTab;

use VuFind\RecordTab\PluginManager;

/**
 * RecordTab Plugin Manager Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class PluginManagerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test results.
     *
     * @return void
     */
    public function testShareByDefault()
    {
        $pm = new PluginManager(new \VuFindTest\Container\MockContainer($this));
        $this->assertTrue($this->getProperty($pm, 'sharedByDefault'));
    }

    /**
     * Test expected interface.
     *
     * @return void
     */
    public function testExpectedInterface()
    {
        $this->expectException(\Laminas\ServiceManager\Exception\InvalidServiceException::class);
        $this->expectExceptionMessage('Plugin ArrayObject does not belong to VuFind\\RecordTab\\TabInterface');

        $pm = new PluginManager(new \VuFindTest\Container\MockContainer($this));
        $pm->validate(new \ArrayObject());
    }
}
