<?php
/**
 * Amazon cover content loader.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Content\Covers;
use ZendService\Amazon\Amazon as AmazonService;

/**
 * Amazon cover content loader.
 *
 * @category VuFind2
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Amazon extends \VuFind\Content\AbstractCover
    implements \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Associate ID
     *
     * @var string
     */
    protected $associate;

    /**
     * Secret key
     *
     * @var string
     */
    protected $secret;

    /**
     * Constructor
     *
     * @param string $associate Associate ID
     * @param string $secret    Secret key
     */
    public function __construct($associate, $secret)
    {
        $this->associate = $associate;
        $this->secret = $secret;
        $this->supportsIsbn = true;
    }

    /**
     * Get an HTTP client
     *
     * @param string $url URL for client to use
     *
     * @return \Zend\Http\Client
     */
    protected function getHttpClient($url = null)
    {
        if (null === $this->httpService) {
            throw new \Exception('HTTP service missing.');
        }
        return $this->httpService->createClient($url);
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
        try {
            $params = [
                'ResponseGroup' => 'Images', 'AssociateTag' => $this->associate
            ];
            // TODO: add support for 13-digit ISBNs (requires extra lookup)
            $isbn = isset($ids['isbn']) ? $ids['isbn']->get10() : false;
            if (!$isbn) {
                return false;
            }
            $result = $this->getAmazonService($key)->itemLookup($isbn, $params);
        } catch (\Exception $e) {
            // Something went wrong?  Just report failure:
            return false;
        }

        // Where in the response can we find the URL we need?
        switch ($size) {
        case 'small':
        case 'medium':
        case 'large':
            $imageIndex = ucwords($size) . 'Image';
            break;
        default:
            $imageIndex = false;
            break;
        }

        if ($imageIndex && isset($result->$imageIndex->Url)) {
            $imageUrl = (string)$result->$imageIndex->Url;
            return $imageUrl;
        }

        return false;
    }

    /**
     * Get an AmazonService object for the specified key.
     *
     * @param string $key API key
     *
     * @return AmazonService
     */
    protected function getAmazonService($key)
    {
        $service = new AmazonService($key, 'US', $this->secret);
        $service->getRestClient()->setHttpClient($this->getHttpClient());
        return $service;
    }
}
