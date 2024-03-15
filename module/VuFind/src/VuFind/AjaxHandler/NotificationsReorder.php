<?php

/**
 * AJAX handler to change the order of notifications.
 *
 * PHP version 8
 *
 * Copyright (C) effective WEBWORK GmbH 2023.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Db\Table\Broadcasts;
use VuFind\Db\Table\Pages;

/**
 * AJAX handler to change the order of notifications.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class NotificationsReorder extends AbstractBase
{
    /**
     * Database table for pages
     *
     * @var Pages
     */
    private $pagesTable;

    /**
     * Database table for broadcasts
     *
     * @var Broadcasts
     */
    private $broadcastsTable;

    /**
     * Constructor
     *
     * @param Pages      $pagesTable      Database table for pages
     * @param Broadcasts $broadcastsTable Database table for broadcasts
     */
    public function __construct(Pages $pagesTable, Broadcasts $broadcastsTable)
    {
        $this->pagesTable = $pagesTable;
        $this->broadcastsTable = $broadcastsTable;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $order = $params->fromPost('order', $params->fromQuery('order', []));
        $type = $params->fromPost('type', $params->fromQuery('type', []));

        if ($type == 'page') {
            foreach ($order as $index => $page_id) {
                $this->pagesTable->setPriorityForPageId($index, $page_id);
            }
        }
        if ($type == 'broadcast') {
            foreach ($order as $index => $broadcast_id) {
                $this->broadcastsTable->setPriorityForBroadcastId($index, $broadcast_id);
            }
        }

        $result = ['success' => true];
        return $this->formatResponse(compact($result));
    }
}
