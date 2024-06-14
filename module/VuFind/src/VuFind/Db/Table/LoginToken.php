<?php

/**
 * Table Definition for login_token
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023-2024.
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
 * @package  Db_Table
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Db\Table;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\Sql\Expression;
use VuFind\Db\Row\LoginToken as LoginTokenRow;
use VuFind\Db\Row\RowGateway;
use VuFind\Exception\LoginToken as LoginTokenException;

/**
 * Table Definition for login_token
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class LoginToken extends Gateway
{
    use ExpirationTrait;

    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Laminas configuration
     * @param RowGateway    $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(
        Adapter $adapter,
        PluginManager $tm,
        $cfg,
        ?RowGateway $rowObj = null,
        $table = 'login_token'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Check if a login token matches one in database.
     *
     * @param array $token array containing user id, token and series
     *
     * @return ?LoginTokenRow
     * @throws LoginTokenException
     */
    public function matchToken(array $token): ?LoginTokenRow
    {
        $userId = null;
        foreach ($this->getBySeries($token['series']) as $row) {
            $userId = $row->user_id;
            if (hash_equals($row['token'], hash('sha256', $token['token']))) {
                if (time() > $row['expires']) {
                    $row->delete();
                    return null;
                }
                return $row;
            }
        }
        if ($userId) {
            throw new LoginTokenException('Tokens do not match', $userId);
        }
        return null;
    }

    /**
     * Delete all tokens in a given series
     *
     * @param string $series         series
     * @param ?int   $currentTokenId Current token ID to keep
     *
     * @return void
     */
    public function deleteBySeries(string $series, ?int $currentTokenId = null): void
    {
        $callback = function ($select) use ($series, $currentTokenId) {
            $select->where->equalTo('series', $series);
            if ($currentTokenId) {
                $select->where->notEqualTo('id', $currentTokenId);
            }
        };
        $this->delete($callback);
    }

    /**
     * Delete all tokens for a user
     *
     * @param int $userId user identifier
     *
     * @return void
     */
    public function deleteByUserId(int $userId): void
    {
        $this->delete(['user_id' => $userId]);
    }

    /**
     * Get tokens for a given user
     *
     * @param int  $userId  User identifier
     * @param bool $grouped Whether to return results grouped by series
     *
     * @return array
     */
    public function getByUserId(int $userId, bool $grouped = true): array
    {
        $callback = function ($select) use ($userId, $grouped) {
            $select->where->equalTo('user_id', $userId);
            $select->order('last_login DESC');
            if ($grouped) {
                $select->columns(
                    [
                        // RowGateway requires an id field:
                        'id' => new Expression(
                            '1',
                            [],
                            [Expression::TYPE_IDENTIFIER]
                        ),
                        'series',
                        'user_id',
                        'last_login' => new Expression(
                            'MAX(?)',
                            ['last_login'],
                            [Expression::TYPE_IDENTIFIER]
                        ),
                        'browser',
                        'platform',
                        'expires',
                    ]
                );
                $select->group(['series', 'user_id', 'browser', 'platform', 'expires']);
            }
        };
        return iterator_to_array($this->select($callback));
    }

    /**
     * Get token by series
     *
     * @param string $series Series identifier
     *
     * @return ResultSetInterface
     */
    public function getBySeries(string $series): ResultSetInterface
    {
        return $this->select(compact('series'));
    }

    /**
     * Update the select statement to find records to delete.
     *
     * @param Select $select    Select clause
     * @param string $dateLimit Date threshold of an "expired" record in format
     * 'Y-m-d H:i:s'.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function expirationCallback($select, $dateLimit)
    {
        // Date limit ignored since login token already contains an expiration time.
        $select->where->lessThan('expires', time());
    }
}
