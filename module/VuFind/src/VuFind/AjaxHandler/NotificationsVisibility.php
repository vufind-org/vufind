<?php

/**
 * AJAX handler to change the visibility of a notification.
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
 * AJAX handler to change the visibility of a notification.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class NotificationsVisibility extends AbstractBase
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
        $page_id = $params->fromPost('page-id', $params->fromQuery('page-id', null));
        $broadcast_id = $params->fromPost('broadcast-id', $params->fromQuery('broadcast-id', null));
        $visibility = $params->fromPost('visibility', $params->fromQuery('visibility', null));
        $visibility_global = $params->fromPost('visibility-global', $params->fromQuery('visibility-global', null));

        if ($visibility !== null) {
            if ($page_id) {
                $this->pagesTable->setVisibilityForPageId($visibility, $page_id);
            }
            if ($broadcast_id) {
                $this->broadcastsTable->setVisibilityForBroadcastId($visibility, $broadcast_id);
            }
            return $this->formatResponse(['visibility' => $visibility ? 1 : 0]);
        } elseif ($visibility_global !== null) {
            if ($page_id) {
                $this->pagesTable->setVisibilityGlobalForPageId($visibility_global, $page_id);
            }
            if ($broadcast_id) {
                $this->broadcastsTable->setVisibilityGlobalForBroadcastId($visibility_global, $broadcast_id);
            }
            return $this->formatResponse(['visibility_global' => $visibility_global ? 1 : 0]);
        }
    }
}
