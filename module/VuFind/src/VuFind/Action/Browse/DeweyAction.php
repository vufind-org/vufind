<?php

/**
 * Browse Dewey action.
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
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Browse;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Browse Dewey action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DeweyAction extends AbstractBrowseAction
{
    /**
     * Browse Dewey.
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
        $templateParams = $this->createTemplateParams(
            'Dewey',
            [
                'dewey_flag' => 1,
                'secondaryParams' => [
                    'query_field' => 'dewey-tens',
                    'facet_field' => 'dewey-ones',
                ],
                'searchParams' => ['sort' => 'dewey-sort'],
            ]
        );

        [$templateParams['filter'], $hundredsList] = $this->getSecondaryList('Dewey', 'dewey');
        $templateParams['categoryList'] = [];
        foreach ($hundredsList as $dewey) {
            $templateParams['categoryList'][$dewey['value']] = [
                'text' => $dewey['displayText'],
                'count' => $dewey['count'],
            ];
        }
        if ($findBy = $this->getQueryParam('findby')) {
            $secondaryList = $this->quoteValues(
                $this->getFacetList(
                    'dewey-tens',
                    'dewey-hundreds',
                    'count',
                    $findBy
                )
            );
            foreach (array_keys($secondaryList) as $index) {
                $secondaryList[$index]['value'] .=
                    ' AND dewey-hundreds:'
                    . $findBy;
            }
            $templateParams['secondaryList'] = $secondaryList;
        }
        return $this->performSearch('Dewey', $templateParams);
    }
}
