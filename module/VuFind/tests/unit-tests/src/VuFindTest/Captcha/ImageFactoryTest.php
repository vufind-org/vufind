<?php

/**
 * Unit tests for Image CAPTCHA handler factory.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
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
namespace VuFindTest\Captcha;

/**
 * Unit tests for Image CAPTCHA handler factory.
 *
 * @requires extension gd
 * @requires function imagepng
 * @requires function imageftbbox
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ImageFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Internal workaround variable regarding willReturn().
     *
     * @var string
     */
    protected static $cacheBasePath;

    /**
     * Getter for internal workaround variable regarding willReturn().
     *
     * @return string
     */
    public static function getCacheBasePath()
    {
        return self::$cacheBasePath;
    }

    /**
     * Helper function to execute a single test and manipulate the
     * cache base path if necessary.
     *
     * @param string $homeUrl Home URL
     */
    protected function testFactoryHelper($homeUrl = null)
    {
        // Set up mock services expected by factory:
        $options = new \Laminas\Cache\Storage\Adapter\FilesystemOptions();
        $container = new \VuFindTest\Container\MockContainer($this);
        $storage = $container->get(\Laminas\Cache\Storage\StorageInterface::class);
        $storage->expects($this->once())->method('getOptions')
            ->will($this->returnValue($options));
        $cacheManager = $container->get(\VuFind\Cache\Manager::class);
        $cacheManager->expects($this->once())->method('getCache')
            ->with($this->equalTo('public'))
            ->will($this->returnValue($storage));

        $url = $container->get(\VuFind\View\Helper\Root\Url::class);
        $url->expects($this->once())->method('__invoke')
            ->with($this->equalTo('home'))
            ->will($this->returnValue($homeUrl));

        $manager = $container->get('ViewHelperManager');

        $manager->expects($this->once())->method('get')
            ->with($this->equalTo('url'))->will($this->returnValue($url));

        $factory = new \VuFind\Captcha\ImageFactory();
        $fakeImage = new class {
            /**
             * Constructor arguments
             *
             * @var array
             */
            public $constructorArgs;

            /**
             * Constructor
             */
            public function __construct()
            {
                $this->constructorArgs = func_get_args();
            }
        };
        $result = $factory($container, get_class($fakeImage));
        $expectedFont = APPLICATION_PATH
        . '/vendor/webfontkit/open-sans/fonts/opensans-regular.ttf';
        $this->assertTrue(file_exists($expectedFont));
        $expected = [
            'font' => $expectedFont,
            'imgDir' => $options->getCacheDir()
        ];
        $this->assertEquals($expected, $result->constructorArgs[0]->getOptions());
        $this->assertEquals('/cache/', $result->constructorArgs[1]);
    }

    /**
     * Test that the factory behaves correctly.
     *
     * @return void
     */
    public function testFactory()
    {
        $this->testFactoryHelper();
    }

    /**
     * Test that the factory behaves correctly, if cache base path is "/".
     *
     * @return void
     */
    public function testFactory2()
    {
        $this->testFactoryHelper('/');
    }
}
