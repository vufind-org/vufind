<?php

/**
 * Class Feedback
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2022.
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
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

declare(strict_types=1);

namespace VuFind\Db\Table;

use Laminas\Db\Adapter\Adapter;
use Laminas\Paginator\Paginator;
use VuFind\Db\Row\RowGateway;

use function intval;

/**
 * Class Feedback
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Feedback extends Gateway
{
    /**
     * Constructor
     *
     * @param Adapter         $adapter Database adapter
     * @param PluginManager   $tm      Table manager
     * @param array           $cfg     Laminas configuration
     * @param RowGateway|null $rowObj  Row prototype object (null for default)
     * @param string          $table   Name of database table to interface with
     */
    public function __construct(
        Adapter $adapter,
        PluginManager $tm,
        $cfg,
        ?RowGateway $rowObj = null,
        $table = 'feedback'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Get feedback by filter
     *
     * @param string|null $formName Form name
     * @param string|null $siteUrl  Site URL
     * @param string|null $status   Current status
     * @param string|null $page     Current page
     * @param int         $limit    Limit per page
     *
     * @return Paginator
     */
    public function getFeedbackByFilter(
        ?string $formName = null,
        ?string $siteUrl = null,
        ?string $status = null,
        ?string $page = null,
        int $limit = 20
    ): Paginator {
        $sql = $this->getSql();
        $select = $sql->select()->columns(
            [
                '*',
                'user_name' => new \Laminas\Db\Sql\Expression(
                    "CONCAT_WS(' ', u.firstname, u.lastname)"
                ),
                'manager_name' => new \Laminas\Db\Sql\Expression(
                    "CONCAT_WS(' ', m.firstname, m.lastname)"
                ),
            ]
        );
        if (null !== $formName) {
            $select->where->equalTo('form_name', $formName);
        }
        if (null !== $siteUrl) {
            $select->where->equalTo('site_url', $siteUrl);
        }
        if (null !== $status) {
            $select->where->equalTo('status', $status);
        }
        $select->join(
            ['u' => 'user'],
            'u.id = feedback.user_id',
            [],
            $select::JOIN_LEFT
        )->join(
            ['m' => 'user'],
            'm.id = feedback.updated_by',
            [],
            $select::JOIN_LEFT
        )->order('created DESC');

        $page = null === $page ? null : intval($page);
        if (null !== $page) {
            $select->limit($limit);
            $select->offset($limit * ($page - 1));
        }
        $adapter = new \Laminas\Paginator\Adapter\LaminasDb\DbSelect($select, $sql);
        $paginator = new \Laminas\Paginator\Paginator($adapter);
        $paginator->setItemCountPerPage($limit);
        if (null !== $page) {
            $paginator->setCurrentPageNumber($page);
        }
        return $paginator;
    }

    /**
     * Delete feedback by ids
     *
     * @param array $ids IDs
     *
     * @return int Count of deleted rows
     */
    public function deleteByIdArray(array $ids): int
    {
        // Do nothing if we have no IDs to delete!
        if (empty($ids)) {
            return 0;
        }
        $callback = function ($select) use ($ids) {
            $select->where->in('id', $ids);
        };
        return $this->delete($callback);
    }
}
