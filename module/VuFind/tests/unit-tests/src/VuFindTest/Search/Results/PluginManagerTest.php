<?php
/**
 * Search\Results Plugin Manager Test Class
 *
 * PHP version 5
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
namespace VuFindTest\Search\Results;
use VuFind\Search\Results\PluginManager;

/**
 * Search\Results Plugin Manager Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class PluginManagerTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test results.
     *
     * @return void
     */
    public function testShareByDefault()
    {
        $pm = new PluginManager(null);
        $this->assertFalse($pm->shareByDefault());
    }

    /**
     * Test expected interface.
     *
     * @return void
     *
     * @expectedException        Zend\ServiceManager\Exception\RuntimeException
     * @expectedExceptionMessage Plugin ArrayObject does not belong to VuFind\Search\Base\Results
     */
    public function testExpectedInterface()
    {
        $pm = new PluginManager(null);
        $pm->validatePlugin(new \ArrayObject());
    }
}
