<?php

/**
 * MarkdownFactory Test Class
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2020.
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
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Service;

use Laminas\Config\Config;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use League\CommonMark\ConverterInterface;
use VuFind\Service\MarkdownFactory;

/**
 * MarkdownFactory Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MarkdownFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test to ensure the markdown factory is using right config for markdown
     * service
     *
     * @return void
     */
    public function testConfig(): void
    {
        $defaultConfig = [];
        $defaultEnvironment = [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => \PHP_INT_MAX,
            'commonmark' => [
                'enable_em' => true,
                'enable_strong' => true,
                'use_asterisk' => true,
                'use_underscore' => true,
                'unordered_list_markers' => ['-', '*', '+'],
            ],
            'renderer' => [
                'block_separator' => "\n",
                'inner_separator' => "\n",
                'soft_break' => "\n",
            ],
        ];

        $customConfig = [
            'Markdown' => [
                'html_input' => 'escape',
                'allow_unsafe_links' => true,
                'enable_em' => false,
                'enable_strong' => false,
                'use_asterisk' => false,
                'use_underscore' => false,
                'unordered_list_markers' => [';', '^'],
                'max_nesting_level' => 10,
                'renderer' => [
                    'block_separator' => "\r\n",
                    'inner_separator' => "\r\n",
                    'soft_break' => "\r\n",
                ],
            ],
        ];
        $customEnvironment = [
            'html_input' => 'escape',
            'allow_unsafe_links' => true,
            'max_nesting_level' => 10,
            'commonmark' => [
                'enable_em' => false,
                'enable_strong' => false,
                'use_asterisk' => false,
                'use_underscore' => false,
                'unordered_list_markers' => [';', '^'],
            ],
            'renderer' => [
                'block_separator' => "\r\n",
                'inner_separator' => "\r\n",
                'soft_break' => "\r\n",
            ],
        ];

        $customConfig2 = [
            'Markdown' => [
                'html_input' => 'escape',
                'allow_unsafe_links' => true,
                'enable_em' => false,
                'enable_strong' => false,
                'use_asterisk' => false,
                'use_underscore' => false,
                'unordered_list_markers' => ['`', '~'],
                'max_nesting_level' => 10,
                'extensions' => 'Table,VuFindTest\Markdown\ExampleExtension',
                'renderer' => [
                    'block_separator' => "\r\n",
                    'inner_separator' => "\r\n",
                    'soft_break' => "\r\n",
                ],
            ],
            'CommonMarkCore' => [
                'enable_em' => false,
                'enable_strong' => false,
                'use_asterisk' => false,
                'use_underscore' => false,
                'unordered_list_markers' => [';', '^'],
            ],
            'Table' => [
                'wrap' => [
                    'enabled' => true,
                    'tag' => 'div',
                    'attributes' => 'class:table-responsive,title:table',
                ],
            ],
            'VuFindTest\Markdown\ExampleExtension' => [
                'config_key' => 'example',
                'example' => 'example',
            ],
        ];
        $customEnvironment2 = [
            'html_input' => 'escape',
            'allow_unsafe_links' => true,
            'max_nesting_level' => 10,
            'commonmark' => [
                'enable_em' => false,
                'enable_strong' => false,
                'use_asterisk' => false,
                'use_underscore' => false,
                'unordered_list_markers' => [';', '^'],
            ],
            'table' => [
                'wrap' => [
                    'enabled' => true,
                    'tag' => 'div',
                    'attributes' => [
                        'class' => 'table-responsive',
                        'title' => 'table',
                    ],
                ],
            ],
            'example' => [
                'example' => 'example',
            ],
            'renderer' => [
                'block_separator' => "\r\n",
                'inner_separator' => "\r\n",
                'soft_break' => "\r\n",
            ],
        ];

        $result = $this->getMarkdownEnvironmentConfig($defaultConfig);
        foreach ($defaultEnvironment as $option => $value) {
            $this->assertEquals($value, $result->get($option), 'Test default option: ' . $option);
        }

        $result = $this->getMarkdownEnvironmentConfig($customConfig);
        foreach ($customEnvironment as $option => $value) {
            $this->assertEquals($value, $result->get($option), 'Test custom option: ' . $option);
        }

        $result = $this->getMarkdownEnvironmentConfig($customConfig2);
        foreach ($customEnvironment2 as $option => $value) {
            $this->assertEquals($value, $result->get($option), 'Test custom option: ' . $option);
        }
    }

    /**
     * Test that extensions are added based on configuration
     *
     * @return void
     */
    public function testExtensions(): void
    {
        $tests = [
            [ // Test custom extension set
                'config' => [
                    'Markdown' => [
                        'extensions' => 'Attributes,ExternalLink,Table',
                    ],
                ],
                'expected' => [
                    \League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension::class,
                    \League\CommonMark\Extension\Attributes\AttributesExtension::class,
                    \League\CommonMark\Extension\ExternalLink\ExternalLinkExtension::class,
                    \League\CommonMark\Extension\Table\TableExtension::class,
                ],
            ],
            [ // Test default extension set
                'config' => [],
                'expected' => [
                    \League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension::class,
                    \League\CommonMark\Extension\Autolink\AutolinkExtension::class,
                    \League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension::class,
                    \League\CommonMark\Extension\Strikethrough\StrikethroughExtension::class,
                    \League\CommonMark\Extension\Table\TableExtension::class,
                    \League\CommonMark\Extension\TaskList\TaskListExtension::class,
                ],
            ],
            [ // Test empty extensions set
                'config' => [
                    'Markdown' => [
                        'extensions' => '',
                    ],
                ],
                'expected' => [
                    \League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension::class,
                ],
            ],
            [ // Test custom extension
                'config' => [
                    'Markdown' => [
                        'extensions' => 'VuFindTest\Markdown\ExampleExtension',
                    ],
                ],
                'expected' => [
                    \League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension::class,
                    \VuFindTest\Markdown\ExampleExtension::class,
                ],
            ],
            [ // Test not valid extensions set
                'config' => [
                    'Markdown' => [
                        'extensions' => 'NotValidExtension',
                    ],
                ],
                'exception' => ServiceNotCreatedException::class,
            ],
        ];
        foreach ($tests as $test) {
            if (isset($test['exception'])) {
                $this->expectException($test['exception']);
            }
            $result = $this->getMarkdownEnvironmentExtensions($test['config']);
            $result = array_map(function ($extension) {
                return get_class($extension);
            }, $result);
            $this->assertEquals($test['expected'], $result);
        }
    }

    /**
     * Return config of created markdown service environment
     *
     * @param array $config Configuration settings
     *
     * @return \League\Config\ReadOnlyConfiguration
     */
    protected function getMarkdownEnvironmentConfig(array $config): \League\Config\ReadOnlyConfiguration
    {
        $markdown = $this->getMarkdownConverter($config);
        return $markdown->getEnvironment()->getConfiguration();
    }

    /**
     * Return config of created markdown service environment
     *
     * @param array $config Configuration settings
     *
     * @return array
     */
    protected function getMarkdownEnvironmentExtensions(array $config): array
    {
        $markdown = $this->getMarkdownConverter($config);
        return $markdown->getEnvironment()->getExtensions();
    }

    /**
     * Create markdown converter
     *
     * @param array $config
     *
     * @return ConverterInterface
     * @throws \Interop\Container\Exception\ContainerException
     * @throws \Throwable
     */
    protected function getMarkdownConverter(array $config): ConverterInterface
    {
        $disabledServices = [
            \League\CommonMark\Extension\Autolink\AutolinkExtension::class,
            \League\CommonMark\Extension\Attributes\AttributesExtension::class,
            \League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension::class,
            \League\CommonMark\Extension\ExternalLink\ExternalLinkExtension::class,
            \League\CommonMark\Extension\Strikethrough\StrikethroughExtension::class,
            \League\CommonMark\Extension\Table\TableExtension::class,
            \League\CommonMark\Extension\TaskList\TaskListExtension::class,
        ];
        $config = new Config($config);
        $container = new \VuFindTest\Container\MockContainer($this);
        foreach ($disabledServices as $service) {
            $container->disable($service);
        }
        $container->set(
            \VuFindTest\Markdown\ExampleExtension::class,
            new \VuFindTest\Markdown\ExampleExtension()
        );
        $configManager = $container
            ->createMock(\VuFind\Config\PluginManager::class, ['get']);
        $configManager->expects($this->any())->method('get')
            ->will($this->returnValue($config));
        $container->set(\VuFind\Config\PluginManager::class, $configManager);
        $markdownFactory = new MarkdownFactory();
        return $markdownFactory(
            $container,
            \League\CommonMark\ConverterInterface::class
        );
    }
}
