<?php

/**
 * Cart search results bulk action.
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

namespace VuFind\Action\Cart;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\BulkActionHelper;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\UrlHelper;

/**
 * Cart search results bulk action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SearchResultsBulkAction extends AbstractCartAction
{
    /**
     * Process a search results bulk action.
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
        // We came in from a search, so let's remember that context so we can return to it later. However, if we came in
        // from a previous instance of this action (for example, because of a login screen), or if we have an external
        // site in the referer, we should ignore that!
        $referer = $request->getHeader('Referer')[0] ?? '';
        $bulk = $this->getUrlFromRoute('cart-searchresultsbulk');
        if ($referer && $this->getHelper(UrlHelper::class)->isLocalUrl($referer) && !str_ends_with($referer, $bulk)) {
            $this->getHelper(BulkActionHelper::class)->getCartFollowupSession()->url = $referer;
        }

        // Now forward to the requested action:
        return $this->getHelper(ForwardHelper::class)
            ->forwardTo($request, $response, 'Cart/' . $this->getCartActionFromRequest());
    }
}
