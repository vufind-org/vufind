<?php

/**
 * Redis driver for statistics
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @package  Statistics
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Statistics\Driver;

use Psr\Log\LoggerAwareInterface;
use VuFind\Log\LoggerAwareTrait;

/**
 * Redis driver for statistics
 *
 * @category VuFind
 * @package  Statistics
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Redis implements DriverInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Default Redis key prefix
     *
     * @var string
     */
    public const DEFAULT_KEY_PREFIX = 'finna_statistics/';

    /**
     * Session start key
     *
     * @var string
     */
    public const KEY_SESSION = 'session';

    /**
     * Page view key
     *
     * @var string
     */
    public const KEY_PAGE_VIEW = 'page_view';

    /**
     * Record view key (detailed)
     *
     * @var string
     */
    public const KEY_RECORD_VIEW = 'record_view';

    /**
     * Redis client
     *
     * @var \Credis_Client
     */
    protected $redisClient;

    /**
     * Queue name prefix
     *
     * @var string
     */
    protected $keyPrefix;

    /**
     * Constructor
     *
     * @param \Credis_Client $redisClient Redis client
     * @param string         $keyPrefix   Redis key prefix
     */
    public function __construct(\Credis_Client $redisClient, string $keyPrefix)
    {
        $this->redisClient = $redisClient;
        $this->keyPrefix = $keyPrefix;
    }

    /**
     * Add a new session to statistics
     *
     * @param string $institution Institution code
     * @param string $view        View subpath (empty string for default view)
     * @param int    $type        Request type bitmap
     * @param array  $session     Session data
     *
     * @return void
     */
    public function addNewSession(
        string $institution,
        string $view,
        int $type,
        array $session
    ): void {
        $date = date('Y-m-d');
        $crawler = $type;
        $params = compact('institution', 'view', 'crawler', 'date');
        $this->processAdd(static::KEY_SESSION, $params);
    }

    /**
     * Add a page view to statistics
     *
     * @param string $institution Institution code
     * @param string $view        View subpath (empty string for default view)
     * @param int    $type        Request type bitmap
     * @param string $controller  Controller
     * @param string $action      Action
     *
     * @return void
     */
    public function addPageView(
        string $institution,
        string $view,
        int $type,
        string $controller,
        string $action
    ): void {
        $date = date('Y-m-d');
        $crawler = $type;
        $params = compact(
            'institution',
            'view',
            'crawler',
            'controller',
            'action',
            'date'
        );
        $this->processAdd(static::KEY_PAGE_VIEW, $params);
    }

    /**
     * Add a record view to statistics
     *
     * @param string $institution Institution code
     * @param string $view        View subpath (empty string for default view)
     * @param int    $type        Request type bitmap
     * @param string $backend     Backend ID
     * @param string $source      Record source
     * @param string $recordId    Record ID
     * @param array  $formats     Record formats
     * @param array  $rights      Record usage rights
     * @param int    $online      Whether the record is available online (0 = no,
     * 1 = yes, 2 = freely)
     *
     * @return void
     */
    public function addRecordView(
        string $institution,
        string $view,
        int $type,
        string $backend,
        string $source,
        string $recordId,
        array $formats,
        array $rights,
        int $online
    ): void {
        $date = date('Y-m-d');

        // Summary log:
        $crawler = $type;
        $params = compact(
            'institution',
            'view',
            'crawler',
            'date',
            'backend',
            'source',
            'online'
        );
        $params['record_id'] = $recordId;
        $params['formats'] = implode('|', $formats);
        $params['usage_rights'] = implode('|', $rights);
        $this->processAdd(static::KEY_RECORD_VIEW, $params);
    }

    /**
     * Add or update a statistics table entry
     *
     * @param string $key    Key name
     * @param array  $params Row identification params
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function processAdd(string $key, array $params): void
    {
        // Add a version indicator for any future needs:
        $params['v'] = 1;
        $this->redisClient->lPush($this->keyPrefix . $key, json_encode($params));
    }
}
