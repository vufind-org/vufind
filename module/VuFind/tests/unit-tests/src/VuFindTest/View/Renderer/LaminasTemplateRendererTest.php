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
use Laminas\View\Model\ModelInterface;
use Laminas\View\Model\ViewModel;
use Laminas\View\Renderer\RendererInterface;
use Laminas\View\ViewInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use VuFind\ModuleManager\ModuleManager;
use VuFind\ModuleManager\ModuleManagerInterface;
use VuFind\View\Renderer\LaminasTemplateRenderer;
use VuFindTest\Feature\AutowireTrait;
use VuFindTest\Feature\ReflectionTrait;

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
    use ReflectionTrait;

    /**
     * Data provider for testRenderTemplate.
     *
     * @return Generator<string, array>
     */
    public static function renderTemplateProvider(): Generator
    {
        yield 'default action, no lightbox' => [null, null, false];
        yield 'default action, lightbox' => [null, null, true];
        yield 'custom action' => ['Foo/action', 'foo/action', false];
        yield 'custom action with template name inflection' => ['Custom/Foo/action', 'foo/action', false];
    }

    /**
     * Test renderTemplate method.
     *
     * @param ?string $action           Action id, or null for default
     * @param ?string $expectedTemplate Expected template based on action id, or null for default
     * @param bool    $inLightbox       Are we in lightbox?
     *
     * @return void
     */
    #[DataProvider('renderTemplateProvider')]
    public function testRenderTemplate(?string $action, ?string $expectedTemplate, bool $inLightbox): void
    {
        $content = 'This is HTML!';
        $layout = $this->getLayout($inLightbox, $expectedTemplate ?? 'test/action');
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
        $layout = $this->createMock(ModelInterface::class);
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
        $layout = $this->createMock(ModelInterface::class);
        $renderer = $this->getRenderer($layout, null);
        $request = (new ServerRequest())
            ->withAttribute('action-id', 'Test/Action');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Request must include the 'view-model' attribute");
        $renderer->renderTemplate($request, new Response());
    }

    /**
     * Test renderTemplateAsString.
     *
     * @return void
     */
    public function testRenderTemplateAsString(): void
    {
        $renderer = $this->getRenderer(null, true);

        $result = $renderer->renderTemplateAsString(template: 'error/index', params: ['message' => 'Foo']);
        $this->assertJsonStringEqualsJsonString(
            '{"template": "error\/index", "params": {"message": "Foo"}}',
            $result
        );
    }

    /**
     * Test missing request with layout in renderTemplateAsString.
     *
     * @return void
     */
    public function testMissingRequest(): void
    {
        $layout = $this->createMock(ModelInterface::class);
        $renderer = $this->getRenderer($layout, null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Request is required for rendering with layout');
        $renderer->renderTemplateAsString(template: 'foo', useLayout: true);
    }

    /**
     * Test template name prefix processing.
     *
     * @return void
     */
    public function testTemplateNamePrefixProcessing(): void
    {
        $testConfig = [
            'vufind' => [
                'extra_theme_prefixes' => ['Extra/'],
                'excluded_theme_prefixes' => ['Laminas'],
            ],
        ];
        $modules = ['Laminas\Foo', 'LaminasBar', 'VuFind\Foo', 'VuFind'];
        $mockModuleManager = $this->createMock(ModuleManager::class);
        $mockModuleManager->expects($this->once())->method('getModules')
            ->willReturn($modules);
        $renderer = $this->getRenderer(null, null, $testConfig, [ModuleManagerInterface::class => $mockModuleManager]);

        $this->assertSame(
            ['Extra/', 'VuFind/Foo/', 'VuFind/'],
            $this->callMethod($renderer, 'getThemePrefixes')
        );
    }

    /**
     * Test template name camelCase handling.
     *
     * @return void
     */
    public function testTemplateNameInflectionCamelCaseToLowerCase(): void
    {
        $testConfig = [
            'vufind' => [
                'extra_theme_prefixes' => ['VuFind/'],
            ],
        ];
        $renderer = $this->getRenderer(null, null, $testConfig);
        $this->assertEquals(
            'testcase',
            $this->callMethod($renderer, 'inflectName', ['VuFind/testCase'])
        );
    }

    /**
     * Get mock layout ViewModel.
     *
     * @param bool   $inLightbox       Are we in lightbox?
     * @param string $expectedTemplate Expected template
     *
     * @return MockObject&ModelInterface
     */
    protected function getLayout(bool $inLightbox, string $expectedTemplate): MockObject&ModelInterface
    {
        return $this->createMock(ModelInterface::class);
    }

    /**
     * Get LaminasTemplateRenderer.
     *
     * @param ModelInterface $layout       Layout
     * @param ?string        $content      Page content, null to not expect a call to render() method or true to return
     *                                     rendering context
     * @param array          $appConfig    Application configuration
     * @param array          $dependencies Additional dependencies
     *
     * @return LaminasTemplateRenderer
     */
    protected function getRenderer(
        ?ModelInterface $layout,
        string|true|null $content,
        array $appConfig = [],
        array $dependencies = []
    ): LaminasTemplateRenderer {
        $view = $this->createMock(ViewInterface::class);
        if ($layout) {
            $render = $view->expects(null !== $content ? $this->once() : $this->never())
                ->method('renderLayout');
        } else {
            $render = $view->expects(null !== $content ? $this->once() : $this->never())
                ->method('render');
        }
        if (true === $content) {
            $render->willReturnCallback(
                fn (ViewModel $viewModel) => json_encode([
                    'template' => $viewModel->getTemplate(),
                    'params' => $viewModel->getVariables(),
                ])
            );
        } else {
            $render->willReturn($content ?? '');
        }

        return $this->getAutowiredObject(
            LaminasTemplateRenderer::class,
            [
                'config' => $appConfig,
                'ViewRenderer' => $this->createMock(RendererInterface::class),
                ViewInterface::class => $view,
            ] + $dependencies
        );
    }
}
