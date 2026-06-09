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
use Laminas\View\Renderer\PhpRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Http\ServerUrlHelper;
use VuFind\ServiceManager\Factory\Autowire;
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
     * Constructor.
     *
     * @param ServerUrlHelper        $serverUrlHelper        Server URL helper
     * @param PhpRenderer            $viewRenderer           View renderer
     * @param ViewManager            $viewManager            View manager
     * @param InjectTemplateListener $injectTemplateListener Template injection listener (for prefixes)
     * @param bool                   $displayExceptions      Display exceptions?
     * @param string                 $notFoundTemplate       Template for 404 errors
     * @param string                 $errorTemplate          Template for errors
     */
    #[Autowire()]
    public function __construct(
        protected ServerUrlHelper $serverUrlHelper,
        #[Autowire(service: 'ViewRenderer')]
        protected PhpRenderer $viewRenderer,
        #[Autowire(service: 'ViewManager')]
        protected ViewManager $viewManager,
        protected InjectTemplateListener $injectTemplateListener,
        #[Autowire(service: 'config', path: 'view_manager/display_exceptions', default: false)]
        protected bool $displayExceptions,
        #[Autowire(service: 'config', path: 'view_manager/not_found_template', default: 'error/404')]
        protected string $notFoundTemplate,
        #[Autowire(service: 'config', path: 'view_manager/exception_template', default: 'error/index')]
        protected string $errorTemplate,
    ) {
    }

    /**
     * Render a template and return the result in the response object.
     *
     * @param ServerRequestInterface $request        Request
     * @param ResponseInterface      $response       Response object
     * @param ?string                $template       Template name, or null to use default for the action
     * @param array                  $params         Template parameters
     * @param array[]                $childTemplates Any child templates; an array of associative array with keys
     * 'template' and 'params'
     *
     * @return ResponseInterface
     */
    public function renderTemplate(
        ServerRequestInterface $request,
        ResponseInterface $response,
        ?string $template = null,
        array $params = [],
        array $childTemplates = [],
    ): ResponseInterface {
        $response->getBody()->write($this->renderTemplateAsString($request, $template, $params, useLayout: true));
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
        array $params = [],
    ): ResponseInterface {
        $params['display_exceptions'] = $this->displayExceptions;
        return $this->renderTemplate(
            $request,
            $response->withStatus(500),
            $this->errorTemplate,
            $params
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
        array $params = [],
    ): ResponseInterface {
        return $this->renderTemplate(
            $request,
            $response->withStatus(404),
            $this->notFoundTemplate,
            $params + ['message' => 'Page not found.']
        );
    }

    /**
     * Render a template and return the result as a string.
     *
     * @param ?ServerRequestInterface $request        Request (must be set if $template is null or $useLayout is true)
     * @param ?string                 $template       Template name, or null to use default for the action
     * @param array                   $params         Template parameters
     * @param array[]                 $childTemplates Any child templates; an array of associative array with keys
     *                                                'template' and 'params'
     * @param bool                    $useLayout      Render full page with the layout?
     *
     * @return string
     */
    public function renderTemplateAsString(
        ?ServerRequestInterface $request = null,
        ?string $template = null,
        array $params = [],
        array $childTemplates = [],
        bool $useLayout = false,
    ): string {
        $view = $this->viewManager->getView();
        $viewModel = $this->createViewModel($request, $params, $template);
        foreach ($childTemplates as $current) {
            $viewModel->addChild($this->createViewModel($request, $current['params'] ?? [], $current['template']));
        }
        if (!$useLayout) {
            // Force renderer to return the result:
            $viewModel->setOption('has_parent', true);
            return $view->render($viewModel);
        }

        // Prepare layout and render:
        if (null === $request) {
            throw new InvalidArgumentException('Request is required for rendering with layout');
        }
        $layout = $this->getLayout($request);

        $templateParts = explode('/', $viewModel->getTemplate());
        $layout->setVariable('templateDir', $templateParts[0]);
        $layout->setVariable('templateName', $templateParts[1] ?? null);

        if ($this->inLightbox($request)) {
            $layout->setTemplate('layout/lightbox');
        }
        $lightboxParentUrl = new Http($this->serverUrlHelper->getCurrentUrl());
        $query = $lightboxParentUrl->getQueryAsArray();
        unset($query['lightboxChild']);
        $lightboxParentUrl->setQuery($query);
        $layout->lightboxParent = $lightboxParentUrl->toString();
        if ($lightboxChild = $request->getQueryParams()['lightboxChild'] ?? null) {
            $layout->lightboxChild = $lightboxChild;
        }

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
     * Find the filename for a template.
     *
     * @param string $template Template
     *
     * @return ?string Filename, or null if not found
     */
    public function resolveTemplateFilename(string $template): ?string
    {
        return $this->viewRenderer->resolver($template);
    }

    /**
     * Create a new ViewModel.
     *
     * @param ?ServerRequestInterface $request  Request
     * @param array                   $params   Parameters to pass to ViewModel constructor
     * @param ?string                 $template Template name, or null to use default for the action
     *
     * @return ViewModel
     */
    public function createViewModel(
        ?ServerRequestInterface $request,
        array $params,
        ?string $template = null
    ): ViewModel {
        $template ??= $this->getDefaultTemplateName($request);
        if ($request && $this->inLightbox($request)) {
            $params['inLightbox'] = true;
        }
        $viewModel = new ViewModel($params);
        $viewModel->setTemplate($template);
        return $viewModel;
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
        if (!($layout = $request->getAttribute('view-model'))) {
            throw new InvalidArgumentException("Request must include the 'view-model' attribute");
        }

        return $layout;
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
     * Get default template name for the action.
     *
     * @param ?ServerRequestInterface $request Request
     *
     * @return string
     */
    protected function getDefaultTemplateName(?ServerRequestInterface $request): string
    {
        if (!($action = $request?->getAttribute('action-id'))) {
            throw new \InvalidArgumentException("Request must include the 'action-id' attribute");
        }
        $parts = explode('/', $action);
        $second = array_pop($parts);
        $first = implode('/', $parts);
        return $this->inflectName($first) . '/' . $this->inflectName($second);
    }

    /**
     * Inflect a name to a normalized value.
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
