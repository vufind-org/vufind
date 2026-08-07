<?php

/**
 * AJAX handler to get all tags for a record as HTML.
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
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Tags\TagsService;
use VuFind\View\Renderer\TemplateRendererInterface;

/**
 * AJAX handler to get all tags for a record as HTML.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetRecordTags extends AbstractBase
{
    /**
     * Constructor.
     *
     * @param TagsService               $tagsService Tags service
     * @param ?UserEntityInterface      $user        Logged in user (or null)
     * @param TemplateRendererInterface $renderer    Template renderer
     */
    public function __construct(
        protected TagsService $tagsService,
        protected ?UserEntityInterface $user,
        protected TemplateRendererInterface $renderer,
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
        $is_me_id = $this->user?->getId();

        // Retrieve from database:
        $id = $this->getQueryParam($request, 'id');
        $source = $this->getQueryParam($request, 'source', DEFAULT_SEARCH_BACKEND);
        $tags = $this->tagsService->getRecordTags(
            $id,
            $source,
            0,
            null,
            null,
            'count',
            $is_me_id
        );

        // Build data structure for return:
        $tagList = [];
        foreach ($tags as $tag) {
            $tagList[] = [
                'tag'   => $tag['tag'],
                'cnt'   => $tag['cnt'],
                'is_me' => !empty($tag['is_me']),
            ];
        }

        $viewParams = ['tagList' => $tagList, 'loggedin' => (bool)$this->user, 'driver' => "$source|$id"];
        $html = $this->renderer->renderTemplateAsString($request, 'record/taglist', $viewParams);
        return $this->formatResponse(compact('html'));
    }
}
