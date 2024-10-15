<?php

/**
 * VuFind Action Helper - Favorites Support Methods
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
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Plugin;

use VuFind\Favorites\FavoritesService;

/**
 * Action helper to perform favorites-related actions
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 *
 * @deprecated Use \VuFind\Favorites\FavoritesService
 */
class Favorites extends \Laminas\Mvc\Controller\Plugin\AbstractPlugin
{
    /**
     * Constructor
     *
     * @param FavoritesService $favoritesService Favorites service
     */
    public function __construct(
        protected FavoritesService $favoritesService,
    ) {
    }

    /**
     * Save a group of records to the user's favorites.
     *
     * @param array               $params Array with some or all of these keys:
     *  <ul>
     *    <li>ids - Array of IDs in source|id format</li>
     *    <li>mytags - Unparsed tag string to associate with record (optional)</li>
     *    <li>list - ID of list to save record into (omit to create new list)</li>
     *  </ul>
     * @param UserEntityInterface $user   The user saving the record
     *
     * @return array list information
     *
     * @deprecated Use \VuFind\Favorites\FavoritesService::saveRecordsToFavorites()
     */
    public function saveBulk($params, $user)
    {
        return $this->favoritesService->saveRecordsToFavorites($params, $user);
    }

    /**
     * Delete a group of favorites.
     *
     * @param array               $ids    Array of IDs in source|id format.
     * @param mixed               $listID ID of list to delete from (null for all lists)
     * @param UserEntityInterface $user   Logged in user
     *
     * @return void
     *
     * @deprecated Use \VuFind\Favorites\FavoritesService::deleteFavorites()
     */
    public function delete($ids, $listID, $user)
    {
        $this->favoritesService->deleteFavorites($ids, $listID, $user);
    }
}
