<?php
/**
 * VuFind Action Helper - Favorites Support Methods
 *
 * PHP version 5
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
use VuFind\Exception\LoginRequired as LoginRequiredException,
    Zend\Mvc\Controller\Plugin\AbstractPlugin,
    VuFind\Db\Row\User, VuFind\Record\Cache;

/**
 * Zend action helper to perform favorites-related actions
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Favorites extends AbstractPlugin
{
    /**
     * Support method for saveBulk() -- get list to save records into. Either
     * retrieves existing list or creates a new one.
     *
     * @param mixed $listId List ID to load (or empty/'NEW' to create new list)
     * @param User  $user   User object.
     *
     * @return \VuFind\Db\Row\UserList
     */
    protected function getList($listId, User $user)
    {
        $table = $this->getController()->getTable('UserList');
        if (empty($listId) || $listId == 'NEW') {
            $list = $table->getNew($user);
            $list->title = $this->getController()->translate('My Favorites');
            $list->save($user);
        } else {
            $list = $table->getExisting($listId);
            $list->rememberLastUsed(); // handled by save() in other case
        }
        return $list;
    }

    /**
     * Support method for saveBulk() -- save a batch of records to the cache.
     *
     * @param Cache $recordCache    Cache service
     * @param array $cacheRecordIds Array of IDs in source|id format
     *
     * @return void
     */
    protected function cacheBatch(Cache $recordCache, array $cacheRecordIds)
    {
        if ($cacheRecordIds) {
            $recordLoader = $this->getController()->getServiceLocator()
                ->get('VuFind\RecordLoader');
            // Disable the cache so that we fetch latest versions, not cached ones:
            $recordLoader->setCacheContext(Cache::CONTEXT_DISABLED);
            $records = $recordLoader->loadBatch($cacheRecordIds);
            // Re-enable the cache so that we actually save the records:
            $recordLoader->setCacheContext(Cache::CONTEXT_FAVORITE);
            foreach ($records as $record) {
                $recordCache->createOrUpdate(
                    $record->getUniqueID(), $record->getSourceIdentifier(),
                    $record->getRawData()
                );
            }
        }
    }

    /**
     * Save a group of records to the user's favorites.
     *
     * @param array $params Array with some or all of these keys:
     *  <ul>
     *    <li>ids - Array of IDs in source|id format</li>
     *    <li>mytags - Unparsed tag string to associate with record (optional)</li>
     *    <li>list - ID of list to save record into (omit to create new list)</li>
     *  </ul>
     * @param User  $user   The user saving the record
     *
     * @return array list information
     */
    public function saveBulk($params, $user)
    {
        // Validate incoming parameters:
        if (!$user) {
            throw new LoginRequiredException('You must be logged in first');
        }

        // Load helper objects needed for the saving process:
        $list = $this->getList(isset($params['list']) ? $params['list'] : '', $user);
        $tagParser = $this->getController()->getServiceLocator()->get('VuFind\Tags');
        $recordCache = $this->getController()->getServiceLocator()
            ->get('VuFind\RecordCache');
        $recordCache->setContext(Cache::CONTEXT_FAVORITE);

        $cacheRecordIds = [];   // list of record IDs to save to cache
        foreach ($params['ids'] as $current) {
            // Break apart components of ID:
            list($source, $id) = explode('|', $current, 2);

            // Get or create a resource object as needed:
            $resourceTable = $this->getController()->getTable('Resource');
            $resource = $resourceTable->findResource($id, $source);

            // Add the information to the user's account:
            $tags = isset($params['mytags'])
                ? $tagParser->parse($params['mytags']) : [];
            $user->saveResource($resource, $list, $tags, '', false);

            // Collect record IDs for caching
            if ($recordCache->isCachable($resource->source)) {
                $cacheRecordIds[] = $current;
            }
        }

        $this->cacheBatch($recordCache, $cacheRecordIds);
        return ['listId' => $list->id];
    }

    /**
     * Delete a group of favorites.
     *
     * @param array $ids    Array of IDs in source|id format.
     * @param mixed $listID ID of list to delete from (null for all
     * lists)
     * @param User  $user   Logged in user
     *
     * @return void
     */
    public function delete($ids, $listID, $user)
    {
        // Sort $ids into useful array:
        $sorted = [];
        foreach ($ids as $current) {
            list($source, $id) = explode('|', $current, 2);
            if (!isset($sorted[$source])) {
                $sorted[$source] = [];
            }
            $sorted[$source][] = $id;
        }

        // Delete favorites one source at a time, using a different object depending
        // on whether we are working with a list or user favorites.
        if (empty($listID)) {
            foreach ($sorted as $source => $ids) {
                $user->removeResourcesById($ids, $source);
            }
        } else {
            $table = $this->getController()->getTable('UserList');
            $list = $table->getExisting($listID);
            foreach ($sorted as $source => $ids) {
                $list->removeResourcesById($user, $ids, $source);
            }
        }
    }
}
