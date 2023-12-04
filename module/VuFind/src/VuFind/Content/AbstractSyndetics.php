<?php

/**
 * Abstract base for Syndetics content loader plug-ins.
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

namespace VuFind\Content;

use DOMDocument;

/**
 * Abstract base for Syndetics content loader plug-ins.
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractSyndetics extends AbstractBase
{
    /**
     * Use SSL URLs?
     *
     * @var bool
     */
    protected $useSSL;

    /**
     * Use Syndetics plus?
     *
     * @var bool
     */
    protected $usePlus;

    /**
     * HTTP timeout for API calls (in seconds)
     *
     * @var int
     */
    protected $timeout;

    /**
     * Constructor
     *
     * @param bool $useSSL  Use SSL URLs?
     * @param bool $usePlus Use Syndetics Plus?
     * @param int  $timeout HTTP timeout for API calls (in seconds)
     */
    public function __construct($useSSL = false, $usePlus = false, $timeout = 10)
    {
        $this->useSSL = $useSSL;
        $this->usePlus = $usePlus;
        $this->timeout = $timeout;
    }

    /**
     * Get an HTTP client
     *
     * @param string $url URL for client to use
     *
     * @return \Laminas\Http\Client
     * @throws \Exception
     */
    protected function getHttpClient($url = null)
    {
        $client = parent::getHttpClient($url);
        $client->setOptions(['timeout' => $this->timeout]);
        return $client;
    }

    /**
     * Get the Syndetics URL for making a request.
     *
     * @param string $isbn ISBN to load
     * @param string $id   Client ID
     * @param string $file File to request
     * @param string $type Type parameter
     *
     * @return string
     */
    protected function getIsbnUrl($isbn, $id, $file = 'index.xml', $type = 'rw12,h7')
    {
        $baseUrl = $this->useSSL
            ? 'https://secure.syndetics.com' : 'http://syndetics.com';
        $url = $baseUrl . '/index.aspx?isbn=' . $isbn
            . '/' . $file . '&client=' . $id . '&type=' . $type;
        $this->debug('Syndetics request: ' . $url);
        return $url;
    }

    /**
     * Turn an XML response into a DOMDocument object.
     *
     * @param string $xml XML to load.
     *
     * @return DOMDocument|bool Document on success, false on failure.
     */
    protected function xmlToDOMDocument($xml)
    {
        $dom = new DOMDocument();
        return $dom->loadXML($xml) ? $dom : false;
    }
}
