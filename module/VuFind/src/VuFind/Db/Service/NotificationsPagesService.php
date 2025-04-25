<?php

/**
 * Database service for notifications pages.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Exception;
use VuFind\Db\Entity\NotificationsPagesInterface;
use VuFind\Db\Table\NotificationsPages;


/**
 * Database service for notifications pages.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class NotificationsPagesService extends AbstractDbService implements NotificationsPagesServiceInterface
{
    /**
     * Constructor
     *
     * @param NotificationsPages $pages Notifications pages table object
     */
    public function __construct(protected NotificationsPages $pages)
    {
    }

    /**
     * Insert a new page into the database or update an existing one.
     *
     * @param array $data     Data to be written to the database
     * @param array $pageData Data of an existing page
     * @param int   $page_id  Id of the page to be edited
     *
     * @return void
     *
     * @throws Exception
     */
    public function insertOrUpdatePage($data, $pageData = null, $page_id = null): void
    {
        $this->pages->insertOrUpdatePage($data, $pageData, $page_id);
    }

    /**
     * Get a list of pages from the database
     *
     * @param array $where Filter setting for the request
     * @param array $order Order settings for the request
     *
     * @return array
     */
    public function getPagesList($where = null, $order = null): array
    {
        return $this->pages->getPagesList($where, $order);
    }

    /**
     * Get all data for a page
     *
     * @param array $page_id Id of the page
     *
     * @return array
     */
    public function getPagesDataByPageId($page_id): array
    {
        return $this->pages->getPagesDataByPageId($page_id);
    }

    /**
     * Get a page object by id
     *
     * @param int $id Id of the page
     *
     * @return ?NotificationsPagesInterface page object
     */
    public function getPageById($id): ?NotificationsPagesInterface
    {
        return $this->pages->getPageById($id);
    }

    /**
     * Get all page objects with the same page_id
     *
     * @param int $page_id Id of the page
     *
     * @return array Array of page objects
     */
    public function getPagesByPageId($page_id): array
    {
        return $this->pages->getPagesByPageId($page_id);
    }

    /**
     * Get a page object by page_id and language
     *
     * @param int    $page_id  Id of the page
     * @param string $language Language of the page
     *
     * @return ?NotificationsPagesInterface page object
     */
    public function getPageByPageIdAndLanguage($page_id, $language): ?NotificationsPagesInterface
    {
        return $this->pages->getPageByPageIdAndLanguage($page_id, $language);
    }

    /**
     * Set the priority of a page
     *
     * @param int $index   New position of the page
     * @param int $page_id Id of the page
     *
     * @return void
     */
    public function setPriorityForPageId($index, $page_id): void
    {
        $this->pages->setPriorityForPageId($index, $page_id);
    }

    /**
     * Set the visibility of a page
     *
     * @param int $visibility New visibility of the page
     * @param int $page_id    Id of the page
     *
     * @return void
     */
    public function setVisibilityForPageId($visibility, $page_id): void
    {
        $this->pages->setVisibilityForPageId($visibility, $page_id);
    }

    /**
     * Set the global visibility of a page
     *
     * @param int $visibility_global New visibility of the page
     * @param int $page_id           Id of the page
     *
     * @return void
     */
    public function setVisibilityGlobalForPageId($visibility_global, $page_id): void
    {
        $this->pages->setVisibilityGlobalForPageId($visibility_global, $page_id);
    }
}
