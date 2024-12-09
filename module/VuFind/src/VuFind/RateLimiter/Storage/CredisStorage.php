<?php

/**
 * Credis storage adapter for Rate Limiter.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Cache
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\RateLimiter\Storage;

use Symfony\Component\RateLimiter\LimiterStateInterface;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * Credis storage adapter for Rate Limiter.
 *
 * @category VuFind
 * @package  Cache
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CredisStorage implements StorageInterface
{
    /**
     * Redis version
     *
     * @var int
     */
    protected $redisVersion;

    /**
     * Cache namespace
     *
     * @var string
     */
    protected $namespace;

    /**
     * Constructor
     *
     * @param \Credis_Client $redis  Redis connection object
     * @param array          $config Redis configuration
     * config.ini)
     */
    public function __construct(protected \Credis_Client $redis, protected array $config = [])
    {
        $this->redisVersion = (int)($config['redis_version'] ?? 6);
        $this->namespace = (string)($config['namespace'] ?? '');
    }

    /**
     * Save limiter state
     *
     * @param LimiterStateInterface $limiterState Limiter state
     *
     * @return void
     */
    public function save(LimiterStateInterface $limiterState): void
    {
        $options = [];
        if (null !== ($expireAfter = $limiterState->getExpirationTime())) {
            $options['EX'] = $expireAfter;
        }
        $this->redis->set($this->createRedisKey($limiterState->getId()), serialize($limiterState), $options);
    }

    /**
     * Get limiter state by ID
     *
     * @param string $limiterStateId Limiter state ID
     *
     * @return ?LimiterStateInterface
     */
    public function fetch(string $limiterStateId): ?LimiterStateInterface
    {
        $state = $this->redis->get($this->createRedisKey($limiterStateId));
        return $state !== false ? unserialize($state) : null;
    }

    /**
     * Delete limiter state by ID
     *
     * @param string $limiterStateId Limiter state ID
     *
     * @return void
     */
    public function delete(string $limiterStateId): void
    {
        $this->redis->get($this->createRedisKey($limiterStateId));
        $unlinkMethod = ($this->redisVersion >= 4) ? 'unlink' : 'del';
        $this->redis->$unlinkMethod($this->createRedisKey($limiterStateId));
    }

    /**
     * Create a Redis key from a Limiter state ID
     *
     * @param string $id Limiter state ID
     *
     * @return string
     */
    protected function createRedisKey(string $id): string
    {
        $id = sha1($id);
        return $this->namespace ? "$this->namespace/$id" : $id;
    }
}
