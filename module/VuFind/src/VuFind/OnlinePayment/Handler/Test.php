<?php

/**
 * Payment handler for VuFind's internal test service
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 * @link     http://docs.paytrail.com/ Paytrail API documentation
 */

declare(strict_types=1);

namespace VuFind\OnlinePayment\Handler;

use Laminas\Http\PhpEnvironment\Response;
use Paytrail\SDK\Util\Signature;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Exception\PaymentException;

use function is_array;

/**
 * Payment handler for VuFind's internal test service
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 * @link     http://docs.paytrail.com/ Paytrail API documentation
 */
class Test extends AbstractBase
{
    /**
     * Start payment.
     *
     * Starts payment with the payment service and redirects the user to the service.
     *
     * @param string              $returnBaseUrl Return URL
     * @param string              $notifyBaseUrl Notify URL
     * @param UserEntityInterface $user          User
     * @param array               $patron        Patron information
     * @param int                 $amount        Amount (excluding service fee)
     * @param array               $fines         Fines data
     * @param string              $paymentParam  Payment status URL parameter
     *
     * @return Response
     *
     * @throws PaymentException
     */
    public function startPayment(
        string $returnBaseUrl,
        string $notifyBaseUrl,
        UserEntityInterface $user,
        array $patron,
        int $amount,
        array $fines,
        string $paymentParam
    ): Response {
        $localIdentifier = $this->generateLocalIdentifier($patron);

        $returnUrl = $this->addQueryParams(
            $returnBaseUrl,
            [$paymentParam => $localIdentifier]
        );
        $notifyUrl = $this->addQueryParams(
            $notifyBaseUrl,
            [$paymentParam => $localIdentifier]
        );

        $paymentRequest = compact('returnUrl', 'notifyUrl');
        $paymentRequest['signature'] = $this->calculateSignature($paymentRequest);

        // Note: The test payment service does not use any actual payment-related params, so they're omitted here.

        try {
            $result = $this->getJsonResponse('init', $paymentRequest);
        } catch (\Exception $e) {
            $request = json_encode($paymentRequest, JSON_PRETTY_PRINT);
            $this->logPaymentError(
                'Exception sending payment: ' . $e->getMessage(),
                compact('user', 'patron', 'fines', 'request')
            );
            throw new PaymentException('Payment::error_payment_request_failed');
        }

        $payment = $this->createPaymentEntity(
            $localIdentifier,
            $result['data']['requestId'],
            $user,
            $patron,
            $amount,
            $fines
        );
        return $this->redirectToPayment($result['data']['paymentUrl'], $payment);
    }

    /**
     * Process the response from payment service.
     *
     * Validates the response from the payment service and marks the payment as paid as appropriate.
     * Registration with ILS happens elsewhere.
     *
     * @param PaymentEntityInterface $payment Payment
     * @param \Laminas\Http\Request  $request Request
     *
     * @return int One of the result codes defined in AbstractBase
     *
     * @throws PaymentException
     */
    public function processPaymentResponse(
        PaymentEntityInterface $payment,
        \Laminas\Http\Request $request
    ): int {
        $params = $request->getQuery()->toArray() + $request->getPost()->toArray();
        if (($params['signature'] ?? null) !== $this->calculateSignature($params)) {
            throw new PaymentException('Bad signature');
        }
        try {
            $result = $this->getJsonResponse('status', ['requestId' => $payment->getRemoteIdentifier()]);
        } catch (\Exception $e) {
            return self::PAYMENT_FAILURE;
        }
        return match ($result['data']['status']) {
            'success' => self::PAYMENT_SUCCESS,
            'failure' => self::PAYMENT_FAILURE,
            'cancel' => self::PAYMENT_CANCEL,
            default => throw new PaymentException("Unexpected status: {$result['data']['status']}")
        };
    }

    /**
     * Validate and return payment response parameters.
     *
     * @param Laminas\Http\Request $request Request
     *
     * @return array|false
     */
    public function getPaymentResponseParams($request)
    {
        $params = $request->getQuery()->toArray();

        $required = [
            'checkout-reference',
            'checkout-stamp',
            'checkout-status',
            'signature',
        ];

        foreach ($required as $name) {
            if (empty($params[$name])) {
                $this->logPaymentError(
                    "Missing or empty parameter $name in payment response",
                    compact('params')
                );
                return false;
            }
        }

        // Validate the parameters:
        try {
            Signature::validateHmac(
                $params,
                '',
                $params['signature'],
                $this->paymentConfig['secret'] ?? ''
            );
        } catch (\Exception $e) {
            $this->logPaymentError(
                'Parameter signature validation failed: ' . $e->getMessage(),
                compact('params')
            );
            return false;
        }

        return $params;
    }

    /**
     * Get a signature for the request params.
     *
     * Not secure, just for testing.
     *
     * @param array $params Request parameters
     *
     * @return string
     */
    protected function calculateSignature(array $params): string
    {
        unset($params['signature']);
        return md5('secret' . json_encode($params));
    }

    /**
     * Call the payment service
     *
     * @param string $function Payment service function to call
     * @param array  $params   POST params
     *
     * @return array Decoded response
     */
    protected function getJsonResponse(string $function, array $params): array
    {
        if (!($url = $this->paymentConfig['url'] ?? null)) {
            throw new \Exception("'url' missing from payment configuration");
        }
        $params['signature'] = $this->calculateSignature($params);
        $response = $this->httpService->postForm("$url/$function", $params);
        $result = json_decode($response->getBody(), true);
        if (!is_array($result)) {
            throw new \Exception('Payment service response invalid: ' . $response->getBody());
        }
        if ($error = $result['data']['error'] ?? null) {
            throw new \Exception("Payment service error: $error");
        }
        return $result;
    }
}
