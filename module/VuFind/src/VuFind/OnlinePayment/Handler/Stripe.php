<?php

/**
 * Payment handler for Stripe
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
use Psr\Log\LoggerAwareInterface;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe as StripeStripe;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Exception\PaymentException;
use VuFind\I18n\Translator\TranslatorAwareInterface;

/**
 * Payment handler for Stripe
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 * @link     http://docs.paytrail.com/ Paytrail API documentation
 */
class Stripe extends AbstractBase implements
    HandlerInterface,
    LoggerAwareInterface,
    TranslatorAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\OnlinePayment\OnlinePaymentEventTrait;

    /**
     * Mappings from fine tax percentages to tax codes
     *
     * @var array
     */
    protected array $taxPercentToTaxCodeMappings = [];

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
        $this->taxPercentToTaxCodeMappings
            = $this->parseMappings($this->paymentConfig['taxPercentToTaxCodeMappings'] ?? '');
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
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
        if (!($apiKey = $this->paymentConfig['apiKey'] ?? null)) {
            throw new PaymentException('Configuration missing: apiKey');
        }
        StripeStripe::setApiKey($apiKey);

        $localIdentifier = $this->generateLocalIdentifier($patron);

        $returnUrl = $this->addQueryParams(
            $returnBaseUrl,
            [$paymentParam => $localIdentifier]
        );

        // Map fines to items:
        $lineItems = [];
        foreach ($fines as $fine) {
            if (null === ($code = $this->getFineProductCode($fine))) {
                $this->logPaymentError('Fine type could not be determined: ' . var_export($fine, true));
                throw new PaymentException('Fine type could not be determined');
            }
            $code = mb_substr($code, 0, 100, 'UTF-8');

            $description = $this->getFineDescription($fine, 255);
            $taxCode = $this->getTaxCode($fine['taxPercent'] ?? 0);

            $item = [
                'price_data' => [
                    'currency' => $this->getCurrencyCode(),
                    'product_data' => [
                        'name' => $code,
                        'description' => $description,
                    ],
                    'unit_amount' => round($fine['balance']),
                ],
                'quantity' => 1,
            ];
            if (null !== $taxCode) {
                $item['price_data']['product_data']['tax_code'] = $taxCode;
            }

            $lineItems[] = $item;
        }
        if ($serviceFee = $this->getServiceFee()) {
            $item = [
                'price_data' => [
                    'currency' => $this->getCurrencyCode(),
                    'product_data' => [
                        'name' => $this->getServiceFeeProductCode() ?? $this->getDefaultProductCode(),
                        'description' => $this->translator->translate('Payment::Service Fee'),
                    ],
                    'unit_amount' => $serviceFee,
                ],
                'quantity' => 1,
            ];
            if (null !== ($taxCode = $this->getTaxCode($this->getServiceFeeTaxRate() ?? 0))) {
                $item['price_data']['product_data']['tax_code'] = $taxCode;
            }
            $lineItems[] = $item;
        }

        $sessionSettings = [
            'mode' => 'payment',
            'client_reference_id' => $localIdentifier,
            'success_url' => $returnUrl,
            'cancel_url' => $returnUrl,
            'line_items' => $lineItems,
            'locale' => $this->getCurrentLocale(),
            'customer_creation' => 'if_required',
        ];
        if ($email = $user->getEmail()) {
            $sessionSettings['customer_email'] = $email;
        }

        try {
            $stripeSession = Session::create($sessionSettings);
        } catch (ApiErrorException $e) {
            $request = json_encode($sessionSettings, JSON_PRETTY_PRINT);
            $this->logPaymentError(
                'Exception creating a Stripe session: ' . $e->getMessage(),
                compact('user', 'patron', 'fines', 'request')
            );
            throw new PaymentException('An error has occurred');
        }

        $payment = $this->createPaymentEntity(
            $localIdentifier,
            $stripeSession->id,
            $user,
            $patron,
            $amount,
            $fines
        );
        return $this->redirectToPayment($stripeSession->url, $payment);
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function processPaymentResponse(
        PaymentEntityInterface $payment,
        \Laminas\Http\Request $request
    ): int {
        if (!($apiKey = $this->paymentConfig['apiKey'] ?? null)) {
            throw new PaymentException('Configuration missing: apiKey');
        }
        StripeStripe::setApiKey($apiKey);
        try {
            $stripeSession = Session::retrieve($payment->getRemoteIdentifier());
        } catch (ApiErrorException $e) {
            return self::PAYMENT_FAILURE;
        }
        return $stripeSession->payment_status === 'paid' ? self::PAYMENT_SUCCESS : self::PAYMENT_CANCEL;
    }

    /**
     * Get tax code for a tax percent
     *
     * @param int $taxPercent Tax percent in 1/100ths of a percent
     *
     * @return ?string Tax code, or null if not defined
     */
    protected function getTaxCode(int $taxPercent): ?string
    {
        return $this->taxPercentToTaxCodeMappings[$taxPercent] ?? null;
    }
}
