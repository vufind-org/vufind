<?php

/**
 * List tags action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindAdmin\Action\AdminTags;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function intval;

/**
 * List tags action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ListAction extends AbstractTagsAction
{
    /**
     * List tags.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $page = intval($this->getPostOrQueryParam('page', '1', preferQuery: true));
        $results = $this->tagsService->getResourceTagsPaginator(
            $this->convertFilter($this->getPostOrQueryParam('user_id', preferQuery: true)),
            $this->convertFilter($this->getPostOrQueryParam('resource_id', preferQuery: true)),
            $this->convertFilter($this->getPostOrQueryParam('tag_id', preferQuery: true)),
            $this->getPostOrQueryParam('order', preferQuery: true),
            $page
        );
        $templateParams = [
            'uniqueTags' => $this->getUniqueTags(),
            'uniqueUsers' => $this->getUniqueUsers(),
            'uniqueResources' => $this->getUniqueResources(),
            'params' => $request->getQueryParams(),
            'page' => $page,
            'results' => $results,
        ];
        return $this->renderTemplate($request, $response, $templateParams, 'admin/tags/list');
    }
}
