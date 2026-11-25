<?php

/**
 * Payment service init action.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025-2026.
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

namespace VuFindDevTools\Action\DevToolsPaymentService;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\ResponseHelper;

/**
 * Payment service init action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class InitAction extends AbstractPaymentServiceAction
{
    /**
     * Initialize a payment request.
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
        $responseHelper = $this->getHelper(ResponseHelper::class);
        if ($this->getPostParam('signature') !== $this->calculateSignature($request->getParsedBody())) {
            return $responseHelper->getAjaxResponse(
                $response,
                'application/json',
                ['error' => 'Bad signature'],
                400
            );
        }
        $params = ['returnUrl', 'notifyUrl'];
        $session = [];
        foreach ($params as $param) {
            if (!($session[$param] = $this->getPostParam($param))) {
                return $responseHelper->getAjaxResponse(
                    $response,
                    'application/json',
                    ['error' => "Missing parameter: $param"],
                    400
                );
            }
        }
        $session['status'] = 'pending';
        $requestId = md5(microtime());
        $this->putCachedData($requestId, $session);
        $paymentUrl = str_replace(
            '/devtools/payment/init',
            '/devtools/payment/handle?requestId=' . urlencode($requestId),
            (string)$request->getUri()
        );
        return $responseHelper->getAjaxResponse(
            $response,
            'application/json',
            ['data' => compact('requestId', 'paymentUrl')]
        );
    }
}
