<?php

/**
 * DSpace 6 REST API implementation
 *
 * API Documentation
 * - https://wiki.duraspace.org/display/DSDOC6x/REST+API
 *
 * Sometimes, when neither documentation nor tutorials help, "Use the Source, Luke!":
 * - https://github.com/DSpace/DSpace/tree/dspace-6.4/dspace-rest/src/main/java/org/dspace/rest
 *
 * Demo instance (official)
 * - https://demo.dspace.org/
 * - https://demo.dspace.org/rest/
 */

namespace TueFind\Service;

class DSpace6 {

    const ENDPOINT_INDEX_TEST = '/test';
    const ENDPOINT_INDEX_LOGIN = '/login';
    const ENDPOINT_INDEX_STATUS = '/status';
    const ENDPOINT_INDEX_LOGOUT = '/logout';

    const ENDPOINT_COMMUNITIES = '/communities';
    const ENDPOINT_COLLECTIONS = '/collections';
    const ENDPOINT_ITEMS = '/items';
    const ENDPOINT_BITSTREAMS = '/bitstreams';
    const ENDPOINT_HIERARCHY = '/hierarchy';
    const ENDPOINT_REGISTRIES = '/registries';
    const ENDPOINT_REPORTS = '/reports';

    const HEADER_ACCEPT = 'Accept';
    const HEADER_CONTENT_TYPE = 'Content-Type';
    const HEADER_COOKIE_REQUEST = 'Cookie';
    const HEADER_COOKIE_RESPONSE = 'Set-Cookie';

    const METHOD_DELETE = 'DELETE';
    const METHOD_GET = 'GET';
    const METHOD_HEAD = 'HEAD';
    const METHOD_PATCH = 'PATCH';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';

    const PAGINATION_LIMIT = 999999; // If we do not add this to our requests, default limit will be 20.

    protected $baseUrl;
    protected $username;
    protected $password;

    /**
     * The cookie will be returned via the API after logging in
     * and needs to be sent back to the API on all consecutive requests.
     *
     * @var array
     */
    protected $cookie = null;

    public function __construct(string $baseUrl, string $username, string $password)
    {
        $this->baseUrl = $baseUrl;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Call the API & return its result
     *
     * @param string $endpoint  One of the ENDPOINT_... class constants
     * @param string $method    One of the METHOD_... class constants
     * @param array  $headers   Array with additional headers to be sent
     *                          (please use HEADER_... class constants)
     * @param string $data      The encoded data, matching the format in $headers
     *
     * @return The decoded JSON response.
     */
    protected function call(string $endpoint, string $method, array $headers=[], string $data=null)
    {
        $fullUrl = $this->baseUrl . $endpoint;

        $opts = ['http' => ['method' => $method, 'header' => '']];
        if (isset($data)) {
            $opts['http']['content'] = $data;
        }

        if (!isset($headers[self::HEADER_ACCEPT])) {
            $headers[self::HEADER_ACCEPT] = 'application/json';
        }
        if (isset($this->cookie)) {
            $headers[self::HEADER_COOKIE_REQUEST] = $this->cookie;
        }
        if ($headers != []) {
            $headerString = '';
            foreach ($headers as $headerName => $headerValue) {
                $headerString .= $headerName . ': ' . $headerValue . "\r\n";
            }
            $opts['http']['header'] .= $headerString;
            $this->requestHeaders = $headers;
            $this->requestBody = $data;
        }

        $context = stream_context_create($opts);
        $json = file_get_contents($fullUrl, false, $context);

        // The server will send a token either on the first response
        // or on any other response, but will not send it in all requests.
        // But whenever he sends one back, we need to use the new one from now on.
        $responseHeaders = get_headers($fullUrl, true, $context);
        $this->responseHeaders = $responseHeaders;
        $this->responseBody = $json;

        if (isset($responseHeaders[self::HEADER_COOKIE_RESPONSE])) {
            $this->cookie = $responseHeaders[self::HEADER_COOKIE_RESPONSE];
            if (preg_match('"^([^=]+=[^=;]+)"', $this->cookie, $hits)) {
                $this->cookie = $hits[1];
            }
        }

        return json_decode($json);
    }

    public function addItem(string $collectionId, array $item)
    {
        // Example Item: https://wiki.lyrasis.org/display/DSDOC6x/REST+API#RESTAPI-ItemObject
        $postData = json_encode($item);
        $headers = [self::HEADER_CONTENT_TYPE => 'application/json',
                    'Content-Length' => strlen($postData)];

        return $this->call(self::ENDPOINT_COLLECTIONS . '/' . urlencode($collectionId) . '/items', self::METHOD_POST, $headers, $postData);
    }

    public function getCollections(string $communityId=null, $limit=self::PAGINATION_LIMIT)
    {
        if (isset($communityId))
            return $this->call(self::ENDPOINT_COMMUNITIES . '/' . urlencode($communityId) . '/collections?size=' . self::PAGINATION_LIMIT, self::METHOD_GET);
        else
            return $this->call(self::ENDPOINT_COLLECTIONS . '?size=' . $limit, self::METHOD_GET);
    }

    public function getCollectionByName(string $name, string $communityId=null)
    {
        // note that there is also a separate endpoint called "find-collection",
        // but there seems to be a lack of documentation how exactly that works,
        // so we use getCollections() as a workaround.
        $result = null;

        $collections = $this->getCollections($communityId);
        foreach ($collections as $collection) {
            if ($collection->name == $name) {
                if ($result != null)
                    throw new \Exception('Multiple collections found with the same name: ' . $name);
                $result = $collection;
            }
        }

        if ($result == null)
            throw new \Exception('Collection not found: ' . $name);

        return $result;
    }

    /**
     * Get an item and its properties.
     *
     * @param string $id     The item ID
     * @param string $expand Per default, only a few attributes will be returned.
     *                       Use e.g. "metadata" or "all" to return more information at the cost of performance.
     * @return stdClass
     */
    public function getItem(string $id, string $expand=null)
    {
        $url = self::ENDPOINT_ITEMS . '/' . urlencode($id);
        if ($expand != null) {
            $url .= '?expand=' . urlencode($expand);
        }
        return $this->call($url, self::METHOD_GET);
    }

    public function getStatus()
    {
        return $this->call(self::ENDPOINT_INDEX_STATUS, self::METHOD_GET);
    }

    /**
     * Try to login using the given credentials.
     *
     * @throws \Exception
     */
    public function login()
    {
        $params = ['email' => $this->username, 'password' => $this->password];
        $postData = http_build_query($params);
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded',
                    'Content-Length' => strlen($postData)];

        $this->call(self::ENDPOINT_INDEX_LOGIN, self::METHOD_POST, $headers, $postData);
    }

}
