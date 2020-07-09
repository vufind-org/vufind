<?php
/**
 * Paytrail payment handler
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2014-2020.
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
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 * @link     http://docs.paytrail.com/ Paytrail API documentation
 */
namespace Finna\OnlinePayment;

use Finna\OnlinePayment\Paytrail\PaytrailE2;

/**
 * Paytrail payment handler module.
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 * @link     http://docs.paytrail.com/ Paytrail API documentation
 */
class Paytrail extends BaseHandler
{
    const PAYMENT_SUCCESS = 'success';
    const PAYMENT_FAILURE = 'failure';
    const PAYMENT_NOTIFY = 'notify';

    /**
     * Return payment response parameters.
     *
     * @param Laminas\Http\Request $request Request
     *
     * @return array
     */
    public function getPaymentResponseParams($request)
    {
        $params = array_merge(
            $request->getQuery()->toArray(), $request->getPost()->toArray()
        );

        $required = [
            'ORDER_NUMBER', 'TIMESTAMP', 'RETURN_AUTHCODE'
        ];

        foreach ($required as $name) {
            if (!isset($params[$name])) {
                $this->logger->err(
                    "Paytrail: missing parameter $name in payment response: "
                    . var_export($params, true)
                );
                return false;
            }
        }

        $params['transaction'] = $params['ORDER_NUMBER'];

        return $params;
    }

