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
    protected $baseUrl;
    protected $apiId;
    protected $encryptionKey;
    protected $cipher;
    protected $frontendUserToken;

    const RETURN_REDIRECT = 0;
    const RETURN_JSON = 1;
    const RETURN_TEMPLATE = 2;

    /**
     * Constructor
     *
     * @param string $baseUrl           Base URL of the proxy
     * @param string $apiId             API ID
     * @param string $cipher            cipher, e.g. 'aes-256-ecb'
     * @param string $encryptionKey     Encryption key
     * @param string $frontendUserToken An anonymized token representing the frontend user
     */
    public function __construct($baseUrl, $apiId, $cipher, $encryptionKey, $frontendUserToken)
    {
        $this->baseUrl = $baseUrl;
        $this->apiId = $apiId;
        $this->cipher = $cipher;
        $this->encryptionKey = $encryptionKey;
        $this->frontendUserToken = $frontendUserToken;
    }

    /**
     * Generate an URL with all the GET params
     *
     * @param array $requestData Additional params to add to the base URL
     *
     * @return string
     */
    protected function generateUrl(array $requestData): string
    {
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
    protected function call(array $requestData)
    {
        $url = $this->generateUrl($requestData);
        return file_get_contents($url);
    }

    /**
     * Decode a given SSO string (for debugging purposes only)
     */
    public function decodeSso(string $ssoHex)
    {
        $ssoBin = hex2bin($ssoHex);
        $ssoJson = openssl_decrypt($ssoBin, $this->cipher, $this->encryptionKey, OPENSSL_RAW_DATA);

        $error = '';
        while (($errorLine = openssl_error_string()) != false)
            $error .= $errorLine . "\n";
        rtrim($error);

        if ($error != '')
            return $error;

        $ssoArray = json_decode($ssoJson);
        return $ssoArray;
    }

    /**
     * Get encrypted Single Sign On part of the request (including user credentials)
     *
     * @param string $entitlement   Entitlement (=license) for the given title, mandatory for redirects.
     *
     * @return string
     *
     * @throws Exception
     */
    protected function getSso($entitlement=null): string
    {
        $env = [];
        if ($entitlement != null)
            $env[] = ['name' => 'entitlement', 'value' => $entitlement];

        // Amount of seconds from now until the URL is valid:
        $validTimespan = 60*60*24*1; // 1 day

        $sso = ['user' => $this->frontendUserToken,
                'env' => $env,
                'timestamp' => time() + $validTimespan];

        $encryptedData = openssl_encrypt(json_encode($sso), $this->cipher, $this->encryptionKey, OPENSSL_RAW_DATA);
        if ($encryptedData === false)
            throw new Exception('Could not encrypt data!');
        return bin2hex($encryptedData);
    }

    /**
     * Get basic request template needed for every request
     * (containing user credentials and so on)
     *
     * @param string $entitlement   Entitlement (=license) for the given title, mandatory for redirects.
     *
     * @return array
     */
    protected function getRequestTemplate($entitlement=null): array
    {
        $requestData = [];
        $requestData['id'] = $this->apiId;
        $requestData['sso'] = $this->getSso($entitlement);
        return $requestData;
    }

    /**
     * Get the URL to access the given record via the KfL proxy.
     *
     * @param \TueFind\RecordDriver\SolrMarc $record
     */
    public function getUrl(\TueFind\RecordDriver\SolrMarc $record): string
    {
        $requestData = $this->getRequestTemplate($record->getKflEntitlement());
        $requestData['method'] = 'getHANID';
        $requestData['return'] = self::RETURN_REDIRECT;

        // URL / Title doesnt work with these examples
        //$requestData['url'] = 'https://handbuch-der-religionen.de/';
        //$requestData['title'] = 'Handbuch der Religionen';

        // Passing the HANID directly seems to work (e.g. 'handbuch-religionen'):
        $requestData['hanid'] = $record->getKflId();

        if ($requestData['hanid'] == null)
            throw new \Exception('Han-ID missing for title: ' . $record->getUniqueID());

        return $this->generateUrl($requestData);
    }
}
