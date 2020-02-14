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
    const HASH_ALGO = 'md5';

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
     * Constructor
     *
     * @param string       $baseUrl Base URL of current VuFind site     *
     * @param ShortlinksTable $table   Shortlinks database table
     * @param string          $salt    HMacKey from config
     */
    public function __construct(
        string $baseUrl,
        ShortlinksTable $table,
        string $salt
    ) {
        $this->baseUrl = $baseUrl;
        $this->table = $table;
        $this->salt = $salt;
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
        $path = str_replace($this->baseUrl, '', $url);
        $hash = hash(static::HASH_ALGO, $path . $this->salt);
        $shorthash = substr($hash, 0, 9);
        $results = $this->table->select(['hash' => $shorthash]);

        // this should almost never happen - we then return the existing hash
        if ($results->count() == 0) {
            $this->table->insert(['path' => $path, 'hash' => $shorthash]);
        }

        return $this->baseUrl . '/short/' . $shorthash;
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
        $shorthash = substr($input, 0, 9);
        $results = $this->table->select(['hash' => $shorthash]);
        if ($results->count() !== 1) {
            throw new Exception('Shortlink could not be resolved: ' . $shorthash);
        }

        return $this->baseUrl . $results->current()['path'];
    }
}
