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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
     * Constructor
     *
     * @param int $timeout HTTP timeout for API calls (in seconds)
     */
    public function __construct(protected int $timeout = 10)
    {
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
        $url = 'https://secure.syndetics.com/index.aspx?isbn=' . $isbn
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
