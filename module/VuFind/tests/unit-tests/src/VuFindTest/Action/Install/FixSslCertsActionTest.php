<?php

/**
 * Install FixSslCertsAction test class.
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
use Psr\Http\Message\ResponseInterface;
use VuFind\Action\Install\FixSslCertsAction;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFindHttp\HttpService;

/**
 * Install FixSslCertsAction test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FixSslCertsActionTest extends AbstractInstallActionTestCase
{
    /**
     * Get an HTTP service whose get() either succeeds or throws a VuFindHttp runtime exception.
     *
     * @param bool $succeeds Whether the HTTPS request succeeds
     *
     * @return HttpService
     */
    protected function getHttpService(bool $succeeds): HttpService
    {
        $httpService = $this->createMock(HttpService::class);
        if ($succeeds) {
            $httpService->method('get')->willReturn($this->createStub(\Laminas\Http\Response::class));
        } else {
            $httpService->method('get')
                ->willThrowException(new \VuFindHttp\Exception\RuntimeException('SSL failure'));
        }
        return $httpService;
    }

    /**
     * Test that a working SSL connection reports success and redirects to the install home page.
     *
     * @return void
     */
    public function testRedirectsHomeWhenSslWorks(): void
    {
        $flashMessagesHelper = $this->createMock(FlashMessagesHelper::class);
        $flashMessagesHelper->expects($this->once())->method('addInfoMessage')->with('SSL configuration fixed.');
        $expectedResponse = new Response();
        $redirectHelper = $this->createMock(RedirectHelper::class);
        $redirectHelper->expects($this->once())->method('redirectToRoute')
            ->with($this->isInstanceOf(ResponseInterface::class), 'install-home')->willReturn($expectedResponse);

        $action = $this->buildAction(
            FixSslCertsAction::class,
            [HttpService::class => $this->getHttpService(true)],
            ['System' => ['autoConfigure' => true]],
            [FlashMessagesHelper::class => $flashMessagesHelper, RedirectHelper::class => $redirectHelper]
        );
        $this->assertSame($expectedResponse, $action($this->getServerRequest(), new Response()));
    }

    /**
     * Test that a failed SSL connection tries the next configuration and increments
     * the "try" counter.
     *
     * @return void
     */
    public function testAppliesNextConfigAndRetriesWhenSslFails(): void
    {
        $expectedResponse = new Response();
        $redirectHelper = $this->createMock(RedirectHelper::class);
        $redirectHelper->expects($this->once())->method('redirectToRoute')
            ->with($this->isInstanceOf(ResponseInterface::class), 'install-fixsslcerts', [], ['try' => 1])
            ->willReturn($expectedResponse);

        $action = $this->buildAction(
            FixSslCertsAction::class,
            [HttpService::class => $this->getHttpService(false)],
            ['System' => ['autoConfigure' => true]],
            [RedirectHelper::class => $redirectHelper]
        );
        $request = $this->getServerRequest(queryParams: ['try' => 0]);
        $this->assertSame($expectedResponse, $action($request, new Response()));
    }

    /**
     * Test that once every candidate configuration has been exhausted, the manual-instructions template is rendered.
     *
     * @return void
     */
    public function testRendersInstructionsWhenAllConfigsExhausted(): void
    {
        $action = $this->buildAction(
            FixSslCertsAction::class,
            [HttpService::class => $this->getHttpService(false)],
            ['System' => ['autoConfigure' => true]]
        );
        $action($this->getServerRequest(queryParams: ['try' => 3]), new Response());

        $this->assertNull($this->capturedTemplate);
        $this->assertSame([], $this->capturedTemplateParams);
    }
}
