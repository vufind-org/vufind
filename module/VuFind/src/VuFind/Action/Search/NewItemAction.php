<?php

/**
 * New item search form action.
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

namespace VuFind\Action\Search;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\Search\Base\Results;

use function in_array;

/**
 * New item search form action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class NewItemAction extends AbstractNewItemAction
{
    /**
     * Display new item search form.
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
        if (in_array($this->newItemsHelper->getMethod(), ['disabled', 'ils'])) {
            return $this->renderNotFoundPage($request, $response);
        }

        // Search parameters set?  Process results.
        if ($this->getQueryParam('range') !== null) {
            return $this->getHelper(ForwardHelper::class)->forwardTo($request, $response, 'Search/NewItemResults');
        }

        $templateParams = $this->createTemplateParams(
            [
                'defaultSort' => $this->newItemsHelper->getDefaultSort(),
                'ranges' => $this->newItemsHelper->getRanges(),
            ]
        );

        if ($this->newItemsHelper->includeFacets()) {
            $templateParams['options'] = $this->getOptionsForClass();
            $templateParams = $this->addFacetDetails($templateParams, 'NewItems');
        }

        return $this->renderTemplate($request, $response, $templateParams);
    }
}
