<?php

/**
 * Laminas view renderer.
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
 * @package  View
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\View\Renderer;

use Closure;
use Laminas\Mvc\View\Http\ViewManager;
use Laminas\Uri\Http;
use Laminas\View\Model\ModelInterface;
use Laminas\View\Model\ViewModel;
use Laminas\View\Renderer\RendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFindTheme\InjectTemplateListener;

use function strlen;

/**
 * Laminas view renderer.
 *
 * @category VuFind
 * @package  View
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class LaminasViewRenderer
{
    /**
     * Constructor.
     *
     * @param Closure                $serverUrlHelperFactory ServerUrl helper factory callback
     * @param RendererInterface      $viewRenderer           View renderer
     * @param ViewManager            $viewManager            View manager
     * @param InjectTemplateListener $injectTemplateListener Template injection listener (for prefixes)
     * @param bool                   $displayExceptions      Display exceptions?
     */
    public function __construct(
        protected Closure $serverUrlHelperFactory,
        protected RendererInterface $viewRenderer,
        protected ViewManager $viewManager,
        protected InjectTemplateListener $injectTemplateListener,
        protected bool $displayExceptions,
    ) {
    }

    /**
     * Render a template and return the result in the response object.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response object
     * @param array                  $params   Template parameters
     * @param ?string                $template Template name, or null to use default for the action
     *
     * @return ResponseInterface
     */
    public function renderTemplate(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params = [],
        ?string $template = null,
    ): ResponseInterface {
        $response->getBody()->write($this->renderTemplateAsString($request, $params, $template));
        return $response;
    }

    /**
     * Render a template and return the result in the response object.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response object
     *
     * @return ResponseInterface
     */
    public function renderNotFoundPage(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        return $this->renderTemplate(
            $request,
            $response->withStatus(404),
            ['message' => 'Page not found.'],
            'error/404.phtml'
        );
    }

    /**
     * Render a template and return the result as a string.
     *
     * @param ServerRequestInterface $request  Request
     * @param array                  $params   Template parameters
     * @param ?string                $template Template name, or null to use default for the action
     *
     * @return string
     */
    public function renderTemplateAsString(
        ServerRequestInterface $request,
        array $params = [],
        ?string $template = null,
    ): string {
        $template ??= $this->getDefaultTemplateName($request);
        $view = $this->viewManager->getView();
        $viewModel = $this->createViewModel($request, $params)->setTemplate($template);
        $layout = $this->getLayout($request);
        // Clear any previous children (e.g. when rendering an error):
        if ($layout instanceof ViewModel) {
            $layout->clearChildren();
        }
        $layout->addChild($viewModel);
        // Force renderer to return the result:
        $layout->setOption('has_parent', true);
        return $view->render($layout);
    }

    /**
     * Get view renderer.
     *
     * @return RendererInterface
     */
    protected function getViewRenderer(): RendererInterface
    {
        return $this->viewRenderer;
    }

    /**
     * Create a new ViewModel.
     *
     * @param ServerRequestInterface $request Request
     * @param array                  $params  Parameters to pass to ViewModel constructor.
     *
     * @return ViewModel
     */
    public function createViewModel(ServerRequestInterface $request, array $params): ViewModel
    {
        $layout = $this->getLayout($request);
        if ($this->inLightbox($request)) {
            $layout->setTemplate('layout/lightbox');
            $params['inLightbox'] = true;
        }
        $serverUrl = ($this->serverUrlHelperFactory)();
        $lightboxParentUrl = new Http($serverUrl(true));
        $query = $lightboxParentUrl->getQueryAsArray();
        unset($query['lightboxChild']);
        $lightboxParentUrl->setQuery($query);
        $layout->lightboxParent = $lightboxParentUrl->toString();
        if ($lightboxChild = $request->getQueryParams()['lightboxChild'] ?? null) {
            $layout->lightboxChild = $lightboxChild;
        }
        return new ViewModel($params);
    }

    /**
     * Get layout (root view model).
     *
     * @param ServerRequestInterface $request Request
     *
     * @return ModelInterface
     */
    public function getLayout(ServerRequestInterface $request): ModelInterface
    {
        return $request->getAttribute('view-model');
    }

    /**
     * Are we currently in a lightbox context?
     *
     * @param ServerRequestInterface $request Request
     *
     * @return bool
     */
    public function inLightbox(ServerRequestInterface $request): bool
    {
        $layout = $request->getParsedBody()['layout'] ?? $request->getQueryParams()['layout'] ?? null;
        return 'lightbox' === $layout || 'layout/lightbox' === $this->getLayout($request)->getTemplate();
    }

    /**
     * Should exceptions be displayed?
     *
     * @return bool
     */
    public function getDisplayExceptions(): bool
    {
        return $this->displayExceptions;
    }

    /**
     * Get default template name for the action.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return string
     */
    protected function getDefaultTemplateName(ServerRequestInterface $request): string
    {
        if (!($action = $request->getAttribute('action-id'))) {
            throw new \InvalidArgumentException("Request must include the 'action-id' attribute");
        }
        $parts = explode('\\', $action);
        $second = array_pop($parts);
        if (str_ends_with($second, 'Action')) {
            $second = substr($second, 0, -6);
        }
        $parts = array_diff($parts, ['Action']);
        $first = implode('/', $parts);
        return $this->inflectName($first) . '/' . $this->inflectName($second);
    }

    /**
     * Inflect a name to a normalized value
     *
     * @param string $name Name to inflect
     *
     * @return string
     */
    protected function inflectName($name)
    {
        foreach ($this->injectTemplateListener->getPrefixes() as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return strtolower(substr($name, strlen($prefix)));
            }
        }
        return strtolower($name);
    }
}
