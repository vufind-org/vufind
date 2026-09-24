<?php

/**
 * Ajax action test class.
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

namespace VuFindTest\Action\Ajax;

use Laminas\Diactoros\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\Ajax\AbstractAjaxAction;
use VuFind\Action\Ajax\JsonAction;
use VuFind\Action\Ajax\OnlinePaymentNotifyAction;
use VuFind\Action\Ajax\RecommendAction;
use VuFind\Action\Ajax\SystemStatusAction;
use VuFind\ActionHelper\ResponseHelper;
use VuFind\AjaxHandler\AjaxHandlerInterface;
use VuFind\AjaxHandler\PluginManager as AjaxPluginManager;
use VuFindTest\Action\AbstractActionTestCase;

/**
 * Ajax action test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AjaxActionTest extends AbstractActionTestCase
{
    use \VuFindTest\Feature\TranslatorTrait;

    /**
     * Build an Ajax action of the given class, wiring the collaborators a test cares about.
     *
     * @param class-string       $class          Action class to build
     * @param ?AjaxPluginManager $ajaxManager    AJAX handler plugin manager (defaults to a stub)
     * @param ?ResponseHelper    $responseHelper Response helper returned by the helper plugin manager
     * (defaults to a stub)
     *
     * @return AbstractAjaxAction
     */
    protected function buildAction(
        string $class,
        ?AjaxPluginManager $ajaxManager = null,
        ?ResponseHelper $responseHelper = null
    ): AbstractAjaxAction {
        $action = new $class($ajaxManager ?? $this->createStub(AjaxPluginManager::class));
        $this->initializeAction(
            $action,
            [ResponseHelper::class => $responseHelper ?? $this->createStub(ResponseHelper::class)]
        );
        $action->setTranslator($this->getMockTranslator([]));
        return $action;
    }

    /**
     * Get an AJAX plugin manager. With no method name it reports no handler; otherwise the named handler either returns
     * the given result or throws the given exception.
     *
     * @param ?string     $method Method the manager should recognize (null = recognizes nothing)
     * @param array       $result Result array the handler returns from handleRequest()
     * @param ?\Throwable $throws Exception the handler throws instead of returning
     *
     * @return AjaxPluginManager
     */
    protected function getAjaxManager(
        ?string $method = null,
        array $result = [],
        ?\Throwable $throws = null
    ): AjaxPluginManager {
        $manager = $this->createMock(AjaxPluginManager::class);
        if (null === $method) {
            $manager->method('has')->willReturn(false);
            return $manager;
        }
        $manager->method('has')->willReturnCallback(fn ($m) => $m === $method);
        $handler = $this->createMock(AjaxHandlerInterface::class);
        if (null !== $throws) {
            $handler->method('handleRequest')->willThrowException($throws);
        } else {
            $handler->method('handleRequest')->willReturn($result);
        }
        $manager->method('get')->with($method)->willReturn($handler);
        return $manager;
    }

    /**
     * Build a request carrying the given query parameters.
     *
     * @param array $query Query parameters
     *
     * @return ServerRequestInterface
     */
    protected function request(array $query = []): ServerRequestInterface
    {
        return $this->getServerRequest(queryParams: $query);
    }

    /**
     * Test that JsonAction returns a 400 JSON error when the "method" parameter is missing.
     *
     * @return void
     */
    public function testJsonActionMissingMethodReturnsError(): void
    {
        $expectedResponse = new Response();
        $expectedData = ['data' => ['error' => 'Parameter "method" missing']];
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects($this->once())->method('getJsonResponse')
            ->with($this->isInstanceOf(ResponseInterface::class), $expectedData, 400)
            ->willReturn($expectedResponse);

        $action = $this->buildAction(JsonAction::class, responseHelper: $responseHelper);
        $this->assertSame($expectedResponse, $action($this->request(), new Response()));
    }

    /**
     * Test that JsonAction dispatches to the named handler and wraps its data in a JSON response.
     *
     * @return void
     */
    public function testJsonActionDispatchesToHandler(): void
    {
        $expectedResponse = new Response();
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects($this->once())->method('getAjaxResponse')
            ->with($this->isInstanceOf(ResponseInterface::class), 'application/json', ['data' => ['x' => 1]], null)
            ->willReturn($expectedResponse);

        $action = $this->buildAction(
            JsonAction::class,
            ajaxManager: $this->getAjaxManager('foo', [['x' => 1]]),
            responseHelper: $responseHelper
        );
        $this->assertSame($expectedResponse, $action($this->request(['method' => 'foo']), new Response()));
    }

    /**
     * Test that an HTTP status returned by the handler is forwarded to the response helper.
     *
     * @return void
     */
    public function testHandlerHttpStatusIsForwarded(): void
    {
        $expectedResponse = new Response();
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects($this->once())->method('getAjaxResponse')
            ->with($this->isInstanceOf(ResponseInterface::class), 'application/json', ['data' => ['ok' => true]], 201)
            ->willReturn($expectedResponse);

        $action = $this->buildAction(
            JsonAction::class,
            ajaxManager: $this->getAjaxManager('foo', [['ok' => true], 201]),
            responseHelper: $responseHelper
        );
        $this->assertSame($expectedResponse, $action($this->request(['method' => 'foo']), new Response()));
    }

    /**
     * Test that an exception thrown by a handler is turned into an exception response.
     *
     * @return void
     */
    public function testHandlerExceptionReturnsExceptionResponse(): void
    {
        $exception = new \RuntimeException('boom');
        $expectedResponse = new Response();
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects($this->once())->method('getExceptionResponse')
            ->with($this->isInstanceOf(ResponseInterface::class), 'application/json', $this->identicalTo($exception))
            ->willReturn($expectedResponse);

        $action = $this->buildAction(
            JsonAction::class,
            ajaxManager: $this->getAjaxManager('foo', throws: $exception),
            responseHelper: $responseHelper
        );
        $this->assertSame($expectedResponse, $action($this->request(['method' => 'foo']), new Response()));
    }

    /**
     * Data provider for testUnknownMethodReturnsBadRequest().
     *
     * @return \Iterator
     */
    public static function unknownMethodProvider(): \Iterator
    {
        yield 'JSON action wraps the message' => [JsonAction::class, 'application/json', ['data' => 'Invalid Method']];

        yield 'HTML action passes the message straight through' => [
            OnlinePaymentNotifyAction::class,
            'text/html',
            'Invalid Method',
        ];
    }

    /**
     * Test that an unrecognized method yields a bad-request "Invalid Method" response in the action's content type.
     *
     * @param class-string $class        Action class to build
     * @param string       $type         Expected response content type
     * @param array|string $expectedData Expected response data
     *
     * @return void
     */
    #[DataProvider('unknownMethodProvider')]
    public function testUnknownMethodReturnsBadRequest(string $class, string $type, array|string $expectedData): void
    {
        $expectedResponse = new Response();
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects($this->once())->method('getAjaxResponse')
            ->with($this->isInstanceOf(ResponseInterface::class), $type, $expectedData, 400)
            ->willReturn($expectedResponse);

        $action = $this->buildAction($class, ajaxManager: $this->getAjaxManager(), responseHelper: $responseHelper);
        $this->assertSame($expectedResponse, $action($this->request(['method' => 'foo']), new Response()));
    }

    /**
     * Data provider for testActionDispatchesUsingContentType().
     *
     * @return \Iterator
     */
    public static function dispatchContentTypeProvider(): \Iterator
    {
        yield 'Recommend renders HTML' => [RecommendAction::class, 'recommend', ['<div>rec</div>'], 'text/html'];

        yield 'SystemStatus renders plain text' => [SystemStatusAction::class, 'systemStatus', ['OK'], 'text/plain'];
    }

    /**
     * Test that a non-JSON action dispatches its handler and passes the returned data straight through in its own
     * content type.
     *
     * @param class-string $class  Action class to build
     * @param string       $method AJAX method the action dispatches
     * @param array        $result Result returned by the handler
     * @param string       $type   Expected response content type
     *
     * @return void
     */
    #[DataProvider('dispatchContentTypeProvider')]
    public function testActionDispatchesUsingContentType(
        string $class,
        string $method,
        array $result,
        string $type
    ): void {
        $expectedResponse = new Response();
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects($this->once())->method('getAjaxResponse')
            ->with($this->isInstanceOf(ResponseInterface::class), $type, $result[0], null)
            ->willReturn($expectedResponse);

        $action = $this->buildAction(
            $class,
            ajaxManager: $this->getAjaxManager($method, $result),
            responseHelper: $responseHelper
        );
        $this->assertSame($expectedResponse, $action($this->request(), new Response()));
    }
}
