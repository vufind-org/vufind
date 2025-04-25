<?php

/**
 * Database service interface for notification pages.
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

/**
 * Database service interface for notification pages.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Johannes Schultze <schultze@effective-webwork.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface NotificationsPagesServiceInterface extends DbServiceInterface
{
    /**
     * Insert a new page into the database or update an existing one.
     *
     * @param array $data Data to be written to the database
     * @param array|null $pageData Data of an existing page
     * @param int|null $page_id Id of the page to be edited
     *
     * @return void
     *
     * @throws Exception
     */
    public function insertOrUpdatePage(array $data, array $pageData = null, int $page_id = null);

    /**
     * Get a list of pages from the database
     *
     * @param array|null $where Filter setting for the request
     * @param array|null $order Order settings for the request
     *
     * @return array
     */
    public function getPagesList(array $where = null, array $order = null);

    /**
     * Get all data for a page
     *
     * @param array $page_id Id of the page
     *
     * @return array
     */
    public function getPagesDataByPageId(array $page_id): array;

    /**
     * Get a page object by id
     * This returns a specific page objects
     *
     * @param int $id Id of the page
     *
     * @return ?NotificationsPagesInterface page object
     */
    public function getPageById(int $id): ?NotificationsPagesInterface;

    /**
     * Get all page objects with the same page_id
     * This returns all the objects in all languages for one page
     *
     * @param int $page_id Page_id of the page
     *
     * @return array Array of page objects
     */
    public function getPagesByPageId(int $page_id): array;

    /**
     * Get a page object by page_id and language
     *
     * @param int    $page_id  Id of the page
     * @param string $language Language of the page
     *
     * @return ?NotificationsPagesInterface page object
     */
    public function getPageByPageIdAndLanguage(int $page_id, string $language): ?NotificationsPagesInterface;

    /**
     * Set the priority of a page
     *
     * @param int $index   New position of the page
     * @param int $page_id Id of the page
     *
     * @return void
     */
    public function setPriorityForPageId(int $index, int $page_id);

    /**
     * Set the visibility of a page
     *
     * @param int $visibility New visibility of the page
     * @param int $page_id    Id of the page
     *
     * @return void
     */
    public function setVisibilityForPageId(int $visibility, int $page_id);

    /**
     * Set the global visibility of a page
     *
     * @param int $visibility_global New visibility of the page
     * @param int $page_id           Id of the page
     *
     * @return void
     */
    public function setVisibilityGlobalForPageId(int $visibility_global, int $page_id);
}