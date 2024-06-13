<?php

/**
 * Local database-driven URL shortener.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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

use Exception;
use VuFind\Db\Service\ShortlinksServiceInterface;

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
     * @param string                     $baseUrl       Base URL of current VuFind site
     * @param ShortlinksServiceInterface $service       Shortlinks database service
     * @param string                     $salt          HMacKey from config
     * @param string                     $hashAlgorithm Hash algorithm to use
     */
    public function __construct(
        protected string $baseUrl,
        protected ShortlinksServiceInterface $service,
        protected string $salt,
        protected string $hashAlgorithm = 'md5'
    ) {
    }

    /**
     * Generate a short hash using the base62 algorithm (and write a row to the
     * database).
     *
     * @param string $path Path to store in database
     *
     * @return string
     */
    protected function getBase62Hash(string $path): string
    {
        $row = $this->service->createAndPersistEntityForPath($path);
        $b62 = new \VuFind\Crypt\Base62();
        $hash = $b62->encode($row->getId());
        $row->setHash($hash);
        $this->service->persistEntity($row);
        return $hash;
    }

    /**
     * Support method for getGenericHash(): do the work of picking a short version
     * of the hash and writing to the database as needed.
     *
     * @param string $path   Path to store in database
     * @param string $hash   Hash of $path (generated in getGenericHash)
     * @param int    $length Minimum number of characters from hash to use for
     * lookups (may be increased to enforce uniqueness)
     *
     * @throws Exception
     * @return string
     */
    protected function saveAndShortenHash($path, $hash, $length)
    {
        // Validate hash length:
        if ($length > $this->maxHashLength) {
            throw new \Exception(
                'Could not generate unique hash under ' . $this->maxHashLength
                . ' characters in length.'
            );
        }
        $shorthash = str_pad(substr($hash, 0, $length), $length, '_');
        $match = $this->service->getShortLinkByHash($shorthash);

        // Brand new hash? Create row and return:
        if (!$match) {
            $newEntity = $this->service->createEntity()->setPath($path)->setHash($shorthash);
            $this->service->persistEntity($newEntity);
            return $shorthash;
        }

        // If we got this far, the hash already exists; let's check if it matches
        // the path...
        if ($match->getHash() === $path) {
            return $shorthash;
        }

        // If we got here, we have encountered an unexpected hash collision. Let's
        // disambiguate by making it one character longer:
        return $this->saveAndShortenHash($path, $hash, $length + 1);
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
        // Generate short hash within a transaction to avoid odd timing-related
        // problems:
        $this->service->beginTransaction();
        try {
            $shortHash = $this->saveAndShortenHash($path, $hash, $this->preferredHashLength);
        } catch (Exception $e) {
            $this->service->rollBackTransaction();
            throw $e;
        }
        $this->service->commitTransaction();
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
            ? $this->getBase62Hash($path) : $this->getGenericHash($path);

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
     * @throws Exception
     */
    public function resolve($input)
    {
        $match = $this->service->getShortLinkByHash($input);
        if (!$match) {
            throw new Exception('Shortlink could not be resolved: ' . $input);
        }

        return $this->baseUrl . $match->getPath();
    }
}
