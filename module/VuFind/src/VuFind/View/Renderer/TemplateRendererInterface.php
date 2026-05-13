<?php

/**
 * Template renderer interface.
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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Template renderer interface.
 *
 * @category VuFind
 * @package  View
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
interface TemplateRendererInterface
{
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
    ): ResponseInterface;

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
    ): ResponseInterface;

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
    ): ResponseInterface;

    /**
     * Render a template and return the result as a string.
     *
     * @param ServerRequestInterface $request        Request
     * @param ?string                $template       Template name, or null to use default for the action
     * @param array                  $params         Template parameters
     * @param array[]                $childTemplates Any child templates; an array of associative array with keys
     * 'template' and 'params'
     * @param bool                   $useLayout      Render full page with the layout?
     *
     * @return string
     */
    public function renderTemplateAsString(
        ServerRequestInterface $request,
        ?string $template = null,
        array $params = [],
        array $childTemplates = [],
        bool $useLayout = false,
    ): string;

    /**
     * Find the filename for a template.
     *
     * @param string $template Template
     *
     * @return ?string Filename, or null if not found
     */
    public function resolveTemplateFilename(string $template): ?string;
}
