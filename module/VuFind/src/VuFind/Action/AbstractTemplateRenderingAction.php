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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use VuFind\ServiceManager\Factory\Autowire;
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
     * Constructor.
     *
     * @param TemplateRendererInterface $templateRenderer Template renderer
     */
    #[Autowire()]
    public function __construct(
        protected TemplateRendererInterface $templateRenderer,
    ) {
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
            return $this->templateRenderer->renderErrorPage(
                $request,
                $response,
                compact('exception', 'message')
            );
        }
    }
}
