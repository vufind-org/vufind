<?php

namespace VuFindSearch\Backend\Summon;

use GuzzleHttp\Client as HttpClient;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use SerialsSolutions_Summon_Exception;

/**
 * PSR-compliant port of VuFindSearch\Backend\Summon\PsrConnector
 */
class PsrConnector extends \SerialsSolutions_Summon_Base implements LoggerAwareInterface
{
    protected $client;
    protected $logger = false;

    public function __construct($apiId, $apiKey, $options = array(), $client = null)
    {
        parent::__construct($apiId, $apiKey, $options);
        $this->client = is_object($client) ? $client : new HttpClient();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    protected function debugPrint($msg)
    {
        if ($this->logger) {
            $this->logger->debug("$msg\n");
        } else {
            parent::debugPrint($msg);
        }
    }

    public function handleFatalError($e)
    {
        throw $e;
    }

    protected function httpRequest($baseUrl, $method, $queryString, $headers)
    {
        $this->debugPrint("{$method}: {$baseUrl}?{$queryString}");

        $options = ['headers' => $headers];

        if ($method == 'GET') {
            $baseUrl .= '?' . $queryString;
        } elseif ($method == 'POST') {
            $options['body'] = $queryString;
            $options['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        $result = $this->client->request($method, $baseUrl, $options);

        if ($result->getStatusCode() < 200 || $result->getStatusCode() >= 300) {
            throw new SerialsSolutions_Summon_Exception($result->getBody()->getContents());
        }

        return $result->getBody()->getContents();
    }
}