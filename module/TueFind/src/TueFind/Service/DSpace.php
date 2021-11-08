<?php

/**
 * DSpace REST API implementation
 *
 * Protocol Documentation
 * - https://github.com/DSpace/RestContract/blob/main/README.md
 *
 * Demo instance (official)
 * - https://demo.dspace.org/
 * - https://api7.dspace.org/server/#/server/api
 */

namespace TueFind\Service;

class DSpace {

    const ENDPOINT_AUTH_LOGIN = '/api/authn/login';
    const ENDPOINT_AUTH_STATUS = '/api/authn/status';
    const ENDPOINT_CORE_ITEMS = '/api/core/items';
    const ENDPOINT_CORE_METADATASCHEMAS = '/api/core/metadataschemas';

    const HEADER_AUTHORIZATION = 'Authorization';
    const HEADER_CSRF_REQUEST = 'X-XSRF-TOKEN';
    const HEADER_CSRF_RESPONSE = 'DSPACE-XSRF-TOKEN';
    const HEADER_COOKIE_REQUEST = 'Cookie';
    const HEADER_COOKIE_RESPONSE = 'Set-Cookie';

    const METHOD_DELETE = 'DELETE';
    const METHOD_GET = 'GET';
    const METHOD_HEAD = 'HEAD';
    const METHOD_PATCH = 'PATCH';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';

    protected $baseUrl;
    protected $username;
    protected $password;

    /**
     * The authentication bearer, returned in HTTP response after login
     *
     * @var string
     */
    protected $bearer;

    /**
     * These cookies will be returned via the API on first call
     * and need to be sent back to the API on all consecutive requests.
     *
     * @var array
     */
    protected $cookies = [];

    /**
     * This token will be returned via the API on the first call
     * and needs to be sent to the API on all consecutive requests.
     *
     * A CSRF token MUST be given before using any modifying operation
     * - Non-modifying: GET, HEAD
     * - Modifying: DELETE, PATCH, POST, PUT
     *
     * @var string
     */
    protected $csrfToken;

    public function __construct(string $baseUrl, string $username, string $password)
    {
        $this->baseUrl = $baseUrl;
        $this->username = $username;
        $this->password = $password;
    }

    public function getItem(string $id)
    {
        return $this->call(self::ENDPOINT_CORE_ITEMS . '/' . urlencode($id), self::METHOD_GET);
    }

    public function getMetadataSchema(string $id)
    {
        return $this->call(self::ENDPOINT_CORE_METADATASCHEMAS . '/' . urlencode($id), self::METHOD_GET);
    }

    public function hasItem(string $id)
    {
        return $this->call(self::ENDPOINT_CORE_ITEMS . '/' . urlencode($id), self::METHOD_HEAD);
    }

    public function hasMetadataSchema(string $id)
    {
        return $this->call(self::ENDPOINT_CORE_METADATASCHEMAS . '/' . urlencode($id), self::METHOD_HEAD);
    }

    public function getAuthenticationStatus()
    {
        if (!isset($this->csrfToken))
            throw new \Exception('No csrfToken present yet that we could check against.');

        return $this->call(self::ENDPOINT_AUTH_STATUS, self::METHOD_GET, [], [self::HEADER_AUTHORIZATION => 'Bearer ' . $this->bearer]);
    }

    /**
     * Try to login using the given credentials.
     *
     * @throws \Exception
     */
    public function login()
    {
        // Since this is a POST operation it requires a crsfToken before using it,
        // so we just call another GET/HEAD operation in case it is missing so far.
        if (!isset($this->csrfToken))
            $this->hasMetadataSchema(1);

        return $this->call(self::ENDPOINT_AUTH_LOGIN, self::METHOD_POST, ['user' => $this->username, 'password' => $this->password]);
    }

    /**
     * Call the API & return its result
     *
     * @param string $endpoint  One of the ENDPOINT_... class constants
     * @param string $method    One of the METHOD_... class constants
     * @param array  $params    Array with additional parameters
     * @param array  $headers   Array with additional headers to be sent
     *                          (please use HEADER_... class constants)
     *
     * @return The decoded JSON response.
     */
    protected function call(string $endpoint, string $method, array $params=[], array $headers=[])
    {
        $fullUrl = $this->baseUrl . $endpoint;

        $opts = ['http' => ['method' => $method, 'header' => '']];
        if ($method == self::METHOD_POST) {
            $postData = http_build_query($params);
            $opts['http']['header'] .= "Content-type: application/x-www-form-urlencoded\r\n";
            $opts['http']['header'] .= "Content-Length: " . strlen($postData) . "\r\n";
            $opts['http']['content'] = $postData;
        }

        if (isset($this->csrfToken))
            $headers[self::HEADER_CSRF_REQUEST] = $this->csrfToken;

        if ($this->cookies != []) {
            $cookiesString = '';
            foreach ($this->cookies as $cookieId => $cookieValue) {
                if ($cookiesString != '')
                    $cookiesString .= '; ';
                $cookiesString .= $cookieId . '=' . $cookieValue;
            }
            $headers[self::HEADER_COOKIE_REQUEST] = $cookiesString;
        }
        if ($headers != []) {
            $headerString = '';
            foreach ($headers as $headerName => $headerValue)
                $headerString .= $headerName . ': ' . $headerValue . "\r\n";
            $opts['http']['header'] .= $headerString;
        }

        $context = stream_context_create($opts);
        $json = file_get_contents($fullUrl, false, $context);

        // The server will send a token either on the first response
        // or on any other response, but will not send it in all requests.
        // But whenever he sends one back, we need to use the new one from now on.
        $responseHeaders = get_headers($fullUrl, true, $context);
        if (isset($responseHeaders[self::HEADER_CSRF_RESPONSE]))
            $this->csrfToken = $responseHeaders[self::HEADER_CSRF_RESPONSE];
        if (isset($responseHeaders[self::HEADER_COOKIE_RESPONSE])) {
            $cookies = $responseHeaders[self::HEADER_COOKIE_RESPONSE];
            if (!is_array($cookies))
                $cookies = [$cookies];
            foreach ($cookies as $cookie) {
                if (preg_match('"^([^=]+)=([^=;]+)"', $cookie, $hits))
                    $this->cookies[$hits[1]] = $hits[2];
            }
        }
        if (isset($responseHeaders[self::HEADER_AUTHORIZATION])) {
            if (preg_match('"Bearer (.+)"', $responseHeaders[self::HEADER_AUTHORIZATION], $hits))
                $this->bearer = $hits[1];
        }

        return json_decode($json);
    }

}
