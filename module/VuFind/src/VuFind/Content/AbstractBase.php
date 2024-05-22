<?php

/**
 * Abstract base for content loader plug-ins.
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

use VuFindCode\ISBN;

use function is_object;

/**
 * Abstract base for content loader plug-ins.
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractBase implements
    \VuFindHttp\HttpServiceAwareInterface,
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Attempt to get an ISBN-10; revert to ISBN-13 only when ISBN-10 representation
     * is impossible.
     *
     * @param ISBN $isbnObj ISBN object to convert
     *
     * @return string
     */
    protected function getIsbn10($isbnObj)
    {
        $isbn = is_object($isbnObj) ? $isbnObj->get10() : false;
        return (!$isbn && is_object($isbnObj)) ? $isbnObj->get13() : $isbn;
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
        if (null === $this->httpService) {
            throw new \Exception('HTTP service missing.');
        }
        return $this->httpService->createClient($url);
    }

    /**
     * Load results for a particular API key and ISBN.
     *
     * @param string $key     API key
     * @param ISBN   $isbnObj ISBN object
     *
     * @return array|string For array of strings returned, they all are escaped in the template and presented as list.
     * If string is returned it is considered as raw HTML and is NOT escaped.
     */
    abstract public function loadByIsbn($key, ISBN $isbnObj);
}
