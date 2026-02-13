<?php

/**
 * Abstract base class for actions that render templates.
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
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Action;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use VuFind\View\Renderer\TemplateRendererInterface;

/**
 * Abstract base class for actions that render templates.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
abstract class AbstractTemplateRenderingAction extends AbstractAction
{
    /**
     * Template renderer
     *
     * @var ?TemplateRendererInterface
     */
    protected ?TemplateRendererInterface $templateRenderer = null;

    /**
     * Set template renderer.
     *
     * @param TemplateRendererInterface $templateRenderer Template renderer
     *
     * @return static
     */
    public function setTemplateRenderer(TemplateRendererInterface $templateRenderer): static
    {
        $this->templateRenderer = $templateRenderer;
        return $this;
    }

    /**
     * Invoke the action.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $this->request = $request;
        $this->response = $response;
        try {
            return $this->action($request, $response);
        } catch (Throwable $exception) {
            $message = 'An error occurred during execution; please try again later.';
            return $this->renderErrorPage(
                $request,
                $response,
                compact('exception', 'message')
            );
        }
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
        return $this->renderTemplate($request, $response, $params, $template);
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
        return $this->getTemplateRenderer()->renderErrorPage($request, $response, $params);
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
        return $this->getTemplateRenderer()->renderNotFoundPage($request, $response, $params);
    }

    /**
     * Get template renderer.
     *
     * @return TemplateRendererInterface
     */
    protected function getTemplateRenderer(): TemplateRendererInterface
    {
        if (null === $this->templateRenderer) {
            throw new Exception($this::class . ' action not properly initialized; template renderer missing');
        }
        return $this->templateRenderer;
    }
}
