<?php

namespace VuFind\UrlShortener;

use \VuFind\Config\PluginManager as Config;
use \VuFind\Db\Table\Shortlinks as ShortlinksTable;

class Database implements UrlShortenerInterface
{
    const BASE62_ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    const BASE62_BASE = 62;

    /**
     * Configuration object
     *
     * @var Config
     */
    protected $config;

    /**
     * Table containing shortlinks
     *
     * @var ShortlinksTable
     */
    protected $table;

    /**
     * Constructor
     *
     * @param Config $config
     * @param ShortlinksTable $table
     */
    public function __construct(Config $config, ShortlinksTable $table)
    {
        $this->config = $config;
        $this->table = $table;
    }

    /**
     * Common base62 encoding function.
     * Implemented here so we don't need additional PHP modules like bcmath.
     *
     * @param string $base10Number
     *
     * @return string
     *
     * @throws \Exception
     */
    protected function base62Encode($base10Number) {
        $binaryNumber = intval($base10Number);
        if ($binaryNumber === 0) {
            throw new \Exception('not a base10 number: "' . $base10Number . '"');
        }

        $base62Number = '';
        while ($binaryNumber != 0) {
            $base62Number = self::BASE62_ALPHABET[$binaryNumber % self::BASE62_BASE] . $base62Number;
            $binaryNumber = intdiv($binaryNumber, self::BASE62_BASE);
        }

        return ($base62Number == '') ? '0' : $base62Number;
    }

    /**
     * Common base62 decoding function.
     * Implemented here so we don't need additional PHP modules like bcmath.
     *
     * @param string $base62Number
     *
     * @return int
     *
     * @throws \Exception
     */
    protected function base62Decode($base62Number) {
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
        $baseUrl = $this->config->get('config')->Site->url;
        $path = str_replace($baseUrl, '', $url);
        $this->table->insert(['path' => $path]);
        $id = $this->table->getLastInsertValue();

        $shortUrl = $baseUrl . '/short/' . $this->base62Encode($id);
        return $shortUrl;
    }

    /**
     * Resolve URL from Database via id.
     *
     * @param string $id
     *
     * @return string
     */
    public function resolve($id) {
        $results = $this->table->select(['id' => $this->base62Decode($id)]);
        if (count($results) !== 1) {
            throw new \Exception('Shortlink could not be resolved: ' . $id);
        }

        $baseUrl = $this->config->get('config')->Site->url;
        return $baseUrl . $results->current()['path'];
    }
}
