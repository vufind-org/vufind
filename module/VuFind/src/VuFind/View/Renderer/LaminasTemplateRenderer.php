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
use Laminas\Uri\Http;
use Laminas\View\Helper\ViewModel as ViewModelHelper;
use Laminas\View\HelperPluginManagerInterface;
use Laminas\View\Model\ModelInterface;
use Laminas\View\Model\ViewModel;
use Laminas\View\Renderer\RendererInterface;
use Laminas\View\Resolver\ResolverInterface;
use Laminas\View\ViewInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Http\ServerUrlHelper;
use VuFind\ModuleManager\ModuleManagerInterface;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\GlobalsContainer;

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
     * Parsed theme prefixes.
     *
     * @var ?array
     */
    protected ?array $parsedThemePrefixes = null;

    /**
     * Constructor.
     *
     * @param ServerUrlHelper              $serverUrlHelper       Server URL helper
     * @param RendererInterface            $viewRenderer          View renderer
     * @param ViewInterface                $view                  View
     * @param ResolverInterface            $templateResolver      Template resolver
     * @param HelperPluginManagerInterface $helperPluginManager   View helper plugin manager
     * @param bool                         $displayExceptions     Display exceptions?
     * @param string                       $notFoundTemplate      Template for 404 errors
     * @param string                       $errorTemplate         Template for errors
     * @param array                        $extraThemePrefixes    Extra theme prefixes
     * @param array                        $excludedThemePrefixes Excluded theme prefixes
     * @param GlobalsContainer             $globalsContainer      Global data container
     * @param ModuleManagerInterface       $moduleManager         Module manager
     */
    public function __construct(
        protected ServerUrlHelper $serverUrlHelper,
        #[Autowire(service: 'ViewRenderer')]
        protected RendererInterface $viewRenderer,
        protected ViewInterface $view,
        protected ResolverInterface $templateResolver,
        protected HelperPluginManagerInterface $helperPluginManager,
        #[Autowire(service: 'config', path: 'view_manager/display_exceptions', default: false)]
        protected bool $displayExceptions,
        #[Autowire(service: 'config', path: 'view_manager/not_found_template', default: 'error/404')]
        protected string $notFoundTemplate,
        #[Autowire(service: 'config', path: 'view_manager/exception_template', default: 'error/index')]
        protected string $errorTemplate,
        #[Autowire(service: 'config', path: 'vufind/extra_theme_prefixes', default: [])]
        protected array $extraThemePrefixes,
        #[Autowire(service: 'config', path: 'vufind/excluded_theme_prefixes', default: [])]
        protected array $excludedThemePrefixes,
        protected GlobalsContainer $globalsContainer,
        protected ModuleManagerInterface $moduleManager,
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
     * @param bool                    $rootContext    Include root context (main template variables)?
     *
     * @return string
     */
    public function renderTemplateAsString(
        ?ServerRequestInterface $request = null,
        ?string $template = null,
        array $params = [],
        array $childTemplates = [],
        bool $useLayout = false,
        bool $rootContext = false,
    ): string {
        $viewModelHelper = $this->helperPluginManager->get(ViewModelHelper::class);
        // Save current model so that we can restore it when done:
        $saveCurrentModel = $viewModelHelper?->getCurrent();
        try {
            if ($rootContext && ($rootViewModel = $this->globalsContainer['viewModel'] ?? null)) {
                $params += $rootViewModel->getVariables();
            }
            $viewModel = $this->createViewModel($request, $params, $template);
            foreach ($childTemplates as $current) {
                $viewModel->addChild($this->createViewModel($request, $current['params'] ?? [], $current['template']));
            }
            if (!$useLayout) {
                return $this->view->render($viewModel, enableLayout: false);
            }

            // Prepare layout and render:
            if (null === $request) {
                throw new InvalidArgumentException('Request is required for rendering with layout');
            }
            $templateParts = explode('/', $viewModel->getTemplate());
            $this->globalsContainer['templateDir'] = $templateParts[0];
            $this->globalsContainer['templateName'] = $templateParts[1] ?? null;
            $this->globalsContainer['viewModel'] = $viewModel;

            $layoutModel = new ViewModel([], 'layout/layout');
            $this->setupRenderingInLightbox($request, $layoutModel);
            $layoutModel->addChild($viewModel);

            return $this->view->renderLayout($layoutModel);
        } finally {
            // Restore the previous "current" model:
            if ($saveCurrentModel) {
                $viewModelHelper->setCurrent($saveCurrentModel);
            }
        }
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
        return $this->templateResolver->resolve($template) ?: null;
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
    protected function inflectName(string $name): string
    {
        foreach ($this->getThemePrefixes() as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return strtolower(substr($name, strlen($prefix)));
            }
        }
        return strtolower($name);
    }

    /**
     * Get template prefixes.
     *
     * @return array
     */
    protected function getThemePrefixes(): array
    {
        if (null === $this->parsedThemePrefixes) {
            // We assume that - by default - modules try loading templates from their own namespace, thus all loaded
            // modules are included for inflection:
            $modules = array_map(
                function ($module) {
                    return str_replace('\\', '/', $module) . '/';
                },
                $this->moduleManager->getModules()
            );
            $this->parsedThemePrefixes = array_values(
                array_unique(
                    array_filter(
                        [...$this->extraThemePrefixes, ...$modules],
                        function ($prefix) {
                            foreach ($this->excludedThemePrefixes as $current) {
                                if (str_starts_with($prefix, $current)) {
                                    return false;
                                }
                            }
                            return true;
                        }
                    )
                )
            );
        }
        return $this->parsedThemePrefixes;
    }

    /**
     * Setup layout with lightbox-related variables, and template when a lightbox is requested.
     *
     * @param ServerRequestInterface $request Request
     * @param ModelInterface         $layout  Layout
     *
     * @return void
     */
    protected function setupRenderingInLightbox(ServerRequestInterface $request, ModelInterface $layout): void
    {
        if ($this->inLightbox($request)) {
            $layout->setTemplate('layout/lightbox');
            $this->globalsContainer['inLightbox'] = true;
        }
        $lightboxParentUrl = new Http($this->serverUrlHelper->getCurrentUrl());
        $query = $lightboxParentUrl->getQueryAsArray();
        unset($query['lightboxChild']);
        $lightboxParentUrl->setQuery($query);
        $this->globalsContainer['lightboxParent'] = $lightboxParentUrl->toString();
        if ($lightboxChild = $request->getQueryParams()['lightboxChild'] ?? null) {
            $this->globalsContainer['lightboxChild'] = $lightboxChild;
        }
    }
}
