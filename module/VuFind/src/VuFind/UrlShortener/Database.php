<?php

/**
 * Local database-driven URL shortener.
 *
 * PHP version 7
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
use VuFind\Db\Table\Shortlinks as ShortlinksTable;

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
     * Table containing shortlinks
     *
     * @var ShortlinksTable
     */
    protected $table;

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
     * @param string          $baseUrl       Base URL of current VuFind site
     * @param ShortlinksTable $table         Shortlinks database table
     * @param string          $salt          HMacKey from config
     * @param string          $hashAlgorithm Hash algorithm to use
     */
    public function __construct(
        string $baseUrl,
        ShortlinksTable $table,
        string $salt,
        string $hashAlgorithm = 'md5'
    ) {
        $this->baseUrl = $baseUrl;
        $this->table = $table;
        $this->salt = $salt;
        $this->hashAlgorithm = $hashAlgorithm;
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
        $this->table->insert(['path' => $path]);
        $id = $this->table->getLastInsertValue();
        $row = $this->table->select(['id' => $id])->current();
        $b62 = new \VuFind\Crypt\Base62();
        $row->hash = $b62->encode($id);
        $row->save();
        return $row->hash;
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
        $results = $this->table->select(['hash' => $shorthash]);

        // Brand new hash? Create row and return:
        if ($results->count() == 0) {
            $this->table->insert(['path' => $path, 'hash' => $shorthash]);
            return $shorthash;
        }

        // If we got this far, the hash already exists; let's check if it matches
        // the path...
        if ($results->current()['path'] === $path) {
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
        $connection = $this->table->getAdapter()->getDriver()->getConnection();
        $connection->beginTransaction();
        $shortHash = $this
            ->saveAndShortenHash($path, $hash, $this->preferredHashLength);
        $connection->commit();
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
     *
     * @throws Exception
     */
    public function resolve($input)
    {
        $results = $this->table->select(['hash' => $input]);
        if ($results->count() !== 1) {
            throw new Exception('Shortlink could not be resolved: ' . $input);
        }

        return $this->baseUrl . $results->current()['path'];
    }
}
