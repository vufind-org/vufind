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

use VuFind\Db\Table\Shortlinks as ShortlinksTable;

/**
 * Local database-driven URL shortener.
 *
 * @category VuFind
 * @package  UrlShortener
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Database implements UrlShortenerInterface
{
    const BASE62_ALPHABET
        = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    const BASE62_BASE = 62;

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
     * Constructor
     *
     * @param string          $baseUrl Base URL of current VuFind site
     * @param ShortlinksTable $table   Shortlinks database table
     */
    public function __construct(string $baseUrl, ShortlinksTable $table)
    {
        $this->baseUrl = $baseUrl;
        $this->table = $table;
    }

    /**
     * Common base62 encoding function.
     * Implemented here so we don't need additional PHP modules like bcmath.
     *
     * @param string $base10Number Number to encode
     *
     * @return string
     *
     * @throws \Exception
     */
    protected function base62Encode($base10Number)
    {
        $binaryNumber = intval($base10Number);
        if ($binaryNumber === 0) {
            throw new \Exception('not a base10 number: "' . $base10Number . '"');
        }

        $base62Number = '';
        while ($binaryNumber != 0) {
            $base62Number = self::BASE62_ALPHABET[$binaryNumber % self::BASE62_BASE]
                . $base62Number;
            $binaryNumber = intdiv($binaryNumber, self::BASE62_BASE);
        }

        return ($base62Number == '') ? '0' : $base62Number;
    }

    /**
     * Common base62 decoding function.
     * Implemented here so we don't need additional PHP modules like bcmath.
     *
     * @param string $base62Number Number to decode
     *
     * @return int
     *
     * @throws \Exception
     */
    protected function base62Decode($base62Number)
    {
        $binaryNumber = 0;
        for ($i = 0; $i < strlen($base62Number); ++$i) {
            $digit = $base62Number[$i];
            $strpos = strpos(self::BASE62_ALPHABET, $digit);
            if ($strpos === false) {
                throw new \Exception('not a base62 digit: "' . $digit . '"');
            }

            $binaryNumber *= self::BASE62_BASE;
            $binaryNumber += $strpos;
        }
        return $binaryNumber;
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
        $this->table->insert(['path' => $path]);
        $id = $this->table->getLastInsertValue();

        $shortUrl = $this->baseUrl . '/short/' . $this->base62Encode($id);
        return $shortUrl;
    }

    /**
     * Resolve URL from Database via id.
     *
     * @param string $id ID to resolve
     *
     * @return string
     */
    public function resolve($id)
    {
        $results = $this->table->select(['id' => $this->base62Decode($id)]);
        if ($results->count() !== 1) {
            throw new \Exception('Shortlink could not be resolved: ' . $id);
        }

        return $this->baseUrl . $results->current()['path'];
    }
}
