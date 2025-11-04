<?php

/**
 * Turku Payment API client
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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

namespace Finna\OnlinePayment\Handler\Connector\TurkuPaymentAPI;

use Paytrail\SDK\Request\PaymentRequest;
use Paytrail\SDK\Response\PaymentResponse;
use Psr\Log\LoggerInterface;
use VuFindHttp\HttpService;

/**
 * Turku Payment API client
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Client extends \Paytrail\SDK\Client
{
    /**
     * OId for authorized users
     *
     * @var string
     */
    protected $oId;

    /**
     * Use different merchant id as the normal value is declared as an int
     *
     * @var string
     */
    protected $merchantIdString;

    /**
     * Timestamp generated
     *
     * @var string
     */
    protected $timeStamp;

    /**
     * Use overwritable version of the api endpoint
     *
     * @var string
     */
    protected $apiEndpoint;

    /**
     * Request body
     *
     * @param array
     */
    protected $requestBody;

    /**
     * Client constructor.
     *
     * @param int             $merchantId       The merchant.
     * @param string          $secretKey        The secret key.
     * @param string          $platformName     Platform name.
     * @param HttpService     $http             HTTP service.
     * @param LoggerInterface $logger           Logger.
     * @param string          $baseUrl          Service base url.
     * @param string          $merchantIdString Merchant id as a string.
     * @param string          $oId              oId.
     */
    public function __construct(
        int $merchantId,
        string $secretKey,
        string $platformName,
        HttpService $http,
        LoggerInterface $logger,
        string $baseUrl,
        string $merchantIdString,
        string $oId
    ) {
        parent::__construct($merchantId, $secretKey, $platformName, $http, $logger, $baseUrl);
        $this->setMerchantIdString($merchantIdString);
        $this->setOId($oId);
        $this->generateTimeStamp();
    }

    /**
     * Get the merchant id string.
     *
     * @return string
     */
    public function getMerchantIdString(): ?string
    {
        return $this->merchantIdString;
    }

    /**
     * Set the merchant id as a string.
     *
     * @param int $merchantId The merchant id.
     *
     * @return void
     */
    public function setMerchantIdString(string $merchantId): void
    {
        $this->merchantIdString = $merchantId;
    }

    /**
     * Get the oId.
     *
     * @return ?string
     */
    public function getOId(): ?string
    {
        return $this->oId;
    }

    /**
     * Set the oId.
     *
     * @param string $oId The oId.
     *
     * @return void
     */
    public function setOId(string $oId): void
    {
        $this->oId = $oId;
    }

    /**
     * Get the timestamp.
     *
     * @return ?string
     */
    public function getTimeStamp(): ?string
    {
        return $this->timeStamp;
    }

    /**
     * Generate a timestamp.
     *
     * @return void
     */
    public function generateTimeStamp(): void
    {
        $this->timeStamp = gmdate("Y-m-d\TH:i:s\Z");
    }

    /**
     * Create a payment request.
     *
     * @param PaymentRequest $payment A payment class instance.
     *
     * @return PaymentResponse
     * @throws HmacException       Thrown if HMAC calculation fails for responses.
     * @throws ValidationException Thrown if payment validation fails.
     * @throws \Exception          Thrown if the HTTP request fails.
     */
    public function createPayment(PaymentRequest $payment): PaymentResponse
    {
        $this->validateRequestItem($payment);
        // Create request
        $this->requestBody = json_encode($payment, JSON_UNESCAPED_SLASHES);
        $headers = $this->getHeaders('POST', null, null);

        $response = $this->http_client->request(
            'POST',
            '',
            [
                'body' => $this->requestBody,
                'headers' => $headers,
            ]
        );
        if (!$response) {
            throw new \Exception('Request failed');
        }

        $body = $response->getBody();
        // Handle header data and validate authorization field:
        $responseHeaders = $response->getHeaders();
        TurkuSignature::validateHash(
            [],
            $body,
            $responseHeaders['authorization'][0] ?? '',
            $this->secretKey,
            $responseHeaders['x-turku-ts'][0],
            $this->platformName
        );
        // Create response:
        $decoded = json_decode($body);
        $paymentResponse = (new PaymentResponse())
            ->setTransactionId($decoded->transactionId ?? null)
            ->setHref($decoded->href ?? null)
            ->setProviders($decoded->providers ?? null);

        return $paymentResponse;
    }

    /**
     * Format request headers.
     *
     * @param string  $method                 The request method. GET or POST.
     * @param ?string $transactionId          Paytrail transaction ID when accessing
     *                                        single transaction not required
     *                                        for a new payment request.
     * @param ?string $checkoutTokenizationId Paytrail tokenization ID
     *                                        or getToken request
     *
     * @return array
     * @throws \Exception
     */
    protected function getHeaders(
        string $method,
        ?string $transactionId = null,
        ?string $checkoutTokenizationId = null
    ): array {
        return [
            'X-TURKU-SP' => $this->platformName,
            'X-TURKU-TS' =>  $this->getTimeStamp(),
            'X-TURKU-OID' => $this->getOId(),
            'X-MERCHANT-ID' => $this->getMerchantIdString(),
            'Content-Type' => 'application/json',
            'Authorization' => TurkuSignature::calculcateHash(
                [],
                $this->requestBody,
                $this->secretKey,
                $this->timeStamp,
                $this->platformName
            ),
        ];
    }
}
