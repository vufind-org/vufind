<?php

/**
 * Cart home action.
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
use VuFind\Action\ListItemSelectionTrait;
use VuFind\ActionHelper\BulkActionHelper;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;

/**
 * Cart home action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HomeAction extends AbstractCartAction implements TranslatorAwareInterface
{
    use ListItemSelectionTrait;
    use TranslatorAwareTrait;

    /**
     * Show cart.
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
        // Bail out if cart is disabled.
        if (!$this->cart->isActive()) {
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'home');
        }

        // If a user is coming directly to the cart, we should clear out any
        // existing context information to prevent weird, unexpected workflows
        // caused by unusual user behavior.
        $this->followupHelper->retrieveAndClear('cartAction');
        $this->followupHelper->retrieveAndClear('cartIds');

        $ids = $this->getSelectedIds();

        // Add items if necessary:
        if ('' !== $this->getPostParam('empty', '')) {
            $this->cart->emptyCart();
        } elseif ('' !== $this->getPostParam('delete', '')) {
            if (empty($ids)) {
                return $this->getHelper(BulkActionHelper::class)
                    ->redirectToSource($request, $response, 'error', 'bulk_noitems_advice');
            } else {
                $this->cart->removeItems($ids);
            }
        } elseif ('' !== $this->getPostParam('add', '')) {
            if (empty($ids)) {
                return $this->getHelper(BulkActionHelper::class)
                    ->redirectToSource($request, $response, 'error', 'bulk_noitems_advice');
            } else {
                $addItems = $this->cart->addItems($ids);
                if (!$addItems['success']) {
                    $msg = $this->translate('bookbag_full_msg') . '. '
                        . $addItems['notAdded'] . ' '
                        . $this->translate('items_already_in_bookbag') . '.';
                    $this->getHelper(FlashMessagesHelper::class)->addInfoMessage($msg);
                }
            }
        }
        // Using the cart/cart template for the cart/home action is a legacy of
        // an earlier controller design; we may want to rename the template for
        // clarity, but right now we are retaining the old template name for
        // backward compatibility.
        return $this->renderTemplate($request, $response, template: 'cart/cart');
    }
}
