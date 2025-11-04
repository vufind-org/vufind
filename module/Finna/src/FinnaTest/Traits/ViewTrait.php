<?php

/**
 * Trait for tests involving Laminas Views.
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
 * @link     https://vufind.org Main Page
 */

namespace FinnaTest\Traits;

use Finna\View\Helper\Root\CleanHtmlFactory;
use FinnaTest\Container\MockContainer;
use stdClass;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Config\Config;
use VuFind\Config\PluginManager as ConfigPluginManager;
use VuFind\View\Helper\Root\CleanHtml;

/**
 * Trait for tests involving Laminas Views.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait ViewTrait
{
    /**
     * Get a CleanHtml helper
     *
     * @param array $customElements Custom elements
     *
     * @return CleanHtml
     */
    protected function getCleanHtml(array $customElements): CleanHtml
    {
        $container = new MockContainer($this);
        $container->add(
            'config',
            [
                'vufind' => [
                    'plugin_managers' => [
                        'view_customelement' => [
                            'aliases' => $customElements,
                        ],
                    ],
                ],
            ]
        );

        $configPluginManager = new MockContainer($this);
        $configPluginManager->add('config', new Config());
        $container->add(ConfigPluginManager::class, $configPluginManager);

        $cacheOptions = $this->getMockBuilder(stdClass::class)
            ->addMethods(['getCacheDir'])
            ->getMock();
        $cacheOptions->expects($this->any())
            ->method('getCacheDir')
            ->willReturn('');
        $cache = $this->getMockBuilder(stdClass::class)
            ->addMethods(['getOptions'])
            ->getMock();
        $cache->expects($this->any())
            ->method('getOptions')
            ->willReturn($cacheOptions);
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects($this->any())
            ->method('getCache')
            ->willReturn($cache);
        $container->add(CacheManager::class, $cacheManager);

        $factory = new CleanHtmlFactory();
        return $factory($container, CleanHtml::class);
    }
}
