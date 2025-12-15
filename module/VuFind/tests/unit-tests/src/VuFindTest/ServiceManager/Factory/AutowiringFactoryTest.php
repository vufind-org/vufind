<?php

/**
 * AutowiringFactory Test Class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

declare(strict_types=1);

namespace VuFindTest\ServiceManager\Factory;

use Laminas\View\HelperPluginManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Container\ContainerInterface;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\YamlReader;
use VuFind\ILS\Connection;
use VuFind\ServiceManager\Factory\AutowiringFactory;
use VuFind\View\Helper\Root\Url;
use VuFindTest\Container\MockContainer;
use VuFindTest\Container\MockViewHelperContainer;
use VuFindTest\Feature\ConfigRelatedServicesTrait;
use VuFindTest\ServiceManager\Factory\TestHarness\AutowiredClass;
use VuFindTest\ServiceManager\Factory\TestHarness\AutowiredClassEmptyConstructor;
use VuFindTest\ServiceManager\Factory\TestHarness\AutowiredClassNoConstructor;
use VuFindTest\ServiceManager\Factory\TestHarness\InvalidAutowiredClass;
use VuFindTest\ServiceManager\Factory\TestHarness\InvalidAutowiredClass2;
use VuFindTest\ServiceManager\Factory\TestHarness\InvalidConfigType;

/**
 * AutowiringFactory Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class AutowiringFactoryTest extends \PHPUnit\Framework\TestCase
{
    use ConfigRelatedServicesTrait;

    /**
     * Data provider for testAutoWiring
     *
     * @return array
     */
    public static function autowiringProvider(): array
    {
        return [
            'autowired class' => [AutowiredClass::class],
            'autowired class with no constructor' => [AutowiredClassNoConstructor::class],
            'autowired class with empty constructor' => [AutowiredClassEmptyConstructor::class],
            'invalid class' => [InvalidAutowiredClass::class, 'Unable to autowire parameter config of type array'],
            'second invalid class'
                => [InvalidAutowiredClass2::class, 'Unable to resolve type of parameter ilsConnection'],
            'invalid config type' => [InvalidConfigType::class, 'Invalid configType yummy'],
        ];
    }

    /**
     * Test autowiring.
     *
     * @param string  $className         Class name
     * @param ?string $expectedException Expected exception, if any
     *
     * @return void
     */
    #[DataProvider('autowiringProvider')]
    public function testAutoWiring(string $className, ?string $expectedException = null): void
    {
        if ($expectedException) {
            $this->expectExceptionMessage($expectedException);
        }
        $this->assertInstanceOf(
            $className,
            (new AutowiringFactory())($this->getContainer(), $className)
        );
    }

    /**
     * Test factory options (invalid).
     *
     * @return void
     */
    public function testFactoryOptions(): void
    {
        $this->expectExceptionMessage('Unexpected options passed to factory.');
        (new AutowiringFactory())($this->getContainer(), AutowiredClass::class, ['foo' => 'bar']);
    }

    /**
     * Get mock container
     *
     * @return ContainerInterface
     */
    protected function getContainer(): ContainerInterface
    {
        $container = new MockContainer($this);
        $container->set(
            ConfigManagerInterface::class,
            $this->getMockConfigManager(['config' => ['Foo' => 'bar']])
        );
        $yamlReader = $this->createMock(YamlReader::class);
        $yamlReader
            ->method('get')
            ->with('config2.yaml')
            ->willReturn(['YAML' => ['foo' => 'bar']]);
        $container->set(YamlReader::class, $yamlReader);
        $plugins = new MockViewHelperContainer($this);
        $plugins->set(Url::class, $this->createMock(Url::class));
        $container->set(HelperPluginManager::class, $plugins);
        $container->set(AuthManager::class, $this->createMock(AuthManager::class));
        $container->set(Connection::class, $this->createMock(Connection::class));
        return $container;
    }
}
