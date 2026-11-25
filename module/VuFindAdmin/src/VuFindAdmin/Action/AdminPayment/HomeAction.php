<?php

/**
 * Online payment home action.
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
use VuFind\Db\Type\PaymentStatus;

/**
 * Online payment home action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HomeAction extends AbstractPaymentAction
{
    /**
     * Display online payment home page.
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
        $payments = $this->paymentService->getPaymentPaginator(
            $this->getStatusFilter(),
            $this->getStringFilter('local_identifier'),
            $this->getStringFilter('remote_identifier'),
            $this->getStringFilter('source_ils'),
            $this->getStringFilter('cat_username'),
            $this->getDateFilter('created_from'),
            $this->getDateFilter('created_until'),
            $this->getDateFilter('paid_from'),
            $this->getDateFilter('paid_until'),
            (int)$this->getPostOrQueryParam('page', 1)
        );
        $templateParams = [
            'payments' => $payments,
            'statuses' => $this->getStatuses(),
            'defaultStatuses' => $this->getDefaultSelectedStatuses(),
            'sourceIlsList' => $this->paymentService->getUniqueSourceIlsList(),
            'resolvableStatuses' => $this->getResolvableStatuses(),
            'params' => $request->getQueryParams() + $request->getParsedBody(),
        ];

        return $this->renderTemplate($request, $response, $templateParams, 'admin/payment/home');
    }

    /**
     * Get a status set filter.
     *
     * @return array
     */
    protected function getStatusFilter(): array
    {
        $statuses = [];
        foreach ((array)($this->getPostOrQueryParam('statuses') ?? $this->getDefaultSelectedStatuses()) as $current) {
            if (null !== ($status = PaymentStatus::tryFrom((int)$current))) {
                $statuses[] = $status;
            }
        }
        return $statuses;
    }
}
