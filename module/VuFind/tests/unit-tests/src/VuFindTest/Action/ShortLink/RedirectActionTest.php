<?php

/**
 * ShortLink RedirectAction test class.
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

namespace VuFindTest\Action\ShortLink;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Laminas\Router\RouteMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\ShortLink\RedirectAction;
use VuFind\ActionHelper\PermissionHelper;
use VuFind\ActionHelper\PluginManager as HelperPluginManager;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Exception\BadConfig;
use VuFind\Http\RouteHelper;
use VuFind\Session\Settings as SessionSettings;
use VuFind\UrlShortener\UrlShortenerInterface;
use VuFind\View\Renderer\TemplateRendererInterface;

/**
 * ShortLink RedirectAction test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RedirectActionTest extends TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Build a RedirectAction with the given redirect method, overriding only the collaborators a test cares about.
     *
     * @param string                     $redirectMethod Configured redirect method
     * @param ?UrlShortenerInterface     $shortener      URL shortener (defaults to a stub)
     * @param ?RedirectHelper            $redirectHelper Redirect helper for HelperPluginManager to return
     * @param ?TemplateRendererInterface $renderer       Template renderer (defaults to a stub)
     *
     * @return RedirectAction
     */
    protected function buildAction(
        string $redirectMethod,
        ?UrlShortenerInterface $shortener = null,
        ?RedirectHelper $redirectHelper = null,
        ?TemplateRendererInterface $renderer = null
    ): RedirectAction {
        $action = new RedirectAction(
            $shortener ?? $this->createStub(UrlShortenerInterface::class),
            $redirectMethod
        );

        $permissionHelper = $this->createMock(PermissionHelper::class);
        $permissionHelper->method('getPermissionBehaviorConfig')->willReturn([]);
        $redirectHelper ??= $this->createStub(RedirectHelper::class);
        $manager = $this->createMock(HelperPluginManager::class);
        $manager->method('get')->willReturnCallback(
            fn ($name) => match ($name) {
                PermissionHelper::class => $permissionHelper,
                RedirectHelper::class => $redirectHelper,
                default => throw new \Exception("Unexpected helper requested: $name"),
            }
        );
        $action->setHelperPluginManager($manager);
        $action->setRouteHelper($this->createStub(RouteHelper::class));
        $action->setSessionSettings($this->createStub(SessionSettings::class));
        $action->setTemplateRenderer($renderer ?? $this->createStub(TemplateRendererInterface::class));
        return $action;
    }

    /**
     * Build a request whose route match carries the given short-link id (or none).
     *
     * @param ?string $id Short-link id
     *
     * @return ServerRequestInterface
     */
    protected function requestWithId(?string $id): ServerRequestInterface
    {
        $routeMatch = new RouteMatch(null === $id ? [] : ['id' => $id]);
        return (new ServerRequest())->withAttribute('route-match', $routeMatch);
    }

    /**
     * Test that a resolved URL shorter than the configured threshold redirects via an HTTP header.
     *
     * @return void
     */
    public function testThresholdShortUrlRedirectsViaHttp(): void
    {
        $shortener = $this->createMock(UrlShortenerInterface::class);
        $shortener->method('resolve')->with('abc')->willReturn('https://vufind.org');
        $expectedResponse = new Response();
        $redirectHelper = $this->createMock(RedirectHelper::class);
        $redirectHelper->expects($this->once())->method('redirectToUrl')
            ->with($this->isInstanceOf(ResponseInterface::class), 'https://vufind.org')
            ->willReturn($expectedResponse);

        $action = $this->buildAction('threshold:1000', shortener: $shortener, redirectHelper: $redirectHelper);
        $this->assertSame($expectedResponse, $action($this->requestWithId('abc'), new Response()));
    }

    /**
     * Test that a resolved URL longer than the configured threshold redirects via an HTML template.
     *
     * @return void
     */
    public function testThresholdLongUrlRedirectsViaHtml(): void
    {
        $longUrl = 'https://vufind.org/' . str_repeat('a', 50);
        $shortener = $this->createMock(UrlShortenerInterface::class);
        $shortener->method('resolve')->willReturn($longUrl);
        $captured = [];
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->method('renderTemplate')->willReturnCallback(
            function ($request, $response, ?string $template, array $params) use (&$captured) {
                $captured = $params;
                return $response;
            }
        );

        $action = $this->buildAction('threshold:10', shortener: $shortener, renderer: $renderer);
        $action($this->requestWithId('abc'), new Response());

        $this->assertSame(['redirectTarget' => $longUrl, 'redirectDelay' => 3], $captured);
    }

    /**
     * Test that an explicitly configured "http" method redirects via an HTTP header.
     *
     * @return void
     */
    public function testExplicitHttpMethodRedirectsViaHttp(): void
    {
        $shortener = $this->createMock(UrlShortenerInterface::class);
        $shortener->method('resolve')->willReturn('https://vufind.org');
        $expectedResponse = new Response();
        $redirectHelper = $this->createMock(RedirectHelper::class);
        $redirectHelper->expects($this->once())->method('redirectToUrl')
            ->with($this->isInstanceOf(ResponseInterface::class), 'https://vufind.org')
            ->willReturn($expectedResponse);

        $action = $this->buildAction('http', shortener: $shortener, redirectHelper: $redirectHelper);
        $this->assertSame($expectedResponse, $action($this->requestWithId('abc'), new Response()));
    }

    /**
     * Test that an unknown redirect method throws a BadConfig exception.
     *
     * @return void
     */
    public function testInvalidRedirectMethodThrowsBadConfig(): void
    {
        $shortener = $this->createMock(UrlShortenerInterface::class);
        $shortener->method('resolve')->willReturn('https://vufind.org');
        $action = $this->buildAction('fake', shortener: $shortener);
        $request = $this->requestWithId('abc');
        $this->setProperty($action, 'request', $request);

        $this->expectException(BadConfig::class);
        $action->action($request, new Response());
    }

    /**
     * Data provider for testRendersNotFoundPageWhenUnresolvable().
     *
     * @return \Iterator
     */
    public static function notFoundProvider(): \Iterator
    {
        yield 'no id in route' => [null];

        yield 'id does not resolve' => ['abc'];
    }

    /**
     * Test that a missing or unresolvable short-link id renders the not-found page.
     *
     * @param ?string $id short-link id
     *
     * @return void
     */
    #[DataProvider('notFoundProvider')]
    public function testRendersNotFoundPageWhenUnresolvable(?string $id): void
    {
        $shortener = $this->createStub(UrlShortenerInterface::class);
        $shortener->method('resolve')->willReturn(null);
        $expectedResponse = new Response();
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->expects($this->once())->method('renderNotFoundPage')->willReturn($expectedResponse);

        $action = $this->buildAction('threshold:1000', shortener: $shortener, renderer: $renderer);
        $this->assertSame($expectedResponse, $action($this->requestWithId($id), new Response()));
    }
}
