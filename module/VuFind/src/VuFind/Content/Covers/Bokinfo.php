<?php

/**
 * Plugin for Bokinfo coverimages
 *
 * PHP version 8
 *
 * Copyright (C) imCode Partner AB 2022.
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
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Content\Covers;

use SimpleXMLElement;

use function strlen;

/**
 * Plugin for Bokinfo coverimages
 *
 * @category VuFind
 * @package  Content
 * @author   Jacob Sandin <jacob@imcode.com>
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
        // Checked with vendor to be sure caching is allowed as of February, 2022.
        $this->cacheAllowed = true;
        $this->supportsIsbn = true;
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
        if (empty($key)) {
            return false;
        }

        $isbn = $ids['isbn']->get13();
        try {
            $client = $this->createHttpClient(
                "https://api.bokinfo.se/book/get/$isbn"
            );

            $client->getRequest()->getHeaders()
                  ->addHeaderLine('Ocp-Apim-Subscription-Key', $key);

            $resp = $client->send();
            $body = $resp->getBody();
            $url = $this->getImageUrl($body);
            if ($this->testUrlFunction($url)) {
                return $url;
            }
        } catch (\Throwable $ex) {
            return false;
        }

        return false;
    }

    /**
     * Return a HTTP Client object
     *
     * @param string $url API Url
     *
     * @return HttpClient Http Client
     */
    protected function createHttpClient($url)
    {
        $client = $this->httpService->createClient($url);

        $client->setOptions(
            ['useragent' => 'VuFind', 'keepalive' => true]
        );

        return $client;
    }

    /**
     * Test that the url is really working
     *
     * @param string $url image Url
     *
     * @return bool Http Client
     */
    protected function testUrlFunction($url)
    {
        try {
            $client = $this->createHttpClient($url);
            $resp = $client->send();
            $headers = $resp->getHeaders();
            if ($headers) {
                return true;
            }
        } catch (\Throwable $ex) {
            return false;
        }
        return false;
    }

    /**
     * Find the image url in the XML returned from API
     *
     * @param string $rawXML XML returned from API
     *
     * @return string url of the image
     */
    protected function getImageUrl($rawXML)
    {
        if (!str_contains($rawXML, 'ResourceLink')) {
            return '';
        }

        // This is already wrapped in try..catch
        $xml = new SimpleXMLElement($rawXML);

        foreach ($xml->getDocNamespaces() as $strPrefix => $strNamespace) {
            if (strlen($strPrefix) == 0) {
                $strPrefix = '_'; // Assign an arbitrary namespace prefix.
            }
            $xml->registerXPathNamespace($strPrefix, $strNamespace);
        }

        $result = $xml->xpath(
            '//_:SupportingResource[_:ResourceContentType="01"]' .
            '/_:ResourceVersion/_:ResourceLink'
        );
        return trim($result[0]);
    }
}
