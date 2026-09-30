<?php

/**
 * Install DoneAction test class.
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
use VuFind\Action\Install\DoneAction;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\Config\ConfigManagerInterface;

/**
 * Install DoneAction test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class DoneActionTest extends AbstractInstallActionTestCase
{
    /**
     * Test that a successful configuration change renders the "done" template with the local config directory.
     *
     * @return void
     */
    public function testDisablesAutoConfigureAndRendersDoneTemplate(): void
    {
        $action = $this->buildAction(DoneAction::class, config: ['System' => ['autoConfigure' => true]]);
        $action($this->getServerRequest(), new Response());

        $this->assertNull($this->capturedTemplate);
        $this->assertArrayHasKey('configDir', $this->capturedTemplateParams);
    }

    /**
     * Test that a failure to write the configuration forwards to the "fix basic config" action.
     *
     * @return void
     */
    public function testForwardsToFixBasicConfigWhenConfigWriteFails(): void
    {
        $configManager = $this->createMock(ConfigManagerInterface::class);
        $configManager->method('getConfigArray')->willReturn([]);
        $configManager->method('writeConfig')->willThrowException(new \Exception('cannot write'));

        $expectedResponse = new Response();
        $forwardHelper = $this->createMock(ForwardHelper::class);
        $forwardHelper->expects($this->once())->method('forwardTo')
            ->with($this->anything(), $this->anything(), 'Install/FixBasicConfig')
            ->willReturn($expectedResponse);

        $action = $this->buildAction(
            DoneAction::class,
            [ConfigManagerInterface::class => $configManager],
            ['System' => ['autoConfigure' => true]],
            [ForwardHelper::class => $forwardHelper]
        );
        $this->assertSame($expectedResponse, $action($this->getServerRequest(), new Response()));
    }
}
