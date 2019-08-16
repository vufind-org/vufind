<?php
/**
 * Turku payment handler
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2014-2018.
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
 * @package  OnlinePayment
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\OnlinePayment;

use Finna\OnlinePayment\TurkuPayment\TurkuPaytrailE2;

use Zend\Http\Request;

/**
 * Turku online payment handler module.
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class TurkuPayment extends Paytrail
{
    /**
     * Start transaction.
     *
     * @param string             $finesUrl       Return URL to MyResearch/Fines
     * @param string             $ajaxUrl        Base URL for AJAX-actions
     * @param \Finna\Db\Row\User $user           User
     * @param array              $patron         Patron information
     * @param string             $driver         Patron MultiBackend ILS source
     * @param int                $amount         Amount
     *                                           (excluding transaction fee)
     * @param int                $transactionFee Transaction fee
     * @param array              $fines          Fines data
     * @param string             $currency       Currency
     * @param string             $statusParam    Payment status URL parameter
     *
     * @return string Error message on error, otherwise redirects to payment handler.
     */
    public function startPayment(
        $finesUrl, $ajaxUrl, $user, $patron, $driver, $amount, $transactionFee,
        $fines, $currency, $statusParam
    ) {
        $required = ['merchantId', 'secret', 'sapCode', 'oId', 'applicationName'];
        foreach ($required as $req) {
            if (!isset($this->config[$req])) {
                $this->logger->err("TurkuPayment: missing parameter $req");
                throw new \Exception('Missing parameter');
            }
        }

        $patronId = $patron['cat_username'];
        $orderNumber = $this->generateTransactionId($patronId);

        $module = $this->initTurkuPaytrail();

        $successUrl = "$finesUrl?driver=$driver&$statusParam="
            . self::PAYMENT_SUCCESS;
        $cancelUrl = "$finesUrl?driver=$driver&$statusParam="
            . self::PAYMENT_FAILURE;
        $notifyUrl = "$ajaxUrl/onlinePaymentNotify?driver=$driver&$statusParam="
            . self::PAYMENT_NOTIFY;

        $module->setUrls($successUrl, $cancelUrl, $notifyUrl);
        $module->setOrderNumber($orderNumber);
        $module->setCurrency($currency);
        $module->setOid($this->config->oId);
        $module->setApplicationName($this->config->applicationName);
        $module->setSapCode($this->config->sapCode);

        if (!empty($this->config->paymentDescription)) {
            $module->setMerchantDescription(
                $this->config->paymentDescription . " - $patronId"
            );
        } else {
            $module->setMerchantDescription($patronId);
        }

        foreach ($fines as $fine) {
            $fineType = $fine['fine'] ?? '';
            $fineOrg = $fine['organization'] ?? '';
            $code = substr($fineType, 0, 16);

            $fineDesc = '';
            if (!empty($fineType)) {
                $fineDesc
                    = $this->translator->translate("fine_status_$fineType");
                if ("fine_status_$fineType" === $fineDesc) {
                    $fineDesc = $this->translator->translate("status_$fineType");
                    if ("status_$fineType" === $fineDesc) {
                        $fineDesc = $fineType;
                    }
                }
            }

            $module->addProduct(
                $fineDesc, $code, 1, $fine['balance'], 0,
                TurkuPaytrailE2::TYPE_NORMAL
            );
        }
        if ($transactionFee) {
            $code = isset($this->config->transactionFeeProductCode)
                ? $this->config->transactionFeeProductCode : $productCode;
            $module->addProduct(
                'Palvelumaksu / Serviceavgift / Transaction fee', $code, 1,
                $transactionFee, 0, TurkuPaytrailE2::TYPE_HANDLING
            );
        }

        try {
            $requestBody = $module->generateBody();
        } catch (\Exception $e) {
            $err = 'TurkuPayment: error creating payment request body: '
                . $e->getMessage();
            $this->logger->err($err);
            return false;
        }

        $success = $this->createTransaction(
            $orderNumber,
            $driver,
            $user->id,
            $patronId,
            $amount,
            $transactionFee,
            $currency,
            $fines
        );
        if (!$success) {
            return false;
        }
        $module->setHttpService($this->http);
        $module->setLogger($this->logger);
        $module->sendRequest($this->config->url);
    }

    /**
     * Initialize the Turku Paytrail module
     *
     * @return TurkuPaytrailE2
     */
    protected function initTurkuPaytrail()
    {
        $locale = $this->translator->getLocale();
        $localeParts = explode('-', $locale);
        $paytrailLocale = 'fi_FI';
        if ('sv' === $localeParts[0]) {
            $paytrailLocale = 'sv_SE';
        } elseif ('en' === $localeParts[0]) {
            $paytrailLocale = 'en_US';
        }

        return new TurkuPaytrailE2(
            $this->config->merchantId, $this->config->secret, $paytrailLocale
        );
    }

    /**
     * Process the response from payment service.
     *
     * @param Zend\Http\Request $request Request
     *
     * @return string error message (not translated)
     *   or associative array with keys:
     *     'markFeesAsPaid' (boolean) true if payment was successful and fees
     *     should be registered as paid.
     *     'transactionId' (string) Transaction ID.
     *     'amount' (int) Amount to be registered (does not include transaction fee).
     */
    public function processResponse($request)
    {
        $params = $this->getPaymentResponseParams($request);
        $status = $params['payment'];
        $orderNum = $params['transaction'];
        $timestamp = $params['TIMESTAMP'];

        list($success, $data) = $this->getStartedTransaction($orderNum);
        if (!$success) {
            return $data;
        }

        $t = $data;

        $amount = $t->amount;
        if ($status === self::PAYMENT_SUCCESS || $status === self::PAYMENT_NOTIFY) {
            if (!$module = $this->initTurkuPaytrail()) {
                return 'online_payment_failed';
            }

            $success = $module->validateRequest(
                $params['ORDER_NUMBER'],
                $params['PAID'],
                $params['TIMESTAMP'],
                $params['METHOD'],
                $params['RETURN_AUTHCODE']
            );
            if (!$success) {
                $this->logger->err(
                    'TurkuPayment: error processing response: invalid checksum'
                );
                $this->logger->err("   " . var_export($params, true));
                $this->setTransactionFailed($orderNum, 'invalid checksum');
                return 'online_payment_failed';
            }
            $this->setTransactionPaid($orderNum, $timestamp);

            return [
                'markFeesAsPaid' => true,
                'transactionId' => $orderNum,
                'amount' => $amount
            ];
        } elseif ($status === self::PAYMENT_FAILURE) {
            $this->setTransactionCancelled($orderNum);
            return 'online_payment_canceled';
        } else {
            $this->setTransactionFailed($orderNum, "unknown status $status");
            return 'online_payment_failed';
        }
    }
}
