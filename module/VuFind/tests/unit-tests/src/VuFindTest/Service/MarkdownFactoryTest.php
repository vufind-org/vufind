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
use VuFind\Service\MarkdownFactory;
use VuFindTest\Unit\MockContainerTest;

/**
 * MarkdownFactory Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MarkdownFactoryTest extends MockContainerTest
{
    /**
     * Test to ensure the markdown factory is using right config for markdown
     * service
     *
     * @return void
     * @throws \Interop\Container\Exception\ContainerException
     */
    public function testConfig()
    {
        $defaultConfig = [];
        $defaultEnvironment = [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'enable_em' => true,
            'enable_strong' => true,
            'use_asterisk' => true,
            'use_underscore' => true,
            'unordered_list_markers' => ['-', '*', '+'],
            'max_nesting_level' => \INF,
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
        ];

        $result = $this->getMarkdownEnvironmentConfig($defaultConfig);
        $this->assertEquals($defaultEnvironment, $result);

        $result = $this->getMarkdownEnvironmentConfig($customConfig);
        $this->assertEquals($customEnvironment, $result);
    }

    /**
     * Return config of created markdown service environment
     *
     * @param $config
     *
     * @return mixed
     * @throws \Interop\Container\Exception\ContainerException
     */
    protected function getMarkdownEnvironmentConfig($config)
    {
        $config = new Config($config);
        $configManager = $this->container->createMock(
            \VuFind\Config\PluginManager::class, ['get']
        );
        $configManager->expects($this->any())->method('get')
            ->will($this->returnValue($config));
        $this->container->set(\VuFind\Config\PluginManager::class, $configManager);
        $markdownFactory = new MarkdownFactory();
        $markdown = $markdownFactory->__invoke(
            $this->container, \League\CommonMark\MarkdownConverterInterface::class
        );
        return $markdown->getEnvironment()->getConfig();
    }
}
