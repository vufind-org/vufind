<?php

/**
 * Online payment resolve action.
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

namespace VuFindAdmin\Action\AdminPayment;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\ContextHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\ActionHelper\ResponseHelper;
use VuFind\Db\Type\AuditEventSubtype;

/**
 * Online payment resolve action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ResolveAction extends AbstractPaymentAction
{
    /**
     * Mark a payment issue resolved.
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
        $id = (int)$this->getRouteParam('id');
        $paymentEntity = $this->paymentService->getPaymentById($id);
        $loginHelper = $this->getHelper(LoginHelper::class);
        if ($this->getHelper(FormHelper::class)->formWasSubmitted($request, 'resolve-confirm')) {
            if (!$this->csrf->isValid($this->getPostParam('csrf'))) {
                throw new \VuFind\Exception\BadRequest('error_inconsistent_parameters');
            }

            $paymentEntity->applyRegistrationResolvedStatus();
            $this->onlinePaymentManager->persistEntityWithAuditEvent(
                $paymentEntity,
                AuditEventSubtype::PaymentRegistration,
                'Payment marked as resolved'
            );

            if ($this->getHelper(ContextHelper::class)->inLightbox($request)) {
                $loginHelper->clearFollowupUrl();
                return $this->getHelper(ResponseHelper::class)->getRefreshResponse($response);
            }
            $redirectHelper = $this->getHelper(RedirectHelper::class);
            if ($url = $loginHelper->getAndClearFollowupUrl($request, true)) {
                return $redirectHelper->redirectToUrl($response, $url);
            }
            return $redirectHelper->redirectToRoute($response, 'Admin/Payment');
        }
        $loginHelper->setFollowupUrlToReferer($request);

        $templateParams = [
            'paymentEntity' => $paymentEntity,
            'statuses' => $this->getStatuses(),
        ];

        return $this->renderTemplate($request, $response, $templateParams, 'admin/payment/resolve');
    }
}
