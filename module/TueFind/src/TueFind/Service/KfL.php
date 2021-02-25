<?php


namespace TueFind\Service;

/**
 * Class to communicate with KfL/HAN Proxy.
 *
 * For API documentation, see:
 * https://www.hh-han.com/webhelp-de/han_web_api.htm
 */
class KfL
{
    private $baseUrl;
    private $apiUser;
    private $apiPassword;
    private $cipher;
    private $frontendUserToken;

    /**
     * Constructor
     *
     * @param string $baseUrl           Base URL of the proxy
     * @param string $apiUser           API username
     * @param string $apiPassword       API password
     * @param string $cipher            cipher, e.g. 'aes-256-ecb'
     * @param string $frontendUserToken An anonymized token representing the frontend user
     */
    public function __construct($baseUrl, $apiUser, $apiPassword, $cipher, $frontendUserToken)
    {
        $this->baseUrl = $baseUrl;
        $this->apiUser = $apiUser;
        $this->apiPassword = $apiPassword;
        $this->cipher = $cipher;
        $this->frontendUserToken = $frontendUserToken;
    }

    /**
     * Generate an URL with all the GET params
     *
     * @param array $requestData Additional params to add to the base URL
     *
     * @return string
     */
    private function generateUrl(array $requestData): string {
        $url = $this->baseUrl;
        $i = 0;
        foreach ($requestData as $key => $value) {
            if ($i == 0)
                $url .= '?';
            else
                $url .= '&';
            $url .= urlencode($key) . '=' . urlencode($value);
            ++$i;
        }
        return $url;
    }

    /**
     * Execute call and return result
     *
     * @param array $requestData
     */
    private function call(array $requestData) {
        $url = $this->generateUrl($requestData);
        return file_get_contents($url);
    }

    /**
     * Get encrypted Single Sign On part of the request (including user credentials)
     *
     * @return string
     *
     * @throws Exception
     */
    private function getSso(): string {
        $sso = ['user' => $this->apiUser,
                'env' => ['frontendUser' => $this->frontendUserToken],
                'timestamp' => time() + 300];

        $encryptedData = openssl_encrypt(json_encode($sso), $this->cipher, $this->apiPassword, OPENSSL_RAW_DATA);
        if ($encryptedData === false)
            throw new Exception('Could not encrypt data!');
        return bin2hex($encryptedData);
    }

    /**
     * Get basic request template needed for every request
     * (containing user credentials and so on)
     *
     * @return array
     */
    private function getRequestTemplate(): array {
        $requestData = [];
        $requestData['id'] = $this->apiUser;
        $requestData['sso'] = $this->getSso();
        return $requestData;
    }

    /**
     * Try to get the HANID for the given record.
     *
     * @param \TueFind\RecordDriver\SolrMarc $record
     */
    public function searchItem(\TueFind\RecordDriver\SolrMarc $record) {
        $requestData = $this->getRequestTemplate();
        $requestData['method'] = 'getHANID';
        $requestData['return'] = 1; // return JSON

        // TODO:
        // This is just a hardcoded example, use information from RecordDriver instead
        // as soon as we are provided with additional information about which URL field to use.
        $requestData['url'] = 'https://handbuch-der-religionen.de/';
        //$requestData['title'] = 'Handbuch der Religionen';
        //return $this->call($requestData);
        return '<a href="' . $this->generateUrl($requestData) . '" target="_blank">Handbuch der Religionen</a>';

        // TODO: Parse JSON response to determine if matches have been found, and act accordingly.
    }
}
