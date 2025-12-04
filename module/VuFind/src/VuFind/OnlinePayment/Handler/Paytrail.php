<?php

/**
 * Payment handle for Paytrail
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2025.
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
use Paytrail\SDK\Client;
use Paytrail\SDK\Model\CallbackUrl;
use Paytrail\SDK\Model\Customer;
use Paytrail\SDK\Model\Item;
use Paytrail\SDK\Request\PaymentRequest;
use Paytrail\SDK\Request\ShopInShopPaymentRequest;
use Paytrail\SDK\Util\Signature;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Exception\PaymentException;

/**
 * Payment handle for Paytrail
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 * @link     http://docs.paytrail.com/ Paytrail API documentation
 */
class Paytrail extends AbstractBase
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
     * Mappings from fine organizations to merchant identifiers for shop-in-shop support
     *
     * @var array
     */
    protected array $organizationMerchantIdMappings = [];

    /**
     * Initialize the handler
     *
     * @param array $paymentConfig Online payment configuration
     *
     * @return void
     */
    public function init(array $paymentConfig): void
    {
        parent::init($paymentConfig);
        $this->organizationMerchantIdMappings
            = $this->parseMappings($this->paymentConfig['organizationMerchantIdMappings'] ?? '');
    }

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
        $email = $user->getEmail() ?? $patron['email'] ?? null;
        if (!$email) {
            throw new PaymentException('email_address_missing');
        }
        $localIdentifier = $this->generateLocalIdentifier($patron);

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

        $customer = (new Customer())
            ->setFirstName($user->getFirstname() ?: null)
            ->setLastName($user->getLastname() ?: null)
            ->setEmail(trim($user->getEmail()));

        $language = $this->languageMap[$this->getCurrentLanguageCode()] ?? 'EN';

        $paymentRequest = $this->organizationMerchantIdMappings ? new ShopInShopPaymentRequest() : new PaymentRequest();
        $paymentRequest
            ->setStamp($localIdentifier)
            ->setRedirectUrls($returnUrls)
            ->setCallbackUrls($callbackUrls)
            ->setReference("$localIdentifier - {$patron['cat_username']}")
            ->setCurrency($this->getCurrencyCode())
            ->setLanguage($language)
            ->setAmount($amount + $this->getServiceFee())
            ->setCustomer($customer);

        // Map fines to items:
        $items = [];
        foreach ($fines as $fine) {
            $fineOrg = $fine['organization'] ?? '';

            if (null === $code = $this->getFineProductCode($fine)) {
                // Skip item if there's no product code
                continue;
            }
            $code = mb_substr($code, 0, 100, 'UTF-8');

            $fineDesc = $this->getFineDescription($fine, 100);
            $itemId = $fine['fineId'] ?? $fine['id'] ?? null;
            $item = (new Item())
                ->setDescription($fineDesc)
                ->setProductCode($code)
                ->setUnitPrice((int)round($fine['balance']))
                ->setUnits(1)
                ->setVatPercentage((float)($fine['taxPercent'] ?? 0) / 100.0)
                ->setStamp(mb_substr("$localIdentifier $itemId", 0, 200, 'UTF-8'))
                ->setReference(mb_substr($itemId, 0, 200, 'UTF-8'));

            if ($itemMerchant = $this->organizationMerchantIdMappings[$fineOrg] ?? null) {
                $item->setMerchant($itemMerchant);
            }

            $items[] = $item;
        }
        if (($serviceFee = $this->getServiceFee()) && ($serviceFeeProductCode = $this->getServiceFeeProductCode())) {
            $item = (new Item())
                ->setDescription($this->translator->translate('Payment::Service Fee'))
                ->setProductCode($serviceFeeProductCode)
                ->setUnitPrice($serviceFee)
                ->setUnits(1)
                ->setVatPercentage((float)($this->getServiceFeeTaxRate() ?? 0) / 100.0);
            $items[] = $item;
        }
        $paymentRequest->setItems($items ?: null);

        try {
            $paymentResponse = $this->initClient()->createPayment($paymentRequest);
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
     * Initialize the Paytrail client
     *
     * @return Client
     */
    protected function initClient(): Client
    {
        foreach (['merchantId', 'secret'] as $req) {
            if (!isset($this->paymentConfig[$req])) {
                $this->logPaymentError("Missing payment configuration $req");
                throw new \Exception('Missing payment configuration');
            }
        }

        return new Client(
            (int)$this->paymentConfig['merchantId'],
            $this->paymentConfig['secret'],
            $this->config['Site']['generator'] ?? 'VuFind'
        );
    }
}
