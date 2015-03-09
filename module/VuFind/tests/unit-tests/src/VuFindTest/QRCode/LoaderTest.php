<?php
/**
 * QR Code Loader Test Class
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\QRCode;
use VuFind\QRCode\Loader;
use VuFindTheme\ThemeInfo;
use Zend\Config\Config;

/**
 * QR Code Loader Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class LoaderTest extends \VuFindTest\Unit\TestCase
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
     *
     * @expectedException        Exception
     * @expectedExceptionMessage Could not load default fail image.
     */
    public function testUtterFailure()
    {
        $theme = $this->getMock('VuFindTheme\ThemeInfo', [], ['foo', 'bar']);
        $theme->expects($this->once())->method('findContainingTheme')->with($this->equalTo(['images/noQRCode.gif']))->will($this->returnValue(false));
        $loader = $this->getLoader([], $theme);
        $loader->getImage();
    }

    /**
     * Test that requesting a blank QR code returns the fail image.
     *
     * @return void
     */
    public function testDefaultLoadingForBlankText()
    {
        $loader = $this->getLoader();
        $loader->loadQRCode('');
        $this->assertEquals('image/gif', $loader->getContentType());
        $this->assertEquals('483', strlen($loader->getImage()));
    }

    /**
     * Get a loader object to test.
     *
     * @param array      $config Configuration
     * @param ThemeInfo  $theme  Theme info object (null to create default)
     * @param array|bool $mock   Array of functions to mock, or false for real object
     *
     * @return void
     */
    protected function getLoader($config = [], $theme = null, $mock = false)
    {
        $config = new Config($config);
        if (null === $theme) {
            $theme = new ThemeInfo($this->getThemeDir(), $this->testTheme);
        }
        if ($mock) {
            return $this->getMock('VuFind\QRCode\Loader', $mock, [$config, $theme]);
        }
        return new Loader($config, $theme);
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