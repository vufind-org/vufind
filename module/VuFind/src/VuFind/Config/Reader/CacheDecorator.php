<?php

/**
 * Cache decorator.
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
 * @package  Config
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Config\Reader;

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Config\Reader\ReaderInterface;

/**
 * This class decorates a configuration file reader with caching support.
 *
 * @category VuFind
 * @package  Config
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class CacheDecorator implements ReaderInterface
{
    /**
     * The decorated reader.
     *
     * @var ReaderInterface
     */
    protected $reader;

    /**
     * Cache storage.
     *
     * @var StorageInterface
     */
    protected $storage;

    /**
     * Constructor.
     *
     * @param ReaderInterface  $reader  Config reader
     * @param StorageInterface $storage Cache storage
     *
     * @return void
     */
    public function __construct(ReaderInterface $reader, StorageInterface $storage)
    {
        $this->reader  = $reader;
        $this->storage = $storage;
    }

    /**
     * Read from a file and create an array
     *
     * @param string $filename Filename
     *
     * @return array
     */
    public function fromFile($filename)
    {
        $absFilename = realpath($filename);
        $mtime = @filemtime($absFilename);
        $key = $this->generateKey($mtime . $absFilename);
        if ($this->storage->hasItem($key)) {
            return $this->storage->getItem($key);
        }
        $config = $this->reader->fromFile($filename);
        $this->storage->setItem($key, $config);
        return $config;
    }

    /**
     * Read from a string and create an array
     *
     * @param string $string String
     *
     * @return array|bool
     */
    public function fromString($string)
    {
        $key = $this->generateKey($string);
        if ($this->storage->hasItem($key)) {
            return $this->storage->getItem($key);
        }
        $config = $this->reader->fromString($string);
        $this->storage->setItem($key, $config);
        return $config;
    }

    /// Internal API

    /**
     * Return a cache key.
     *
     * @param string $string String
     *
     * @return string
     */
    protected function generateKey($string)
    {
        return md5($string);
    }
}
