<?php

/**
 * Local database-driven URL shortener.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @package  UrlShortener
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\UrlShortener;

use VuFind\Db\Service\ShortlinksService;

/**
 * Local database-driven URL shortener.
 *
 * @category VuFind
 * @package  UrlShortener
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Database implements UrlShortenerInterface
{
    /**
     * Hash algorithm to use
     *
     * @var string
     */
    protected $hashAlgorithm;

    /**
     * Base URL of current VuFind site
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Shortlinks database service
     *
     * @var ShortlinksService
     */
    protected $shortlinksService;

    /**
     * HMacKey from config
     *
     * @var string
     */
    protected $salt;

    /**
     * When using a hash algorithm other than base62, the preferred number of
     * characters to use from the hash in the URL (more may be used for
     * disambiguation when necessary).
     *
     * @var int
     */
    protected $preferredHashLength = 9;

    /**
     * The maximum allowed hash length (tied to the width of the database hash
     * column); if we can't generate a unique hash under this length, something
     * has gone very wrong.
     *
     * @var int
     */
    protected $maxHashLength = 32;

    /**
     * Constructor
     *
     * @param string            $baseUrl           Base URL of current VuFind site
     * @param ShortlinksService $shortlinksService Shortlinks database service
     * @param string            $salt              HMacKey from config
     * @param string            $hashAlgorithm     Hash algorithm to use
     */
    public function __construct(
        string $baseUrl,
        ShortlinksService $shortlinksService,
        string $salt,
        string $hashAlgorithm = 'md5'
    ) {
        $this->baseUrl = $baseUrl;
        $this->shortlinksService = $shortlinksService;
        $this->salt = $salt;
        $this->hashAlgorithm = $hashAlgorithm;
    }

    /**
     * Generate a short hash using the configured algorithm (and write a row to the
     * database if the link is new).
     *
     * @param string $path Path to store in database
     *
     * @return string
     */
    protected function getGenericHash(string $path): string
    {
        $hash = hash($this->hashAlgorithm, $path . $this->salt);
        $shortHash = $this->shortlinksService
            ->saveAndShortenHash(
                $path,
                $hash,
                $this->preferredHashLength,
                $this->maxHashLength
            );
        return $shortHash;
    }

    /**
     * Given a URL, create a database entry (if necessary) and return the hash
     * value for inclusion in the short URL.
     *
     * @param string $url URL
     *
     * @return string
     */
    protected function getShortHash(string $url): string
    {
        $path = str_replace($this->baseUrl, '', $url);

        // We need to handle things differently depending on whether we're
        // using the legacy base62 algorithm, or a different hash mechanism.
        $shorthash = $this->hashAlgorithm === 'base62'
            ? $this->shortlinksService->getBase62Hash($path)
            : $this->getGenericHash($path);

        return $shorthash;
    }

    /**
     * Generate & store shortened URL in Database.
     *
     * @param string $url URL
     *
     * @return string
     */
    public function shorten($url)
    {
        return $this->baseUrl . '/short/' . $this->getShortHash($url);
    }

    /**
     * Resolve URL from Database via id.
     *
     * @param string $input hash
     *
     * @return string
     */
    public function resolve($input)
    {
        return $this->shortlinksService->resolve($input, $this->baseUrl);
    }
}
