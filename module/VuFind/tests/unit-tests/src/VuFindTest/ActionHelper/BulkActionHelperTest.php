<?php

/**
 * BulkActionHelper test class.
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

namespace VuFindTest\ActionHelper;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Laminas\Session\Container;
use Laminas\Session\SessionManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use VuFind\ActionHelper\BulkActionHelper;
use VuFind\ActionHelper\ContextHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\ActionHelper\UrlHelper;
use VuFind\Config\ConfigManager;
use VuFind\Export;
use VuFind\Http\RouteHelper;
use VuFind\View\FlashMessenger\FlashMessengerInterface;

/**
 * BulkActionHelper test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class BulkActionHelperTest extends TestCase
{
    /**
     * Build a helper instance, overriding specific constructor dependencies by name.
     *
     * @param array $overrides Map of parameter name => dependency instance
     *
     * @return BulkActionHelper
     */
    protected function getHelper(array $overrides = []): BulkActionHelper
    {
        return new BulkActionHelper(
            $overrides['flashMessenger'] ?? $this->createStub(FlashMessengerInterface::class),
            $overrides['sessionManager'] ?? $this->getSessionManager(),
            $overrides['routeHelper'] ?? $this->createStub(RouteHelper::class),
            $overrides['export'] ?? $this->createStub(Export::class),
            $overrides['configManager'] ?? $this->createStub(ConfigManager::class),
            $overrides['contextHelper'] ?? $this->createStub(ContextHelper::class),
            $overrides['urlHelper'] ?? $this->createStub(UrlHelper::class),
            $overrides['redirectHelper'] ?? $this->createStub(RedirectHelper::class),
        );
    }

    /**
     * Get a real session manager for use in tests.
     *
     * @return SessionManager
     */
    protected function getSessionManager(): SessionManager
    {
        return new SessionManager();
    }

    /**
     * Data provider for testGetSelectedIds().
     *
     * @return \Iterator
     */
    public static function selectedIdsProvider(): \Iterator
    {
        yield 'simple select all' => [
            ['selectAll' => '1', 'idsAll' => ['a', 'b'], 'ids' => ['a']],
            ['a', 'b'],
        ];

        yield 'simple checked ids' => [['ids' => ['x', 'y']], ['x', 'y']];

        yield 'simple nothing' => [[], []];

        yield 'advanced checked default' => [
            ['checked_default' => '1', 'non_default_ids' => '[2]', 'all_ids_global' => '[1,2,3]'],
            [1, 3],
        ];

        yield 'advanced unchecked default' => [
            ['non_default_ids' => '[2]', 'all_ids_global' => '[1,2,3]'],
            [2],
        ];

        yield 'advanced checked default, no exceptions' => [
            ['checked_default' => '1', 'non_default_ids' => '[]', 'all_ids_global' => '[1,2]'],
            [1, 2],
        ];
    }

    /**
     * Test getSelectedIds() across both the simple and the default states.
     *
     * @param array $parsedBody POST body
     * @param array $expected   Expected selected ids
     *
     * @return void
     */
    #[DataProvider('selectedIdsProvider')]
    public function testGetSelectedIds(array $parsedBody, array $expected): void
    {
        $request = (new ServerRequest('POST', 'http://localhost/'))->withParsedBody($parsedBody);
        $this->assertSame($expected, $this->getHelper()->getSelectedIds($request));
    }

    /**
     * Data provider for testRedirectToSourceSetsFlashMessage().
     *
     * @return \Iterator
     */
    public static function flashNamespaceProvider(): \Iterator
    {
        yield 'error' => ['error', 'addErrorMessage'];

        yield 'info' => ['info', 'addInfoMessage'];

        yield 'success' => ['success', 'addSuccessMessage'];

        yield 'warning' => ['warning', 'addWarningMessage'];
    }

    /**
     * Test that redirectToSource() routes a flash message to the correct messenger method for its namespace.
     *
     * @param string $namespace      Flash namespace passed to redirectToSource()
     * @param string $expectedMethod Messenger method expected to receive the message
     *
     * @return void
     */
    #[DataProvider('flashNamespaceProvider')]
    public function testRedirectToSourceSetsFlashMessage(string $namespace, string $expectedMethod): void
    {
        $flashMessenger = $this->createMock(FlashMessengerInterface::class);
        $flashMessenger->expects($this->once())->method($expectedMethod)->with('a message');

        $helper = $this->getHelper(compact('flashMessenger'));
        $helper->redirectToSource(
            new ServerRequest('POST', 'http://localhost/'),
            new Response(),
            $namespace,
            'a message'
        );
    }

    /**
     * Test that redirectToSource() throws for an unrecognized flash namespace.
     *
     * @return void
     */
    public function testRedirectToSourceThrowsOnUnknownFlashNamespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown flash message namespace 'unrecognized'");
        $this->getHelper()->redirectToSource(
            new ServerRequest('POST', 'http://localhost/'),
            new Response(),
            'unrecognized',
            'a message'
        );
    }

    /**
     * Test that redirectToSource() returns null (no redirect) when in a lightbox and redirection is not forced.
     *
     * @return void
     */
    public function testRedirectToSourceReturnsNullInLightbox(): void
    {
        $contextHelper = $this->createMock(ContextHelper::class);
        $contextHelper->method('inLightbox')->willReturn(true);
        $redirectHelper = $this->createMock(RedirectHelper::class);
        $redirectHelper->expects($this->never())->method('redirectToUrl');

        $helper = $this->getHelper(compact('contextHelper', 'redirectHelper'));
        $result = $helper->redirectToSource(
            new ServerRequest('POST', 'http://localhost/'),
            new Response()
        );
        $this->assertNotInstanceOf(ResponseInterface::class, $result);
    }

    /**
     * Data provider for testRedirectToSourceUsesSessionUrl().
     *
     * @return \Iterator
     */
    public static function redirectInLightboxProvider(): \Iterator
    {
        yield 'not in lightbox' => [false];

        yield 'in lightbox, redirect forced' => [true];
    }

    /**
     * Test that redirectToSource() redirects to the source URL stored in the followup session when present.
     *
     * @param bool $redirectInLightbox Whether we are in a lightbox and forcing a redirect anyway
     *
     * @return void
     */
    #[DataProvider('redirectInLightboxProvider')]
    public function testRedirectToSourceUsesSessionUrl(bool $redirectInLightbox): void
    {
        $sessionManager = $this->getSessionManager();
        $session = new Container('cart_followup', $sessionManager);
        $session->url = 'http://localhost/source';

        $contextHelper = $this->createMock(ContextHelper::class);
        $contextHelper->method('inLightbox')->willReturn($redirectInLightbox);

        $expectedResponse = new Response();
        $redirectHelper = $this->createMock(RedirectHelper::class);
        $redirectHelper->expects($this->once())->method('redirectToUrl')
            ->with($this->isInstanceOf(ResponseInterface::class), 'http://localhost/source')
            ->willReturn($expectedResponse);

        $routeHelper = $this->createMock(RouteHelper::class);
        $routeHelper->expects($this->never())->method('getUrlFromRoute');

        $helper = $this->getHelper(compact('sessionManager', 'contextHelper', 'redirectHelper', 'routeHelper'));
        $result = $helper->redirectToSource(
            new ServerRequest('POST', 'http://localhost/'),
            new Response(),
            redirectInLightbox: $redirectInLightbox
        );
        $this->assertSame($expectedResponse, $result);

        $sessionAfter = new Container('cart_followup', $sessionManager);
        $this->assertFalse(isset($sessionAfter->url));
    }

    /**
     * Test that redirectToSource() falls back to the MyResearch home route when no session URL is present.
     *
     * @return void
     */
    public function testRedirectToSourceFallsBackToMyResearch(): void
    {
        $contextHelper = $this->createMock(ContextHelper::class);
        $contextHelper->method('inLightbox')->willReturn(false);

        $routeHelper = $this->createMock(RouteHelper::class);
        $routeHelper->expects($this->once())->method('getUrlFromRoute')
            ->with('myresearch-home')
            ->willReturn('http://localhost/myresearch');

        $expectedResponse = new Response();
        $redirectHelper = $this->createMock(RedirectHelper::class);
        $redirectHelper->expects($this->once())->method('redirectToUrl')
            ->with($this->isInstanceOf(ResponseInterface::class), 'http://localhost/myresearch')
            ->willReturn($expectedResponse);

        $helper = $this->getHelper(compact('contextHelper', 'routeHelper', 'redirectHelper'));
        $result = $helper->redirectToSource(new ServerRequest('POST', 'http://localhost/'), new Response());
        $this->assertSame($expectedResponse, $result);
    }
}
