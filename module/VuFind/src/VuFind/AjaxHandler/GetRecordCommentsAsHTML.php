<?php

/**
 * AJAX handler to get list of comments for a record as HTML.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Psr\Http\Message\ServerRequestInterface;
use VuFind\Record\Loader;
use VuFind\View\Renderer\TemplateRendererInterface;

/**
 * AJAX handler to get list of comments for a record as HTML.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetRecordCommentsAsHTML extends AbstractBase
{
    /**
     * Constructor.
     *
     * @param Loader                    $loader   Record loader
     * @param TemplateRendererInterface $renderer Template renderer
     */
    public function __construct(
        protected Loader $loader,
        protected TemplateRendererInterface $renderer
    ) {
        parent::__construct(null);
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
        $driver = $this->loader->load(
            $this->getQueryParam($request, 'id'),
            $this->getQueryParam($request, 'source', DEFAULT_SEARCH_BACKEND)
        );
        $html = $this->renderer->renderTemplateAsString($request, 'record/comments-list.phtml', compact('driver'));
        return $this->formatResponse(compact('html'));
    }
}
