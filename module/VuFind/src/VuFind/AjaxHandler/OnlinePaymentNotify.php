<?php

/**
 * External payment notification handler for online payment.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
use VuFind\Controller\AjaxController;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Http\PhpEnvironment\Request;

use function assert;

/**
 * External payment notification handler for online payment.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class OnlinePaymentNotify extends AbstractOnlinePaymentAction
{
    /**
     * Handle a request.
     *
     * Note: This handler does not register the payment with the ILS since that happens in the response handler or
     * online payment monitor.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $controller = $params->getController();
        assert($controller instanceof AjaxController);
        $request = $controller->getRequest();
        assert($request instanceof Request);

        $this->logger->debug('Online payment notify handler called. Request: ' . (string)$request);

        if (null === ($localIdentifier = $request->getQuery('local_payment_id'))) {
            $this->logError(
                'Error processing payment: local_payment_id not provided. Query: '
                . $request->getQuery()->toString()
                . ', post parameters: ' . $request->getPost()->toString()
            );
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }
        if (!($payment = $this->paymentService->getPaymentByLocalIdentifier($localIdentifier))) {
            $this->logError(
                "Error processing payment: payment $localIdentifier not found"
            );
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }

        $this->addPaymentEvent($payment, AuditEventSubtype::PaymentNotifyHandler, 'Handler called');

        if ($payment->isRegistered()) {
            $this->addPaymentEvent($payment, AuditEventSubtype::PaymentNotifyHandler, 'Payment already registered');
            // Already registered, treat as success:
            return $this->formatResponse('');
        }

        try {
            $this->onlinePaymentManager->processPaymentHandlerResponse($payment, $request, true);
        } catch (\Exception $e) {
            $this->logError(
                'Error processing payment notification for ' . $payment->getSourceIls() . ", payment $localIdentifier"
            );
            $this->logException($e);
            $this->addPaymentEvent(
                $payment,
                AuditEventSubtype::PaymentNotifyHandler,
                'Exception processing request',
                ['error' => $e->getMessage()]
            );
            return $this->formatResponse('', self::STATUS_HTTP_ERROR);
        }

        return $this->formatResponse('');
    }
}
