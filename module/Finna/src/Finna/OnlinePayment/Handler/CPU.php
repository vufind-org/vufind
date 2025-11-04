<?php

/**
 * CPU payment handler
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2025.
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
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\OnlinePayment\Handler;

use Finna\OnlinePayment\Handler\Connector\Cpu\Client;
use Finna\OnlinePayment\Handler\Connector\Cpu\Payment;
use Finna\OnlinePayment\Handler\Connector\Cpu\Product;
use Laminas\Http\PhpEnvironment\Response;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Exception\PaymentException;

use function count;
use function in_array;
use function intval;

/**
 * CPU payment handler module.
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CPU extends \VuFind\OnlinePayment\Handler\AbstractBase
{
    public const CPU_STATUS_SUCCESS = 1;
    public const CPU_STATUS_CANCELLED = 0;
    public const CPU_STATUS_PENDING = 2;
    public const CPU_STATUS_ID_EXISTS = 97;
    public const CPU_STATUS_ERROR = 98;
    public const CPU_STATUS_INVALID_REQUEST = 99;

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

        $paymentRequest = new Payment($localIdentifier);
        $email = trim($user->getEmail());
        if ($email) {
            $paymentRequest->Email = $email;
        }
        $lastname = $user->getLastname();
        if (!empty($user->getFirstname())) {
            $paymentRequest->FirstName = $user->getFirstname();
        } else {
            // We don't have both names separately, try to extract first name from
            // last name.
            if (strpos($lastname, ',') > 0) {
                // Lastname, Firstname
                [$lastname, $firstname] = explode(',', $lastname, 2);
            } else {
                // First Middle Last
                if (preg_match('/^(.*) (.*?)$/', $lastname, $matches)) {
                    $firstname = $matches[1];
                    $lastname = $matches[2];
                } else {
                    $firstname = '';
                }
            }
            $lastname = trim($lastname);
            $firstname = trim($firstname);
            $paymentRequest->FirstName = empty($firstname) ? 'ei tietoa' : $firstname;
        }
        $paymentRequest->LastName = empty($lastname) ? 'ei tietoa' : $lastname;

        $lang = $this->getCurrentLanguageCode();
        if (!empty($this->config['supportedLanguages'])) {
            $languageMappings = [];
            foreach (explode(':', $this->config['supportedLanguages']) as $item) {
                $parts = explode('=', $item, 2);
                if (count($parts) != 2) {
                    continue;
                }
                $languageMappings[trim($parts[0])] = trim($parts[1]);
            }
            if (isset($languageMappings[$lang])) {
                $paymentRequest->Language = $languageMappings[$lang];
            }
        }

        $paymentRequest->Description = $this->config['paymentDescription'] ?? '';

        $paymentRequest->ReturnAddress = $returnUrl;
        $paymentRequest->NotificationAddress = $notifyUrl;

        foreach ($fines as $fine) {
            $fineType = $fine['fine'] ?? '';
            $fineOrg = $fine['organization'] ?? '';
            $fineDesc = $this->getFineDescription($fine, 100);
            if ($fineDesc) {
                // Get rid of characters that cannot be converted to ISO-8859-1 since
                // CPU apparently can't handle them properly.
                $fineDesc = iconv(
                    'ISO-8859-1',
                    'UTF-8',
                    iconv('UTF-8', 'ISO-8859-1//IGNORE', $fineDesc)
                );
                // Remove ' since it causes the string to be truncated
                $fineDesc = str_replace("'", ' ', $fineDesc);
                // Sanitize before limiting the length, otherwise the sanitization
                // process may blow the string through the limit
                $fineDesc = Client::sanitize($fineDesc);
                // Make sure that description length does not exceed CPU max limit of
                // 100 characters.
                $fineDesc = mb_substr($fineDesc, 0, 100, 'UTF-8');
            }

            if (null === $code = $this->getFineProductCode($fine)) {
                // Skip item if there's no product code
                continue;
            }
            $code = mb_substr($code, 0, 25, 'UTF-8');
            $product = new Product(
                $code,
                1,
                round($fine['balance']),
                $fineDesc ?: null
            );
            $paymentRequest = $paymentRequest->addProduct($product);
        }
        if ($serviceFee = $this->getServiceFee()) {
            $product = new Product(
                $this->getServiceFeeProductCode(),
                1,
                $serviceFee,
                $this->translator->translate('Payment::Service Fee')
            );
            $paymentRequest = $paymentRequest->addProduct($product);
        }

        if (!($module = $this->initCpu())) {
            $this->logPaymentError(
                'error initializing CPU online payment',
                compact('user', 'patron', 'fines')
            );
            throw new PaymentException('Payment::error_payment_request_failed');
        }

        try {
            $response = $module->sendPayment($paymentRequest);
        } catch (\Exception $e) {
            $this->logPaymentError(
                'Exception sending payment: ' . $e->getMessage(),
                compact('user', 'patron', 'fines', 'paymentRequest')
            );
            throw new PaymentException('Payment::error_payment_request_failed');
        }
        if (isset($response['error']) || !$response) {
            $errorMessage = $response['error'] ?? 'sendPayment returned false';
            $this->logPaymentError(
                'Error sending payment: ' . $errorMessage,
                compact('user', 'patron', 'fines', 'paymentRequest')
            );
            throw new PaymentException('Payment::error_payment_request_failed');
        }

        $response = json_decode($response);

        if (empty($response->Id) || empty($response->Status)) {
            $this->logPaymentError(
                'Error starting payment, no response',
                compact('user', 'patron', 'fines', 'paymentRequest')
            );
            throw new PaymentException('Payment::error_payment_request_failed');
        }

        $status = intval($response->Status);
        $error = in_array(
            $status,
            [self::CPU_STATUS_ERROR, self::CPU_STATUS_INVALID_REQUEST]
        );
        if ($error) {
            // System error or Request failed.
            $this->logPaymentError(
                'Error starting transaction',
                compact('response', 'user', 'patron', 'fines', 'paymentRequest')
            );
            throw new PaymentException('Payment::error_payment_request_failed');
        }

        $params = [
            $localIdentifier,
            $status,
            $response->Reference,
            $response->PaymentAddress,
        ];
        if (!$this->verifyHash($params, $response->Hash)) {
            $this->logPaymentError(
                'Error starting transaction, invalid checksum',
                compact('response', 'user', 'patron', 'fines', 'paymentRequest')
            );
            throw new PaymentException('Payment::error_payment_request_failed');
        }

        if ($status === self::CPU_STATUS_SUCCESS) {
            // Already processed
            $this->logPaymentError(
                'Error starting transaction, transaction already processed',
                compact('response', 'user', 'patron', 'fines', 'paymentRequest')
            );
            throw new PaymentException('Payment::error_payment_request_failed');
        }

        if ($status === self::CPU_STATUS_ID_EXISTS) {
            // Order exists
            $this->logPaymentError(
                'Error starting transaction, order exists',
                compact('response', 'user', 'patron', 'fines', 'paymentRequest')
            );
            throw new PaymentException('Payment::error_payment_request_failed');
        }

        if ($status === self::CPU_STATUS_CANCELLED) {
            // Cancelled
            $this->logPaymentError(
                'Error starting transaction, order cancelled',
                compact('response', 'user', 'patron', 'fines', 'paymentRequest')
            );
            throw new PaymentException('Payment::error_payment_request_failed');
        }

        if ($status === self::CPU_STATUS_PENDING) {
            // Pending
            $payment = $this->createPaymentEntity($localIdentifier, null, $user, $patron, $amount, $fines);
            return $this->redirectToPayment($response->PaymentAddress, $payment);
        }

        $this->logPaymentError(
            'Error starting transaction, order cancelled',
            compact('response', 'user', 'patron', 'fines', 'paymentRequest')
        );
        throw new PaymentException('Payment::error_payment_request_failed');
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
        if ($payment->getLocalIdentifier() !== $params['Id']) {
            throw new PaymentException('Payment Id mismatch');
        }

        $status = intval($params['Status']);
        if ($status === self::CPU_STATUS_SUCCESS) {
            return self::PAYMENT_SUCCESS;
        } elseif ($status === self::CPU_STATUS_CANCELLED) {
            return self::PAYMENT_CANCEL;
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
     * @return array
     */
    protected function getPaymentResponseParams($request)
    {
        $params = array_merge(
            $request->getQuery()->toArray(),
            $request->getPost()->toArray()
        );
        $payload = json_decode($request->getContent());

        $required = ['Id', 'Status', 'Reference', 'Hash'];
        $response = [];

        foreach ($required as $name) {
            if (isset($payload->$name)) {
                $response[$name] = $payload->$name;
                continue;
            }
            if (isset($params[$name])) {
                $response[$name] = $params[$name];
                continue;
            }

            $this->logPaymentError(
                "Missing parameter $name in payment response",
                compact('request', 'params', 'payload')
            );

            return false;
        }

        $hashParams = [
            $response['Id'],
            intval($response['Status']),
            $response['Reference'],
        ];
        if (!$this->verifyHash($hashParams, $response['Hash'])) {
            $this->logPaymentError(
                'Error processing response: invalid checksum',
                compact('request', 'params')
            );
            return false;
        }

        return array_merge($response, $params);
    }

    /**
     * Init CPU module with configured merchantId, secret and URL.
     *
     * @return Client
     */
    protected function initCpu(): Client
    {
        foreach (['merchantId', 'secret', 'url'] as $req) {
            if (!isset($this->config[$req])) {
                $this->logPaymentError("Missing payment configuration $req");
                throw new \Exception('Missing payment configuration');
            }
        }

        $module = new Client(
            $this->config['url'],
            $this->config['merchantId'],
            $this->config['secret']
        );
        $module->setHttpService($this->httpService);
        $module->setLogger($this->logger);
        return $module;
    }

    /**
     * Verify transaction response hash.
     *
     * @param array  $params Parameters
     * @param string $hash   Hash
     *
     * @return boolean
     */
    protected function verifyHash($params, $hash)
    {
        $params[] = $this->config['secret'];
        return hash('sha256', implode('&', $params)) === $hash;
    }
}