    /**
     * Start transaction.
     *
     * @param string             $finesUrl       Return URL to MyResearch/Fines
     * @param string             $ajaxUrl        Base URL for AJAX-actions
     * @param \Finna\Db\Row\User $user           User
     * @param array              $patron         Patron information
     * @param string             $driver         Patron MultiBackend ILS source
     * @param int                $amount         Amount
     * (excluding transaction fee)
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
        $patronId = $patron['cat_username'];
        $orderNumber = $this->generateTransactionId($patronId);

        $module = $this->initPaytrail($orderNumber, $currency);

        $successUrl = "$finesUrl?driver=$driver&$statusParam="
            . self::PAYMENT_SUCCESS;
        $cancelUrl = "$finesUrl?driver=$driver&$statusParam="
            . self::PAYMENT_FAILURE;
        $notifyUrl = "$ajaxUrl/onlinePaymentNotify?driver=$driver&$statusParam="
            . self::PAYMENT_NOTIFY;

        $module->setUrls($successUrl, $cancelUrl, $notifyUrl);
        $module->setOrderNumber($orderNumber);
        $module->setCurrency($currency);

        if (!empty($this->config->paymentDescription)) {
            $module->setMerchantDescription(
                $this->config->paymentDescription . " - $patronId"
            );
        } else {
            $module->setMerchantDescription($patronId);
        }

        $lastname = trim($user->lastname);
        if (!empty($user->firstname)) {
            $module->setFirstName(trim($user->firstname));
        } else {
            // We don't have both names separately, try to extract first name from
            // last name.
            if (strpos($lastname, ',') > 0) {
                // Lastname, Firstname
                list($lastname, $firstname) = explode(',', $lastname, 2);
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
            $module->setFirstName(empty($firstname) ? 'ei tietoa' : $firstname);
        }
        $module->setLastName(empty($lastname) ? 'ei tietoa' : $lastname);

        if ($user->email) {
            $module->setEmail($user->email);
        }

        if (!isset($this->config->productCode)
            && !isset($this->config->transactionFeeProductCode)
            && !isset($this->config->productCodeMappings)
            && !isset($this->config->organizationProductCodeMappings)
        ) {
            $module->setTotalAmount($amount + $transactionFee);
        } else {
            $productCode = !empty($this->config->productCode)
                ? $this->config->productCode : '';
            $productCodeMappings = $this->getProductCodeMappings();
            $organizationProductCodeMappings
                = $this->getOrganizationProductCodeMappings();

            foreach ($fines as $fine) {
                $fineType = $fine['fine'] ?? '';
                $fineOrg = $fine['organization'] ?? '';

                if (isset($productCodeMappings[$fineType])) {
                    $code = $productCodeMappings[$fineType];
                } elseif ($productCode) {
                    $code = $productCode;
                } else {
                    $code = $fineType;
                }
                if (isset($organizationProductCodeMappings[$fineOrg])) {
                    $code = $organizationProductCodeMappings[$fineOrg]
                        . ($productCodeMappings[$fineType] ?? '');
                }
                $code = substr($code, 0, 16);

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
                if (!empty($fine['title'])) {
                    $fineDesc .= ' ('
                        . substr($fine['title'], 0, 255 - 4 - strlen($fineDesc))
                    . ')';
                }
                $module->addProduct(
                    $fineDesc, $code, 1, $fine['balance'], 0, PaytrailE2::TYPE_NORMAL
                );
            }
            if ($transactionFee) {
                $code = isset($this->config->transactionFeeProductCode)
                    ? $this->config->transactionFeeProductCode : $productCode;
                $module->addProduct(
                    'Palvelumaksu / Serviceavgift / Transaction fee', $code, 1,
                    $transactionFee, 0, PaytrailE2::TYPE_HANDLING
                );
            }
        }

        try {
            $formData = $module->createPaymentFormData();
        } catch (\Exception $e) {
            $err = 'Paytrail: error creating payment form data: '
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

        $paytrailUrl = !empty($this->config->e2url) ? $this->config->e2url
            : 'https://payment.paytrail.com/e2';

        $this->redirectToPaymentForm($paytrailUrl, $formData);
    }

    /**
     * Process the response from payment service.
     *
     * @param Laminas\Http\Request $request Request
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
            if (!$module = $this->initPaytrail()) {
                return 'online_payment_failed';
            }
            $success = $module->validateRequest(
                $params['ORDER_NUMBER'],
                $params['PAYMENT_ID'],
                $params['TIMESTAMP'],
                $params['STATUS'],
                $params['RETURN_AUTHCODE']
            );
            if (!$success) {
                $this->logger->err(
                    'Paytrail: error processing response: invalid checksum'
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

    /**
     * Initialize the Paytrail module
     *
     * @return PaytrailE2
     */
    protected function initPaytrail()
    {
        foreach (['merchantId', 'secret'] as $req) {
            if (!isset($this->config[$req])) {
                $this->logger->err("Paytrail: missing parameter $req");
                throw new \Exception('Missing parameter');
            }
        }

        $locale = $this->translator->getLocale();
        $localeParts = explode('-', $locale);
        $paytrailLocale = 'fi_FI';
        if ('sv' === $localeParts[0]) {
            $paytrailLocale = 'sv_SE';
        } elseif ('en' === $localeParts[0]) {
            $paytrailLocale = 'en_US';
        }

        return new PaytrailE2(
            $this->config->merchantId, $this->config->secret, $paytrailLocale
        );
    }

    /**
     * Redirect to payment handler.
     *
     * @param string $url      URL
     * @param array  $formData Form fields
     *
     * @return void
     */
    protected function redirectToPaymentForm($url, $formData)
    {
        // Output a minimal form and submit it automatically
        $formFields = '';
        foreach ($formData as $key => $value) {
            $formFields .= '<input type="hidden" name="' . htmlentities($key)
                . '" value="' . htmlentities($value) . '">';
        }
        $locale = $this->translator->getLocale();
        list($lang) = explode('-', $locale);
        $title = $this->translator->translate('online_payment_go_to_pay');
        $title = str_replace('%%amount%%', '', $title);
        $jsRequired = $this->translator->translate('Please enable JavaScript.');
        echo <<<EOT
<!DOCTYPE html>
<html lang="$lang">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>$title</title>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('paytrail-form').submit();
        });
    </script>
</head>
<body>
    <noscript>
        $jsRequired
    </noscript>
    <form id="paytrail-form" action="$url" method="POST">
        $formFields
    </form>
</body>
</html>
EOT;
        exit();
    }
}
