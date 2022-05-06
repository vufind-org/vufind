<?php

/**
 * DSpace REST API implementation
 *
 * Protocol Documentation
 * - https://github.com/DSpace/RestContract/blob/main/README.md
 *
 * API Tutorials
 * - https://dspace-labs.github.io/DSpace7RestTutorial/
 *
 * Sometimes, when neither documentation nor tutorials help, "Use the Source, Luke!":
 * - https://github.com/DSpace/DSpace/tree/main/dspace-server-webapp/src/main/java/org/dspace/app/rest
 *
 * Demo instance (official)
 * - https://demo.dspace.org/
 * - https://api7.dspace.org/server/#/server/api
 */

namespace TueFind\Service;

class DSpace {

    const ENDPOINT_AUTH_LOGIN = '/api/authn/login';
    const ENDPOINT_AUTH_STATUS = '/api/authn/status';
    const ENDPOINT_CORE_COLLECTIONS = '/api/core/collections';
    const ENDPOINT_CORE_COMMUNITIES = '/api/core/communities';
    const ENDPOINT_CORE_ITEMS = '/api/core/items';
    const ENDPOINT_CORE_METADATASCHEMAS = '/api/core/metadataschemas';
    const ENDPOINT_WORKSPACE_ITEM = '/api/submission/workspaceitems';
    const ENDPOINT_WORKFLOW_ITEM = '/api/workflow/workflowitems';

    const HEADER_AUTHORIZATION = 'Authorization';
    const HEADER_CONTENT_TYPE = 'Content-Type';
    const HEADER_COOKIE_REQUEST = 'Cookie';
    const HEADER_COOKIE_RESPONSE = 'Set-Cookie';
    const HEADER_CSRF_REQUEST = 'X-XSRF-TOKEN';
    const HEADER_CSRF_RESPONSE = 'DSPACE-XSRF-TOKEN';

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

    /**
     * Headers of the last request, for debbuging purposes.
     * @var array
     */
    protected $requestHeaders = [];

    /**
     * Body of the last request, for debbuging purposes.
     * @var string
     */
    protected $requestBody = '';

    /**
     * Headers of the last response, for debbuging purposes.
     * @var array
     */
    protected $responseHeaders = [];

    /**
     * Body of the last response, for debbuging purposes.
     * @var string
     */
    protected $responseBody = '';

