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
    protected $authManager;
    protected $tuefindInstance;

    protected $baseUrl;
    protected $apiId;
    protected $encryptionKey;
    protected $cipher;
    protected $titles;

    const RETURN_REDIRECT = 0;
    const RETURN_JSON = 1;
    const RETURN_TEMPLATE = 2;

    /**
     * Constructor
     *
     * @param Config $config            Configuration entries
     * @param Manager $authManager      Auth Manager
     * @param string $tuefindInstance   TueFind instance
     */
    public function __construct($config, $authManager, $tuefindInstance)
    {
        $this->baseUrl = $config->base_url;
        $this->apiId = $config->api_id;
        $this->cipher = $config->cipher;
        $this->encryptionKey = $config->encryption_key;

        $titles = $config->titles ?? [];
        $parsedTitles = [];
        foreach ($titles as $title) {
            $titleDetails = explode(':', $title);
            $parsedTitles[] = ['ppn' => $titleDetails[0],
                               'kflId' => $titleDetails[1],
                               'entitlement' => $titleDetails[2]];
        }
        $this->titles = $parsedTitles;

        $this->authManager = $authManager;
        $this->tuefindInstance = $tuefindInstance;
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
     * Generate token that represents the frontend user
     *
     * @return string
     *
     * @throws \Exception
     */
    protected function getFrontendUserToken(): string
    {
        // Check if user is logged-in:
        // This should be checked at the latest possible point.
        // An earlier implementation checked it in the factory, which led
        // to errors in other actions in the same controller, which should still
        // be possible if the user is not logged in.
        $user = $this->authManager->isLoggedIn();
        if (!$user)
            throw new \Exception('Could not generate KfL Frontend User Token, user is not logged in!');

        // We pass an anonymized version of the user id (tuefind_uuid) together with host+tuefind instance.
        // This value will be saved by the proxy and reported back to us in case of abuse.
        return implode('#', [gethostname(), $this->tuefindInstance, $user->tuefind_uuid]);
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

        $sso = ['user' => $this->getFrontendUserToken(),
                'timestamp' => time() + $validTimespan,
                'env' => $env,
        ];

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
        $titleInfo = $this->getTitleInfo($record->getUniqueId());
        $requestData = $this->getRequestTemplate($titleInfo['entitlement']);
        $requestData['method'] = 'getHANID';
        $requestData['return'] = self::RETURN_REDIRECT;
        $requestData['hanid'] = $titleInfo['kflId'];

        if ($requestData['hanid'] == null)
            throw new \Exception('Han-ID missing for title: ' . $record->getUniqueID());

        return $this->generateUrl($requestData);
    }

    /**
     * Get information about a title, especially Kfl-ID and entitlement.
     *
     * @param string $ppn
     *
     * @return array
     */
    protected function getTitleInfo(string $ppn): array
    {
        foreach ($this->titles as $title) {
            if ($title['ppn'] == $ppn)
                return $title;
        }

        throw new \Exception('KfL title information missing for ppn: ' . $ppn);
    }

    /**
     * Is the given PPN available via the KfL?
     *
     * @param string $ppn
     *
     * @return bool
     */
    public function hasTitle(string $ppn): bool
    {
        foreach ($this->titles as $title) {
            if ($title['ppn'] == $ppn)
                return true;
        }
        return false;
    }
}
