<?php
// @codingStandardsIgnoreStart

/**
 * Client example of CPU Verkkomaksu API.
 * Handles validating and sending data to eCommerce service.
 *
 * @since 2015-05-19 MB, Version 1.0 created
 * @version 1.0
 */
class Cpu_Client
{
    use \Finna\OnlinePayment\OnlinePaymentModuleTrait;

    /**
     * Url of eCommerce service where payment data will be sent.
     *
     * @var string
     */
    private $service_url;

    /**
     * Identification code of client service. This can be considered as an account number.
     * Granted by CPU.
     *
     * Max. length 40 chars
     *
     * @var string
     */
    private $source;

    /**
     * Secret Key of client service. This can be considered as an account password.
     * Granted by CPU.
     *
     * @var string
     */
    private $secret_key;

    /**
     * Constructor initializes object with client settings.
     *
     * @param string $service_url Url pointing to eCommerce payment checkout
     * @param string $source Client account
     * @param string $secret_key Client password
     */
    public function __construct($service_url, $source, $secret_key)
    {
        $this->service_url = self::sanitize($service_url);
        $this->source      = mb_substr(self::sanitize($source), 0, 40);
        $this->secret_key  = self::sanitize($secret_key);
    }

    /**
     * Sends new payment to eCommerce.
     * Do not send the secretkey as part of the message, it will make the request fail!
     *
     * Redirect customer to PaymentAddress after validating response data.
     *
     * @param Cpu_Client_Payment $payment Payment data
     * @return mixed array containing an errormessage, JSON response from eCommerce or false
     */
    public function sendPayment(Cpu_Client_Payment $payment)
    {
        $valid = $payment->isValid();

        if ($valid !== true) {
            return ['error' => $valid];
        }

        if ($this->service_url && $this->source && $this->secret_key) {

            // Prepare data to be sent.
            $data = $payment->convertToArray();
            $data['Source'] = $this->source;
            $data['Hash']   = Cpu_Client::calculateHash($payment, $this->source, $this->secret_key);

            $json_data = json_encode($data);

            if (false === $json_data) {
                throw new \Exception(
                    'Could not convert request to JSON: ' . var_export($data, true)
                );
            }

            $options = ['maxredirects' => 1];
            $headers = [
               'Content-Type' => 'application/json; charset=utf-8'
            ];

            $response = $this->postRequest(
                $this->service_url, $json_data, $options, $headers
            );

            if (!$response) {
                return ['error' => 'Failed to send payment'];
            }

            return $response['response'];
        }

        return ['error' => 'Error with settings'];
    }

    /**
     * Calculates sha256 signature.
     * Only mandatory properties and properties with values are used in calculation.
     *
     * @param Cpu_Client_Payment $payment Payment object
     * @param string $source Source identification given by CPU
     * @param string $secret_key Secret Key identification given by CPU
     * @return string sha256 hash signature
     */
    public static function calculateHash(Cpu_Client_Payment $payment, $source, $secret_key)
    {
        $source     = Cpu_Client::sanitize($source);
        $secret_key = Cpu_Client::sanitize($secret_key);
        $separator  = '&';
        $string     = '';

        if ($payment->isValid() === true && !empty($source) && !empty($secret_key)) {
            $string .= $payment->ApiVersion . $separator;
            $string .= $source . $separator;
            $string .= $payment->Id . $separator;
            $string .= $payment->Mode . $separator;

            if ($payment->Description != null) {
                $string .= str_replace(';', '', $payment->Description) . $separator;
            }

            foreach ($payment->Products as $product) {
                if ($product instanceof Cpu_Client_Product) {
                    $string .= str_replace(';', '', $product->Code) . $separator;

                    if ($product->Amount != null) {
                        $string .= intval($product->Amount) . $separator;
                    }

                    if ($product->Price != null) {
                        $string .= intval($product->Price) . $separator;
                    }

                    if ($product->Description != null) {
                        $string .= str_replace(';', '', $product->Description) . $separator;
                    }

                    if ($product->Taxcode != null) {
                        $string .= str_replace(';', '', $product->Taxcode) . $separator;
                    }
                }
            }

            if ($payment->Email != null) {
                $string .= $payment->Email . $separator;
            }

            if ($payment->FirstName != null) {
                $string .= $payment->FirstName . $separator;
            }

            if ($payment->LastName != null) {
                $string .= $payment->LastName . $separator;
            }

            if ($payment->Language != null) {
                $string .= $payment->Language . $separator;
            }

            $string .= $payment->ReturnAddress . $separator;
            $string .= $payment->NotificationAddress . $separator;
            $string .= $secret_key;

            $string = hash('sha256', $string);
        }

        return $string;
    }

    /**
     * Simple sanitazion method.
     *
     * @param string $value Value to be sanitated
     * @return string Clean value
     */
    public static function sanitize($value)
    {
        return str_replace(';', '', strip_tags(trim(filter_var($value, FILTER_SANITIZE_STRING))));
    }
}
// @codingStandardsIgnoreEnd
