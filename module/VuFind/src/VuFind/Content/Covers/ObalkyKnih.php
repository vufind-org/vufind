<?php
/**
 * Obalkyknih cover content loader.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Content
 * @author   Josef Moravec <josef.moravec@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Content\Covers;

class ObalkyKnih extends \VuFind\Content\AbstractCover
    implements \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * API URL
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * Constructor
     *
     * @param string $associate Associate ID
     * @param string $secret    Secret key
     */
    public function __construct($config)
    {
        $this->supportsIsbn = true;
        $this->supportsIssn = true;
        $this->supportsOclc = true;
        $this->cacheAllowed = true;

        $this->apiUrl = isset($config->url) ? 
            $config->url : "https://cache.obalkyknih.cz/api/books";
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
        $imageUrl = false;
        $param = "multi";
        $query = [];
        if (isset($ids['isbn'])) {
            $query['isbn'] = $ids['isbn']->get13();
        }

        if (isset($ids['issn'])) {
             $query['issn'] = $ids['issn'];
        }

        if (isset($ids['oclc'])) {
             $query['oclc'] = $ids['oclc'];
        }

        $url = $this->apiUrl ."?" . $param . "=" . json_encode([$query]);
        

        $response = $this->getHttpClient($url)->send();

        if ($response->isSuccess()) {
            $json = json_decode($response->getBody(), true);
            switch ($size) {
            case 'small':
                $imageUrl = $json[0]["cover_medium_url"];
                break;
            case 'medium':
                $imageUrl = $json[0]["cover_medium_url"];
                break;
            case 'large':
                $imageUrl = $json[0]["cover_preview510_url"];
                break;
            default:
                $imageUrl = $json[0]["cover_medium_url"];
                break;
            }
        } 

        return $imageUrl;
    }
}

