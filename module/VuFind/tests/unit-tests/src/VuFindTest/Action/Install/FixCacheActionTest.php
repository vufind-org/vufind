<?php

/**
 * Install FixCacheAction test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2026.
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
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Action\Install;

use Laminas\Diactoros\Response;
use VuFind\Action\Install\FixCacheAction;
use VuFind\Cache\Manager as CacheManager;

/**
 * Install FixCacheAction test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FixCacheActionTest extends AbstractInstallActionTestCase
{
    /**
     * Test that the action renders the cache directory and running user for troubleshooting.
     *
     * @return void
     */
    public function testRendersCacheTroubleshootingDetails(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->method('getCacheDir')->willReturn('/tmp/vufind-cache');

        $action = $this->buildAction(
            FixCacheAction::class,
            [CacheManager::class => $cacheManager],
            ['System' => ['autoConfigure' => true]]
        );
        $action($this->getServerRequest(), new Response());

        $this->assertSame('/tmp/vufind-cache', $this->capturedTemplateParams['cacheDir']);
        $this->assertArrayHasKey('runningUser', $this->capturedTemplateParams);
    }

    /**
     * Test that the action renders the "disabled" template when auto-configuration is turned off.
     *
     * @return void
     */
    public function testRendersDisabledTemplateWhenAutoConfigureOff(): void
    {
        $action = $this->buildAction(FixCacheAction::class, config: ['System' => ['autoConfigure' => false]]);
        $action($this->getServerRequest(), new Response());

        $this->assertSame('install/disabled', $this->capturedTemplate);
    }
}
