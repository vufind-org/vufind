<?php

/**
 * LaminasTemplateRenderer test class.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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

namespace VuFindTest\View\Renderer;

use Generator;
use InvalidArgumentException;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Laminas\Mvc\View\Http\ViewManager;
use Laminas\View\Model\ViewModel;
use Laminas\View\Renderer\RendererInterface;
use Laminas\View\View;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VuFind\View\Renderer\LaminasTemplateRenderer;
use VuFindTest\Feature\AutowireTrait;
use VuFindTheme\InjectTemplateListener;

/**
 * LaminasTemplateRenderer test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class LaminasTemplateRendererTest extends TestCase
{
    use AutowireTrait;

    /**
     * Data provider for testRenderTemplate.
     *
     * @return Generator<string, array>
     */
    public static function renderTemplateProvider(): Generator
    {
        yield 'default action, no lightbox' => [null, false];
        yield 'default action, lightbox' => [null, true];
        yield 'custom action' => ['Custom/Test/action', false];
    }

    /**
     * Test renderTemplate method.
     *
     * @param ?string $action     Action name override
     * @param bool    $inLightbox Are we in lightbox?
     *
     * @return void
     */
    #[DataProvider('renderTemplateProvider')]
    public function testRenderTemplate(?string $action, bool $inLightbox): void
    {
        $content = 'This is HTML!';
        $layout = $this->getLayout($inLightbox, 'test/action');
        $renderer = $this->getRenderer($layout, $content);
        $request = (new ServerRequest())
            ->withAttribute('view-model', $layout)
            ->withAttribute('action-id', $action ?? 'Test/Action');
        if ($inLightbox) {
            $request = $request->withQueryParams(['layout' => 'lightbox', 'lightboxChild' => 'child']);
        }

        $response = $renderer->renderTemplate($request, new Response());
        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $body->rewind();
        $this->assertSame($content, $body->getContents());
    }

    /**
     * Test renderErrorPage method.
     *
     * @return void
     */
    public function testRenderErrorPage(): void
    {
        $layout = $this->getLayout(false, 'error/index');
        $renderer = $this->getRenderer($layout, 'Error!');
        $request = (new ServerRequest())
            ->withAttribute('view-model', $layout)
            ->withAttribute('action-id', 'Test/Action');

        $response = $renderer->renderErrorPage($request, new Response());
        $this->assertSame(500, $response->getStatusCode());
        $body = $response->getBody();
        $body->rewind();
        $this->assertSame('Error!', $body->getContents());
    }

    /**
     * Test renderNotFoundPage method.
     *
     * @return void
     */
    public function testRenderNotFoundPage(): void
    {
        $layout = $this->getLayout(false, 'error/404');
        $renderer = $this->getRenderer($layout, 'Error!');
        $request = (new ServerRequest())
            ->withAttribute('view-model', $layout)
            ->withAttribute('action-id', 'Test/Action');

        $response = $renderer->renderNotFoundPage($request, new Response());
        $this->assertSame(404, $response->getStatusCode());
        $body = $response->getBody();
        $body->rewind();
        $this->assertSame('Error!', $body->getContents());
    }

    /**
     * Test missing action-id attribute.
     *
     * @return void
     */
    public function testMissingActionIdAttribute(): void
    {
        $layout = $this->createMock(ViewModel::class);
        $renderer = $this->getRenderer($layout, null);
        $request = (new ServerRequest())
            ->withAttribute('view-model', $layout);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Request must include the 'action-id' attribute");
        $renderer->renderTemplate($request, new Response());
    }

    /**
     * Test missing view-model attribute.
     *
     * @return void
     */
    public function testMissingViewModelAttribute(): void
    {
        $layout = $this->createMock(ViewModel::class);
        $renderer = $this->getRenderer($layout, null);
        $request = (new ServerRequest())
            ->withAttribute('action-id', 'Test/Action');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Request must include the 'view-model' attribute");
        $renderer->renderTemplate($request, new Response());
    }

    /**
     * Get mock layout ViewModel.
     *
     * @param bool   $inLightbox       Are we in lightbox?
     * @param string $expectedTemplate Expected template
     *
     * @return MockObject&ViewModel
     */
    protected function getLayout(bool $inLightbox, string $expectedTemplate): ViewModel
    {
        $layout = $this->createMock(ViewModel::class);
        $layout->expects($this->once())
            ->method('clearChildren');
        $layout->expects($this->once())
            ->method('setOption')
            ->with('has_parent', true);
        $layout->expects($inLightbox ? $this->once() : $this->never())
            ->method('setTemplate')
            ->with('layout/lightbox');
        $layout->expects($this->once())
            ->method('addChild')
            ->willReturnCallback(
                function ($viewModel) use ($expectedTemplate): void {
                    $this->assertSame($expectedTemplate, $viewModel->getTemplate());
                }
            );
        return $layout;
    }

    /**
     * Get LaminasTemplateRenderer.
     *
     * @param ViewModel $layout  Layout
     * @param ?string   $content Page content, or null to not expect a call to render() method
     *
     * @return LaminasTemplateRenderer
     */
    protected function getRenderer(ViewModel $layout, ?string $content): LaminasTemplateRenderer
    {
        $view = $this->createMock(View::class);
        $view->expects(null !== $content ? $this->once() : $this->never())
            ->method('render')
            ->with($layout)
            ->willReturn($content);

        $viewManager = $this->createMock(ViewManager::class);
        $viewManager->method('getView')
            ->willReturn($view);

        $injectTemplateListener = new InjectTemplateListener(['Custom/']);

        return $this->getAutowiredObject(
            LaminasTemplateRenderer::class,
            [
                'config' => [],
                'ViewRenderer' => $this->createMock(RendererInterface::class),
                'ViewManager' => $viewManager,
                InjectTemplateListener::class => $injectTemplateListener,
            ]
        );
    }
}
