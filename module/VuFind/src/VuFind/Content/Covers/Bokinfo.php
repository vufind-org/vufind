<?php

/**
 * VuFind Driver for Koha, using REST API
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016-2020.
 * Copyright (C) Moravian Library 2019.
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
 * @package  Content
 * @author   Jacob Sandin <jacob@imcode.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:content_provider_components#covers Wiki
 */

namespace VuFind\Content\Covers;

use \SimpleXMLElement;

/**
 * Summon cover content loader.
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Bokinfo extends \VuFind\Content\AbstractCover implements
    \Laminas\Log\LoggerAwareInterface,
    \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFindHttp\HttpServiceAwareTrait;
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->supportsIsbn = $this->cacheAllowed = true;
        
    }

    /**
     * Get image URL for a particular API key and set of IDs (or false if invalid).
     *
     * @param string $key  API key
     * @param string $size Size of image to load (small/medium/large)
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object and 'issn' pointing to a string)
     *
     * @return string|bool
     */
    public function getUrl($key, $size, $ids)
    {
        if (!isset($ids['isbn'])) {
            return false;
        }
        if (!isset($key)) {
            return false;
        }

        $isbn = $ids['isbn']->get13();
        try {
            $client = $this->createHttpClient("https://api.bokinfo.se/book/get/$isbn");

             $client->getRequest()->getHeaders()
                  ->addHeaderLine("Ocp-Apim-Subscription-Key",$key);

             try {
                 $resp= $client->send();
                 $body = $resp->getBody();
                 $url = $this->getImageUrl($body);
             } catch (\Exception $e) {

             }

            if ($this->testUrl($url)) {
                return "$url";
            }
        } catch (\Throwable $ex) {
            return false;
        } catch (\Exception $ex) {
            return false;
        } catch (\RuntimeException $ex) {
            return false;
        }

        return false;
    }


    protected function createHttpClient($url)
    {
        $client = $this->httpService->createClient($url);

        if (
            isset($this->config['Http']['ssl_verify_peer_name'])
            && !$this->config['Http']['ssl_verify_peer_name']
        ) {
            $adapter = $client->getAdapter();
            if ($adapter instanceof \Laminas\Http\Client\Adapter\Socket) {
                $context = $adapter->getStreamContext();
                $res = stream_context_set_option(
                    $context,
                    'ssl',
                    'verify_peer_name',
                    false
                );
                if (!$res) {
                    throw new \Exception('Unable to set sslverifypeername option');
                }
            } elseif ($adapter instanceof \Laminas\Http\Client\Adapter\Curl) {
                $adapter->setCurlOption(CURLOPT_SSL_VERIFYHOST, false);
            }
        }

        // Set timeout value
        $timeout = $this->config['Catalog']['http_timeout'] ?? 30;
        $client->setOptions(
            ['timeout' => $timeout, 'useragent' => 'VuFind', 'keepalive' => true]
        );

        // Set Accept header
        $client->getRequest()->getHeaders()->addHeaderLine(
            'Accept',
            'application/json'
        );

        return $client;
    }


    protected function testUrl($url)
    {

        // Use get_headers() function
        $headers = @get_headers($url);

        // Use condition to check the existence of URL
        if ($headers && strpos($headers[0], '200')) {
            return true;
        } else {
            return false;
        }
    }

    protected  function getImageUrl($rawXML)
    {
        if (!str_contains($rawXML,"ResourceLink")){
            return "";
        }

        //This is already wrapped in try..catch
        $xml = new SimpleXMLElement($rawXML);

        foreach ($xml->getDocNamespaces() as $strPrefix => $strNamespace) {
            if (strlen($strPrefix) == 0) {
                $strPrefix = "_"; //Assign an arbitrary namespace prefix.
            }
            $xml->registerXPathNamespace($strPrefix, $strNamespace);
        }

        $result = $xml->xpath('//_:SupportingResource[_:ResourceContentType="01"]/_:ResourceVersion/_:ResourceLink');
        return trim($result[0]);
    }
}
