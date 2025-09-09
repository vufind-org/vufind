<?php

/**
 * AJAX handler for registering an online payment with the ILS.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2025.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

declare(strict_types=1);

namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;

/**
 * AJAX handler for registering an online payment with the ILS.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class OnlinePaymentRegister extends AbstractOnlinePaymentAction
{
    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $localIdentifier = $params->fromPost('localIdentifier') ?? $params->fromQuery('localIdentifier');
        if (!$localIdentifier) {
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }
        $payment = $this->paymentService->getPaymentByLocalIdentifier($localIdentifier);
        if (!$payment) {
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }
        if ($payment->isRegistered()) {
            // Already registered, return success:
            return $this->formatResponse('');
        }
        if (!$payment->isRegistrationNeeded()) {
            // Bad status, return error:
            return $this->formatResponse('', self::STATUS_HTTP_ERROR);
        }
        if ($payment->isRegistrationInProgress()) {
            // Registration already in progress, return error:
            return $this->formatResponse('', self::STATUS_HTTP_ERROR);
        }

        $result = $this->onlinePaymentManager->registerPaymentWithILS($payment);
        return $this->formatResponse('', $result ? null : self::STATUS_HTTP_ERROR);
    }
}
