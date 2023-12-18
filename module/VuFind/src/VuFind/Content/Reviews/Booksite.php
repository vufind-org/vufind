<?php

/**
 * Booksite review content loader.
 *
 * PHP version 8
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Content\Reviews;

/**
 * Booksite review content loader.
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Booksite extends \VuFind\Content\AbstractBase
{
    /**
     * Base URL for Booksite
     *
     * @var string
     */
    protected $url;

    /**
     * API key for Booksite
     *
     * @var string
     */
    protected $apiKey;

    /**
     * Constructor
     *
     * @param string $url    Base URL for Booksite
     * @param string $apiKey API key for Booksite
     */
    public function __construct($url, $apiKey)
    {
        $this->url = $url;
        $this->apiKey = $apiKey;
    }

    /**
     * Booksite
     *
     * Connects to Booksite's API and retrieves reviews for the specific ISBN
     *
     * @param string           $key     API key (unused here)
     * @param \VuFindCode\ISBN $isbnObj ISBN object
     *
     * @throws \Exception
     * @return array     Returns array with review data.
     * @author Joe Atzberger
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function loadByIsbn($key, \VuFindCode\ISBN $isbnObj)
    {
        $reviews = []; // Initialize return value

        $isn = $this->getIsbn10($isbnObj);
        $url = $this->url . '/poca/book/tradereviews?apikey=' . $this->apiKey
            . '&ean=' . $isn;
        $response = $this->getHttpClient($url)->send();
        if (!$response->isSuccess()) {
            $this->logWarning(
                'Reviews: ' . $response->getStatusCode() . ' '
                . $response->getReasonPhrase() . " $url"
            );
            return $reviews;    // still empty
        }
        $this->debug(
            'Reviews: ' . $response->getStatusCode() . ' '
            . $response->getReasonPhrase() . " $url"
        );

        $i = 0;
        $json = json_decode($response->getBody());
        foreach ($json as $source => $values) {
            $reviews[$i]['Source'] = $source;
            $reviews[$i]['Date'] = (string)$values->reviewDate;
            $reviews[$i]['Content'] = (string)$values->reviewText;
            $i++;
        }

        return $reviews;
    }
}
