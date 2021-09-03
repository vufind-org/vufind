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
use League\CommonMark\MarkdownConverterInterface;
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

        $result = $this->getMarkdownEnvironmentConfig($defaultConfig);
        $this->assertEquals($defaultEnvironment, $result);

        $result = $this->getMarkdownEnvironmentConfig($customConfig);
        $this->assertEquals($customEnvironment, $result);
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
                    'League\CommonMark\Extension\CommonMarkCoreExtension',
                    'League\CommonMark\Extension\Attributes\AttributesExtension',
                    'League\CommonMark\Extension\ExternalLink\ExternalLinkExtension',
                    'League\CommonMark\Extension\Table\TableExtension',
                ],
            ],
            [ // Test default extension set
                'config' => [],
                'expected' => [
                    'League\CommonMark\Extension\CommonMarkCoreExtension',
                    'League\CommonMark\Extension\Autolink\AutolinkExtension',
                    'League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension',
                    'League\CommonMark\Extension\Strikethrough\StrikethroughExtension',
                    'League\CommonMark\Extension\Table\TableExtension',
                    'League\CommonMark\Extension\TaskList\TaskListExtension',
                ],
            ],
            [ // Test empty extensions set
                'config' => [
                    'Markdown' => [
                        'extensions' => '',
                    ],
                ],
                'expected' => [
                    'League\CommonMark\Extension\CommonMarkCoreExtension',
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
     * @return array
     */
    protected function getMarkdownEnvironmentConfig(array $config): array
    {
        $markdown = $this->getMarkdownConverter($config);
        return $markdown->getEnvironment()->getConfig();
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
     * @return MarkdownConverterInterface
     * @throws \Interop\Container\Exception\ContainerException
     * @throws \Throwable
     */
    protected function getMarkdownConverter(array $config): MarkdownConverterInterface
    {
        $config = new Config($config);
        $container = new \VuFindTest\Container\MockContainer($this);
        $configManager = $container
            ->createMock(\VuFind\Config\PluginManager::class, ['get']);
        $configManager->expects($this->any())->method('get')
            ->will($this->returnValue($config));
        $container->set(\VuFind\Config\PluginManager::class, $configManager);
        $markdownFactory = new MarkdownFactory();
        $markdown = $markdownFactory(
            $container,
            \League\CommonMark\MarkdownConverterInterface::class
        );
        return $markdown;
    }
}
