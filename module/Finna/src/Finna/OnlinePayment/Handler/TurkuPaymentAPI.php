<?php

/**
 * Turku Payment API handler
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2024.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\OnlinePayment\Handler;

use Finna\OnlinePayment\Handler\Connector\TurkuPaymentAPI\Client;
use Finna\OnlinePayment\Handler\Connector\TurkuPaymentAPI\Item;
use Finna\OnlinePayment\Handler\Connector\TurkuPaymentAPI\PaymentRequest;
use Finna\OnlinePayment\Handler\Connector\TurkuPaymentAPI\TurkuSignature;
use Laminas\Http\PhpEnvironment\Response;
use Paytrail\SDK\Model\CallbackUrl;
use Paytrail\SDK\Model\Customer;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Exception\PaymentException;

/**
 * Turku Payment API handler
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class TurkuPaymentAPI extends \VuFind\OnlinePayment\Handler\AbstractBase
{
    /**
     * Mappings from VuFind language codes to Paytrail
     *
     * @var array
     */
    protected $languageMap = [
        'fi' => 'FI',
        'sv' => 'SV',
        'en' => 'EN',
    ];

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
        $patronId = $patron['cat_username'];
        $localIdentifier = $this->generateLocalIdentifier($patronId);

        $returnUrl = $this->addQueryParams(
            $returnBaseUrl,
            [$paymentParam => $localIdentifier]
        );
        $notifyUrl = $this->addQueryParams(
            $notifyBaseUrl,
            [$paymentParam => $localIdentifier]
        );

        $returnUrls = (new CallbackUrl())
            ->setSuccess($returnUrl)
            ->setCancel($returnUrl);

        $callbackUrls = (new CallbackUrl())
            ->setSuccess($notifyUrl)
            ->setCancel($notifyUrl);

        // Use email from the ILS as default and use the one stored in Finna as a
        // fallback:
        $customer = (new Customer())
            ->setEmail(trim(($patron['email'] ?? '') ?: trim($user->getEmail())));

        $language = $this->languageMap[$this->getCurrentLanguageCode()] ?? 'FI';
        $sapOrganization = [
            'sapSalesOrganization' => $this->config['sapSalesOrganization'] ?? '',
            'sapDistributionChannel' => $this->config['sapDistributionChannel'] ?? '',
            'sapSector' => $this->config->sapSector ?? '',
        ];
        $reference = preg_replace('/\PL/u', '', "{$localIdentifier}{$patronId}");
        $serviceFee = $this->getServiceFee();
        $paymentRequest = (new PaymentRequest())
            ->setUsePricesWithoutVat(true)
            ->setSapOrganizationDetails($sapOrganization)
            ->setStamp($localIdentifier)
            ->setRedirectUrls($returnUrls)
            ->setCallbackUrls($callbackUrls)
            ->setReference($reference)
            ->setCurrency('EUR')
            ->setLanguage($language)
            ->setAmount(round($amount) + $serviceFee)
            ->setCustomer($customer);

        // Payment description in $this->config['paymentDescription'] is not supported

        $items = [];
        $sapProduct = [
            'sapCode' => $this->config['sapCode'] ?? '',
            'sapOfficeCode' => $this->config['sapOfficeCode'] ?? '',
        ];
        foreach ($fines as $fine) {
            $code = $this->getFineProductCode($fine);
            $code = mb_substr($code, 0, 100, 'UTF-8');
            $fineDesc = $this->getFineDescription($fine, 1000);
            $item = (new Item())
                ->setSapProduct($sapProduct)
                ->setDescription($fineDesc)
                ->setProductCode($code)
                ->setUnitPrice(round($fine['balance']))
                ->setUnits(1)
                ->setVatPercentage(0);

            $items[] = $item;
        }
        if ($serviceFee) {
            $item = (new Item())
                ->setSapProduct($sapProduct)
                ->setDescription($this->translator->translate('Payment::Service Fee'))
                ->setProductCode($this->getServiceFeeProductCode())
                ->setUnitPrice($serviceFee)
                ->setUnits(1)
                ->setVatPercentage(0);

            $items[] = $item;
        }
        $paymentRequest->setItems($items);

        try {
            $paymentResponse = $this->initClient()->createPayment($paymentRequest);
        } catch (\Exception $e) {
            $request = json_encode($paymentRequest, JSON_PRETTY_PRINT);
            $this->logPaymentError(
                'Exception sending payment: ' . $e->getMessage(),
                compact('user', 'patron', 'fines', 'request')
            );
            if (mb_strtolower($e->getMessage()) === 'email is empty') {
                throw new PaymentException('Payment::email_address_missing');
            }
            throw new PaymentException('Payment::error_payment_request_failed');
        }

        $payment = $this->createPaymentEntity(
            $localIdentifier,
            null,
            $user,
            $patron,
            $amount,
            $fines
        );
        return $this->redirectToPayment($paymentResponse->getHref(), $payment);
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
        if (!($params = $this->getPaymentResponseParams($request))) {
            throw new PaymentException('Could not get payment response params');
        }

        // Make sure the transaction IDs match:
        if ($payment->getLocalIdentifier() !== $params['checkout-stamp']) {
            throw new PaymentException('Payment stamp mismatch');
        }

        $status = $params['checkout-status'];
        switch ($status) {
            case 'ok':
                return self::PAYMENT_SUCCESS;
            case 'fail':
                return self::PAYMENT_CANCEL;
            case 'new':
            case 'pending':
            case 'delayed':
                return self::PAYMENT_PENDING;
        }

        $this->logPaymentError("Unknown status $status");
        $this->addPaymentEvent(
            $payment,
            AuditEventSubtype::PaymentResponseHandler,
            'Received unknown status',
            ['status' => $status]
        );
        return self::PAYMENT_FAILURE;
    }

    /**
     * Validate and return payment response parameters.
     *
     * @param \Laminas\Http\Request $request Request
     *
     * @return array|false
     */
    public function getPaymentResponseParams($request)
    {
        $params = [];
        $required = [];
        $body = '';
        // Payment response is a get request and notify is a post request
        if ($request->isGet()) {
            $params = $request->getQuery()->toArray();
            $required = [
                'checkout-amount',
                'checkout-reference',
                'checkout-stamp',
                'checkout-status',
                'checkout-provider',
                'checkout-transaction-id',
                'X-TURKU-SP',
                'X-TURKU-TS',
                'Authorization',
            ];
        } elseif ($request->isPost()) {
            $params = $request->getHeaders()->toArray();
            $body = $request->getContent();
            $required = [
                'X-Turku-Sp',
                'X-Turku-Ts',
                'Authorization',
            ];
        } else {
            $this->logPaymentError(
                'The request was not POST or GET'
            );
            return false;
        }
        foreach ($required as $name) {
            if (empty($params[$name])) {
                $this->logPaymentError(
                    "missing or empty parameter $name in payment response",
                    compact('params')
                );
                return false;
            }
        }
        // Validate the parameters:
        try {
            TurkuSignature::validateHash(
                $params,
                $body,
                $params['Authorization'],
                $this->config['secret'] ?? '',
                $params['X-TURKU-TS'] ?? $params['X-Turku-Ts'] ?? '',
                $this->config['platformName']
            );
        } catch (\Exception $e) {
            $this->logPaymentError(
                'parameter Authorization validation failed',
                compact('params')
            );
            return false;
        }
        // For POST notify request, the params needed to check are in the body
        return $request->isGet() ? $params : json_decode($body, true);
    }

    /**
     * Initialize the Paytrail client
     *
     * @return Client
     */
    protected function initClient(): Client
    {
        foreach (['merchantId', 'secret', 'oId', 'url', 'platformName'] as $req) {
            if (!isset($this->config[$req])) {
                $this->logPaymentError("Missing payment configuration $req");
                throw new \Exception('Missing payment configuration');
            }
        }

        return new Client(
            0,
            $this->config['secret'],
            'Finna',
            $this->httpService,
            $this->logger,
            $this->config['url'],
            $this->config['merchantId'],
            $this->config['oId']
        );
    }
}
