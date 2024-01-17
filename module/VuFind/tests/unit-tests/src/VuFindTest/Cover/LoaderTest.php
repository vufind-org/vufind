<?php

/**
 * Cover Loader Test Class
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

namespace VuFindTest\Cover;

use Laminas\Config\Config;
use VuFind\Cover\Loader;
use VuFindTheme\ThemeInfo;

use function strlen;

/**
 * Cover Loader Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class LoaderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Theme to use for testing purposes.
     *
     * @var string
     */
    protected $testTheme = 'bootstrap3';

    /**
     * Test that failure to load even the baseline image causes an exception.
     *
     * @return void
     */
    public function testUtterFailure()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not load default fail image.');

        $theme = $this->getMockBuilder(\VuFindTheme\ThemeInfo::class)
            ->setConstructorArgs(['foo', 'bar'])->getMock();
        $theme->expects($this->once())
            ->method('findContainingTheme')
            ->with($this->equalTo(['images/noCover2.gif']))
            ->will($this->returnValue(false));
        $loader = $this->getLoader([], null, $theme);
        $loader->getImage();
    }

    /**
     * Test that requesting a content type causes default data to load.
     *
     * @return void
     */
    public function testDefaultLoadingForContentType()
    {
        $loader = $this->getLoader();
        $this->assertEquals('image/gif', $loader->getContentType());
        $this->assertEquals('368', strlen($loader->getImage()));
    }

    /**
     * Test that requesting an image causes default data to load.
     * (same as above test, but with assertions in different order to
     * force appropriate loading).
     *
     * @return void
     */
    public function testDefaultLoadingForImage()
    {
        $loader = $this->getLoader();
        $this->assertEquals('368', strlen($loader->getImage()));
        $this->assertEquals('image/gif', $loader->getContentType());
    }

    /**
     * Test missing user-specified fail image
     *
     * @return void
     */
    public function testMissingUserSpecifiedFailImage()
    {
        $badfile = 'not/a/real/file/at.all';
        $cfg = ['Content' => ['noCoverAvailableImage' => $badfile]];
        $loader = $this->getLoader($cfg, null, null, null, ['debug']);

        // We expect the loader to complain about the bad filename and load the default image:
        $loader->expects($this->once())->method('debug')->with($this->equalTo("Cannot access '$badfile'"));
        $loader->loadUnavailable();
        $this->assertEquals('368', strlen($loader->getImage()));
    }

    /**
     * Test illegal file extension
     *
     * @return void
     */
    public function testFailImageIllegalExtension()
    {
        $badfile = 'templates/layout/layout.phtml';
        $cfg = ['Content' => ['noCoverAvailableImage' => $badfile]];
        $loader = $this->getLoader($cfg, null, null, null, ['debug']);

        // We expect the loader to complain about the bad filename and load the default image:
        $expected = "Illegal file-extension 'phtml' for image '" . $this->getThemeDir() . '/'
            . $this->testTheme . '/' . $badfile . "'";
        $loader->expects($this->once())->method('debug')->with($this->equalTo($expected));
        $loader->loadUnavailable();
        $this->assertEquals('368', strlen($loader->getImage()));
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
    protected function getLoader($config = [], $manager = null, $theme = null, $httpService = null, $mock = false)
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
            $mock = array_unique(array_merge($mock, ['debug']));
            $mockLoader = $this->getMockBuilder(Loader::class)
                ->onlyMethods($mock)
                ->setConstructorArgs([$config, $manager, $theme, $httpService])
                ->getMock();
            return $mockLoader;
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
