<?php

/**
 * Cart MyResearch bulk action.
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

/**
 * Cart MyResearch bulk action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class MyResearchBulkAction extends AbstractCartAction
{
    /**
     * Process a MyResearch bulk action.
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
        // We came in from the MyResearch section -- let's remember which list (if any) we came from so we can redirect
        // there when we're done:
        $listID = $this->getPostParam('listID');
        $this->getHelper(BulkActionHelper::class)->getCartFollowupSession()->url = empty($listID)
            ? $this->getUrlFromRoute('myresearch-favorites')
            : $this->getUrlFromRoute('userList', ['id' => $listID]);

        // Now forward to the requested controller/action:
        $action = null;
        if ('' !== $this->getPostParam('email', '')) {
            $action = 'Cart/Email';
        } elseif ('' !== $this->getPostParam('print', '')) {
            $action = 'Cart/PrintCart';
        } elseif ('' !== $this->getPostParam('delete', '')) {
            $action = 'MyResearch/Delete';
        } elseif ('' !== $this->getPostParam('add', '')) {
            $action = 'Cart/Home';
        } elseif ('' !== $this->getPostParam('export', '')) {
            $action = 'Cart/Export';
        } else {
            if (!($action = $this->followupHelper->retrieveAndClear('cartAction', null))) {
                throw new \Exception('Unrecognized bulk action.');
            }
            $action = "Cart/$action";
        }
        return $this->getHelper(ForwardHelper::class)->forwardTo($request, $response, $action);
    }
}
