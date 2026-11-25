<?php

/**
 * Unit tests for Image CAPTCHA handler factory.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Captcha;

use Gregwar\Captcha\CaptchaBuilder;
use Laminas\Session\Container;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Session\SessionContainerRegistry;
use VuFindTest\Feature\ConfigRelatedServicesTrait;

use function func_get_args;

/**
 * Unit tests for Image CAPTCHA handler factory.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
#[\PHPUnit\Framework\Attributes\RequiresPhpExtension('gd')]
#[\PHPUnit\Framework\Attributes\RequiresFunction('imagepng')]
#[\PHPUnit\Framework\Attributes\RequiresFunction('imageftbbox')]
class ImageFactoryTest extends \PHPUnit\Framework\TestCase
{
    use ConfigRelatedServicesTrait;

    /**
     * Test that the factory behaves correctly.
     *
     * @param array $captchaConfig Captcha config
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('factoryDataProvider')]
    public function testFactory(array $captchaConfig): void
    {
        // Set up mock services expected by factory:
        $container = new \VuFindTest\Container\MockContainer($this);
        $config = [
            'Captcha' => $captchaConfig,
        ];
        $container->set(ConfigManagerInterface::class, $this->getMockConfigManager(compact('config')));
        $mockRegistry = $this->createMock(SessionContainerRegistry::class);
        $mockRegistry->method('get')->willReturn(new Container('test'));
        $container->set(SessionContainerRegistry::class, $mockRegistry);

        $factory = new \VuFind\Captcha\ImageFactory();
        $fakeImage = new class () {
            /**
             * Constructor arguments.
             *
             * @var array
             */
            public $constructorArgs;

            /**
             * Constructor.
             */
            public function __construct()
            {
                $this->constructorArgs = func_get_args();
            }
        };
        $result = $factory($container, $fakeImage::class);
        $this->assertInstanceOf(CaptchaBuilder::class, $result->constructorArgs[0]);
        $this->assertInstanceOf(Container::class, $result->constructorArgs[1]);
        $this->assertSame($captchaConfig['image_width'] ?? 200, $result->constructorArgs[2]);
        $this->assertSame($captchaConfig['image_height'] ?? 50, $result->constructorArgs[3]);
    }

    /**
     * Provide data for testFactory().
     *
     * @return \Iterator
     */
    public static function factoryDataProvider(): \Iterator
    {
        yield 'defaults' => [[]];
        yield 'width & height' => [[
            'image_width' => 250,
            'image_height' => 60,
        ]];
    }
}
