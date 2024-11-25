<?php

/**
 * Cover Router Test Class
 *
 * PHP version 8
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

use Laminas\Config\Config;
use VuFind\Cover\Loader;
use VuFind\Cover\Router;
use VuFindTest\RecordDriver\TestHarness;
use VuFindTheme\ThemeInfo;

/**
 * Cover Router Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RouterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Theme to use for testing purposes.
     *
     * @var string
     */
    protected $testTheme = 'bootstrap3';

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
        return new Router('https://vufind.org/cover', $this->getCoverLoader());
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
            $url,
            $this->getRouter()->getUrl($this->getDriver(['Thumbnail' => $url]))
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

    /**
     * Get a loader object to test.
     *
     * @param array                                $config      Configuration
     * @param \VuFind\Content\Covers\PluginManager $manager     Plugin manager (null to create mock)
     * @param ThemeInfo                            $theme       Theme info object (null to create default)
     * @param \VuFindHttp\HttpService              $httpService HTTP client factory
     * @param array|bool                           $mock        Array of functions to mock, or false for real object
     *
     * @return Loader|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getCoverLoader($config = [], $manager = null, $theme = null, $httpService = null, $mock = false)
    {
        $config = new Config($config);
        if (null === $manager) {
            $manager = $this->createMock(\VuFind\Content\Covers\PluginManager::class);
        }
        if (null === $theme) {
            $theme = new ThemeInfo($this->getThemeDir(), $this->testTheme);
        }
        if (null === $httpService) {
            $httpService = $this->getMockBuilder(\VuFindHttp\HttpService::class)->getMock();
        }
        if ($mock) {
            return $this->getMockBuilder(__NAMESPACE__ . '\MockLoader')
                ->onlyMethods($mock)
                ->setConstructorArgs([$config, $manager, $theme, $httpService])
                ->getMock();
        }
        return new Loader($config, $manager, $theme, $httpService);
    }

    /**
     * Get the theme directory.
     *
     * @return string
     */
    protected function getThemeDir()
    {
        return realpath(__DIR__ . '/../../../../../../../themes');
    }
}
