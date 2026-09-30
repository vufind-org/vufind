<?php

/**
 * Install FixDependenciesAction test class.
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
use VuFind\Action\Install\FixDependenciesAction;
use VuFind\ActionHelper\FlashMessagesHelper;

/**
 * Install FixDependenciesAction test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FixDependenciesActionTest extends AbstractInstallActionTestCase
{
    /**
     * Test that a healthy environment reports no problems.
     *
     * @return void
     */
    public function testReportsNoProblemsWhenEnvironmentIsHealthy(): void
    {
        $action = $this->buildActionMock(
            FixDependenciesAction::class,
            ['phpVersionIsNewEnough', 'getMissingExtensions'],
            ['System' => ['autoConfigure' => true]]
        );
        $action->method('phpVersionIsNewEnough')->willReturn(true);
        $action->method('getMissingExtensions')->willReturn([]);
        $action($this->getServerRequest(), new Response());

        $this->assertSame(0, $this->capturedTemplateParams['problems']);
        $this->assertSame([], $this->capturedTemplateParams['missingExtensions']);
    }

    /**
     * Test that an outdated PHP version and missing extensions are both counted and the PHP problem is flashed.
     *
     * @return void
     */
    public function testReportsProblemsAndFlashesPhpVersionError(): void
    {
        $flashMessagesHelper = $this->createMock(FlashMessagesHelper::class);
        $flashMessagesHelper->expects($this->once())->method('addErrorMessage')
            ->with($this->stringContains('requires PHP version 8.2.0'));

        $action = $this->buildActionMock(
            FixDependenciesAction::class,
            ['phpVersionIsNewEnough', 'getMissingExtensions', 'getMinimalPhpVersion'],
            ['System' => ['autoConfigure' => true]],
            [FlashMessagesHelper::class => $flashMessagesHelper]
        );
        $action->method('phpVersionIsNewEnough')->willReturn(false);
        $action->method('getMinimalPhpVersion')->willReturn('8.2.0');
        $action->method('getMissingExtensions')->willReturn(['GD']);
        $action($this->getServerRequest(), new Response());

        $this->assertSame(2, $this->capturedTemplateParams['problems']);
        $this->assertSame(['GD'], $this->capturedTemplateParams['missingExtensions']);
    }
}
