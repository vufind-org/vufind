<?php

/**
 * "Get Cookie Consent Overlay" AJAX handler.
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
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Psr\Http\Message\ServerRequestInterface;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Renderer\TemplateRendererInterface;

/**
 * "Get Cookie Consent Overlay" AJAX handler.
 *
 * Get contents for the cookie consent overlay.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetCookieConsent extends AbstractBase
{
    /**
     * Constructor.
     *
     * @param TemplateRendererInterface $renderer Template renderer
     */
    #[Autowire]
    public function __construct(
        protected TemplateRendererInterface $renderer
    ) {
    }

    /**
     * Handle a request.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(ServerRequestInterface $request): array
    {
        $html = $this->renderer->renderTemplateAsString($request, 'CookieConsent/cookie-consent-overlay.phtml');
        return $this->formatResponse(compact('html'));
    }
}