    public function __construct(string $baseUrl, string $username, string $password)
    {
        $this->baseUrl = $baseUrl;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Start the workflow (e.g. publication) for an existing workspace item (e.g. uploaded PDF file).
     *
     * @param string $workspaceItemId
     */
    public function addWorkflowItem(string $workspaceItemId)
    {
        // we need to use a POST operation and send a full URL of the workspace item as body (including its endpoint!!!)
        $requestData = $this->baseUrl . '/' . self::ENDPOINT_WORKSPACE_ITEM . '/' . urlencode($workspaceItemId);
        return $this->call(self::ENDPOINT_WORKFLOW_ITEM, self::METHOD_POST,
                            [self::HEADER_CONTENT_TYPE => 'text/uri-list',
                            self::HEADER_AUTHORIZATION => 'Bearer ' . $this->bearer],
        $requestData);
    }

    /**
     * Add a workspace item (e.g. upload a PDF file)
     *
     * @param string $documentUrl
     * @param string $collectionId
     */
    public function addWorkspaceItem(string $documentUrl, string $collectionId)
    {
        $endpointUrl = self::ENDPOINT_WORKSPACE_ITEM;

        // POST the whole file
        $fileHandle = fopen($documentUrl, "rb");
        $fileContents = stream_get_contents($fileHandle);
        fclose($fileHandle);

        $requestData = '';
        $boundary = '---------------------------6549703717952841723022740979';

        $requestData = '--' . $boundary . "\r\n";
        $requestData .= 'Content-Disposition: form-data; name="owningCollection"' . "\r\n\r\n";
        $requestData .= $collectionId . "\r\n";

        $requestData .= '--' . $boundary . "\r\n";
        $requestData .= 'Content-Disposition: form-data; name="file"; filename="' . basename($documentUrl) . '"' . "\r\n";
        $requestData .= 'Content-Type: application/pdf' . "\r\n\r\n";
        $requestData .= 'Content-Transfer-encoding: binary' . "\r\n\r\n";
        $requestData .= $fileContents . "\r\n\r\n";
        $requestData .= '--' . $boundary . '--' . "\r\n";

        $headers = [self::HEADER_CONTENT_TYPE => 'multipart/form-data; boundary=' . $boundary,
                    'Content-Length' => strlen($requestData),
                    self::HEADER_AUTHORIZATION => 'Bearer ' . $this->bearer,
        ];

        $result = $this->call($endpointUrl, self::METHOD_POST, $headers, $requestData);
        return $result->_embedded->workspaceitems[0];
    }

    /**
     * Update metadata for an existing item
     *
     * @param string $id       The DSpace-ID of the existing workspace item
     * @param array $metadata  The metadata, as produced by TueFind\MetadataVocabulary\DSpace.
     */
    public function updateWorkspaceItem(string $id, array $metadata) {

        $requestData = [];

        foreach ($metadata as $metaKey => $metaValue) {
            $valuesArray = ['value' => $metaValue, 'display' => $metaValue];
            switch ($metaKey) {
                case '/sections/traditionalpageone/dc.contributor.author':
                    $valuesArray['confidence'] = 600;
                    $explodeValue = explode(';', $metaValue);
                    $valuesArray['value'] = $explodeValue[0];
                    $valuesArray['display'] = $explodeValue[0];
                    $valuesArray['authority'] = $explodeValue[1];
                    break;
                case '/sections/traditionalpageone/dc.language.iso':
                    $valuesArray['language'] = $metaValue;
                    break;
            }

            if(isset($valuesArray['language'])) {
                $dsValueArray = [];
                $explodeLanguages = explode(",",$metaValue);
                foreach($explodeLanguages as $exl) {
                    if(!empty($exl)) {
                        $dsValueArray[] = ['language' => $exl];
                    }
                }
                $requestData[] = [
                    'op' => 'add',
                    'path' => $metaKey,
                    'value' => $dsValueArray
                ];
            }else{
                $requestData[] = [
                    'op' => 'add',
                    'path' => $metaKey,
                    'value' =>
                        [
                            $valuesArray
                        ]
                ];
            }

        }

        $requestDataJson = json_encode($requestData);

        $headers = [
            'Content-Type' => 'application/json',
            self::HEADER_AUTHORIZATION => 'Bearer ' . $this->bearer
            ];

        return $this->call(self::ENDPOINT_WORKSPACE_ITEM . '/' . urlencode($id), self::METHOD_PATCH, $headers, $requestDataJson);

    }

    public function getAuthenticationStatus()
    {
        if (!isset($this->bearer))
            throw new \Exception('No bearer value present yet that we could check against (not yet logged in?).');

        return $this->call(self::ENDPOINT_AUTH_STATUS, self::METHOD_GET, [self::HEADER_AUTHORIZATION => 'Bearer ' . $this->bearer]);
    }

    public function getCollectionByName(string $name, string $communityId=null)
    {
        $result = null;

        $collections = $this->getCollections($communityId);
        foreach ($collections->_embedded->collections as $collection) {
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

    public function getCollections(string $communityId=null, $limit=self::PAGINATION_LIMIT)
    {
        if (isset($communityId))
            return $this->call(self::ENDPOINT_CORE_COMMUNITIES . '/' . urlencode($communityId) . '/collections?size=' . self::PAGINATION_LIMIT, self::METHOD_GET);
        else
            return $this->call(self::ENDPOINT_CORE_COLLECTIONS . '?size=' . $limit, self::METHOD_GET);
    }

    public function getCommunities($limit=self::PAGINATION_LIMIT)
    {
        return $this->call(self::ENDPOINT_CORE_COMMUNITIES . '?size=' . $limit, self::METHOD_GET);
    }

    public function getItem(string $id)
    {
        return $this->call(self::ENDPOINT_CORE_ITEMS . '/' . urlencode($id), self::METHOD_GET);
    }

    public function getMetadataSchemas($limit=self::PAGINATION_LIMIT)
    {
        return $this->call(self::ENDPOINT_CORE_METADATASCHEMAS . '?size=' . $limit, self::METHOD_GET);
    }

    public function getMetadataSchema(string $id)
    {
        return $this->call(self::ENDPOINT_CORE_METADATASCHEMAS . '/' . urlencode($id), self::METHOD_GET);
    }

    public function getSubCommunities(string $communityId)
    {
        return $this->call(self::ENDPOINT_CORE_COMMUNITIES . '/' . urlencode($communityId) . '/subcommunities', self::METHOD_GET);
    }

    public function getWorkspaceItem(string $id)
    {
        return $this->call(self::ENDPOINT_WORKSPACE_ITEM . '/' . urlencode($id), self::METHOD_GET, [self::HEADER_AUTHORIZATION => 'Bearer ' . $this->bearer]);
    }

    /**
     * Try to login using the given credentials.
     *
     * @throws \Exception
     */
    public function login(): void
    {
        // Since this is a POST operation it requires a crsfToken before using it,
        // so we just call another GET/HEAD operation in case it is missing so far.
        if (!isset($this->csrfToken))
            $this->getCommunities();

        $params = ['user' => $this->username, 'password' => $this->password];
        $postData = http_build_query($params);
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded',
                    'Content-Length' => strlen($postData)];

        $this->call(self::ENDPOINT_AUTH_LOGIN, self::METHOD_POST, $headers, $postData);
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
        if (isset($data))
            $opts['http']['content'] = $data;

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

    /**
     * Get last request headers for debugging purposes.
     * @return array
     */
    public function getRequestHeaders(): array
    {
        return $this->requestHeaders;
    }

    /**
     * Get last request body for debugging purposes.
     * @return array
     */
    public function getRequestBody(): string
    {
        return $this->requestBody;
    }

    /**
     * Get last response headers for debugging purposes.
     * @return array
     */
    public function getResponseHeaders(): array
    {
        return $this->responseHeaders;
    }

    /**
     * Get last response body for debugging purposes.
     * @return array
     */
    public function getResponseBody(): string
    {
        return $this->responseBody;
    }

}
