<?php

/**
 * FormatBased cover loader factory unit tests.
 *
 * PHP version 8
 *
 * Copyright (C) Universitätsbibliothek Mannheim 2026.
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
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Content\Covers;

use VuFind\Config\ConfigManagerInterface;
use VuFind\Content\Covers\FormatBased;
use VuFind\Content\Covers\FormatBasedFactory;
use VuFind\Record\Loader as RecordLoader;

use function defined;

/**
 * Unit tests for FormatBased cover loader factory.
 *
 * @category VuFind
 * @package  Tests
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class FormatBasedFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Read a protected property of the created service.
     *
     * @param FormatBased $service Service to inspect
     * @param string      $name    Property name
     *
     * @return mixed
     */
    protected function getProperty(FormatBased $service, string $name)
    {
        $property = new \ReflectionProperty(FormatBased::class, $name);
        return $property->getValue($service);
    }

    /**
     * Create the service using the given config array.
     *
     * @param array $config Config array as returned by ConfigManagerInterface
     *
     * @return FormatBased
     */
    protected function createService(array $config): FormatBased
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('getConfigArray')->with('config')->willReturn($config);
        $recordLoader = $this->createMock(RecordLoader::class);
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static function (string $id) use ($configManager, $recordLoader) {
                return $id === ConfigManagerInterface::class
                    ? $configManager
                    : $recordLoader;
            }
        );
        $factory = new FormatBasedFactory();
        return $factory($container, FormatBased::class);
    }

    /**
     * Test that image_dir defaults to the bootstrap5 theme directory.
     *
     * @return void
     */
    public function testDefaultImageDir(): void
    {
        $service = $this->createService([]);
        $expected = defined('APPLICATION_PATH')
            ? APPLICATION_PATH . '/themes/bootstrap5/images/format-covers'
            : '';
        $this->assertSame($expected, $this->getProperty($service, 'imageDir'));
    }

    /**
     * Test that an explicit image_dir setting is used.
     *
     * @return void
     */
    public function testExplicitImageDir(): void
    {
        $service = $this->createService(
            ['FormatBasedCovers' => ['image_dir' => ' /tmp/images ']]
        );
        $this->assertSame('/tmp/images', $this->getProperty($service, 'imageDir'));
    }

    /**
     * Test that an explicit empty image_dir falls back to the default.
     *
     * @return void
     */
    public function testEmptyImageDirFallsBackToDefault(): void
    {
        $service = $this->createService(
            ['FormatBasedCovers' => ['image_dir' => '  ']]
        );
        $expected = defined('APPLICATION_PATH')
            ? APPLICATION_PATH . '/themes/bootstrap5/images/format-covers'
            : '';
        $this->assertSame($expected, $this->getProperty($service, 'imageDir'));
    }

    /**
     * Test that the default setting and format mappings are parsed.
     *
     * @return void
     */
    public function testDefaultAndMappings(): void
    {
        $service = $this->createService(
            [
                'FormatBasedCovers' => [
                    'default' => '/tmp/default.png',
                    'e-Book' => '/tmp/ebook.png',
                    'Journal' => '/tmp/journal.png',
                ],
            ]
        );
        $this->assertSame('/tmp/default.png', $this->getProperty($service, 'default'));
        $this->assertSame(
            ['e-Book' => '/tmp/ebook.png', 'Journal' => '/tmp/journal.png'],
            $this->getProperty($service, 'mapping')
        );
    }
}
