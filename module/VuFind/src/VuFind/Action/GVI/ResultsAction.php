<?php

/**
 * GVI results action.
 *
 * PHP version 8
 *
 * Copyright (C) Universitätsbibliothek Mannheim 2026.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\GVI;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\ForwardHelper;

/**
 * GVI results action.
 *
 * @category VuFind
 * @package  Action
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ResultsAction extends AbstractGVISearchAndResultsAction
{
    /**
     * Display search results with special handling for tag searches.
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
        if ($this->getQueryParam('type') == 'tag') {
            $request->getQuery()->set('fuzzy', 'true');
            return $this->getHelper(ForwardHelper::class)->forwardTo(
                $request,
                $response,
                'Tag',
                'Home'
            );
        }
        return $this->renderSearchResults($request, $response);
    }
}
