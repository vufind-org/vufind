<?php
/**
 * Cover Router Test Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2016.
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
namespace VuFindTest\Cover;
use VuFind\Cover\Router, VuFindTest\RecordDriver\TestHarness;

/**
 * Cover Router Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RouterTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Get a fake record driver
     *
     * @param array $data Test data
     *
     * @return TestHarness
     */
    protected function getDriver($data)
    {
        $driver = new TestHarness();
        $driver->setRawData($data);
        return $driver;
    }

    /**
     * Get a router to test
     *
     * @return Router
     */
    protected function getRouter()
    {
        return new Router('https://vufind.org/cover');
    }

    /**
     * Test a record driver with no thumbnail data.
     *
     * @return void
     */
    public function testUnsupportedThumbnail()
    {
        $this->assertFalse(
            $this->getRouter()->getUrl($this->getDriver([]))
        );
    }

    /**
     * Test a record driver with static thumbnail data.
     *
     * @return void
     */
    public function testStaticUrl()
    {
        $url = 'http://foo/bar';
        $this->assertEquals(
            $url, $this->getRouter()->getUrl($this->getDriver(['Thumbnail' => $url]))
        );
    }

    /**
     * Test a record driver with dynamic thumbnail data.
     *
     * @return void
     */
    public function testDynamicUrl()
    {
        $params = ['foo' => 'bar'];
        $this->assertEquals(
            'https://vufind.org/cover?foo=bar',
            $this->getRouter()->getUrl($this->getDriver(['Thumbnail' => $params]))
        );
    }
}
