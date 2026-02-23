<?php

/**
 * Laminas template renderer.
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

use InvalidArgumentException;
use Laminas\Mvc\View\Http\ViewManager;
use Laminas\Uri\Http;
use Laminas\View\Model\ModelInterface;
use Laminas\View\Model\ViewModel;
use Laminas\View\Renderer\RendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Http\ServerUrlHelper;
use VuFindTheme\InjectTemplateListener;

use function strlen;

/**
 * Laminas template renderer.
 *
 * @category VuFind
 * @package  View
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class LaminasTemplateRenderer implements TemplateRendererInterface
{
    /**
     * Display exceptions?
     *
     * @var bool
     */
    protected bool $displayExceptions;

    /**
     * Template for 404 errors
     *
     * @var string
     */
    protected string $notFoundTemplate;

    /**
     * Template for errors
     *
     * @var string
     */
    protected string $errorTemplate;

    /**
     * Constructor.
     *
     * @param ServerUrlHelper        $serverUrlHelper        Server URL helper
     * @param RendererInterface      $viewRenderer           View renderer
     * @param ViewManager            $viewManager            View manager
     * @param InjectTemplateListener $injectTemplateListener Template injection listener (for prefixes)
     * @param array                  $viewManagerConfig      View manager configuration
     */
    public function __construct(
        protected ServerUrlHelper $serverUrlHelper,
        protected RendererInterface $viewRenderer,
        protected ViewManager $viewManager,
        protected InjectTemplateListener $injectTemplateListener,
        protected array $viewManagerConfig,
    ) {
        $this->displayExceptions = $viewManagerConfig['display_exceptions'] ?? false;
        $this->notFoundTemplate = $viewManagerConfig['not_found_template'] ?? 'error/404';
        $this->errorTemplate = $viewManagerConfig['exception_template'] ?? 'error/index';
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
     * Render an error template and return the result in the response object.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response object
     * @param array                  $params   Template parameters
     *
     * @return ResponseInterface
     */
    public function renderErrorPage(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params
    ): ResponseInterface {
        $params['display_exceptions'] = $this->displayExceptions;
        return $this->renderTemplate(
            $request,
            $response->withStatus(500),
            $params,
            $this->errorTemplate
        );
    }

    /**
     * Render a "not found" template and return the result in the response object.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response object
     * @param array                  $params   Template parameters
     *
     * @return ResponseInterface
     */
    public function renderNotFoundPage(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params = []
    ): ResponseInterface {
        return $this->renderTemplate(
            $request,
            $response->withStatus(404),
            $params + ['message' => 'Page not found.'],
            $this->notFoundTemplate
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
        $lightboxParentUrl = new Http($this->serverUrlHelper->getCurrentUrl());
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
     *
     * @throws InvalidArgumentException
     */
    public function getLayout(ServerRequestInterface $request): ModelInterface
    {
        if (!($result = $request->getAttribute('view-model'))) {
            throw new InvalidArgumentException("Attribute 'view-model' required in request");
        }
        return $result;
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
     * Get view renderer.
     *
     * @return RendererInterface
     */
    protected function getViewRenderer(): RendererInterface
    {
        return $this->viewRenderer;
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
