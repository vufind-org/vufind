<?php

/**
 * Service for modifying User Lists
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
 * Favorites service
 *
 * @category VuFind
 * @package  Favorites
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Favorites;

use Finna\Db\Entity\UserResourceEntityInterface;
use Finna\Db\Service\UserListService;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Record\Cache as RecordCache;

use function assert;

/**
 *  Favorites service
 *
 * @category VuFind
 * @package  Favorites
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FavoritesService extends \VuFind\Favorites\FavoritesService
{
    /**
     * Save a group of records to the user's favorites.
     *
     * Finna: Handle custom order
     *
     * @param array               $params  Array with some or all of these keys:
     *                                     <ul> <li>ids - Array of IDs in
     *                                     source|id format</li> <li>mytags -
     *                                     Unparsed tag string to associate with
     *                                     record (optional)</li> <li>list - ID
     *                                     of list to save record into (omit to
     *                                     create new list)</li> </ul>
     * @param UserEntityInterface $user    The user saving the record
     * @param array               $records Record drivers
     *
     * @return array list information
     */
    public function saveMany(array $params, UserEntityInterface $user, array $records): array
    {
        // Load helper objects needed for the saving process:
        $list = $this->getAndRememberListObject($this->getListIdFromParams($params), $user);
        $this->recordCache?->setContext(RecordCache::CONTEXT_FAVORITE);

        assert($this->userListService instanceof UserListService);

        // Add custom order keys for new items if the list has custom order:
        $index = $this->userListService->getNextAvailableCustomOrderIndex($list);

        // If target list is not in custom order then reverse the records to get original order:
        if (!$this->userListService->isCustomOrderAvailable($list)) {
            $records = array_reverse($records);
        }
        $tags = isset($params['mytags']) ? $this->tagsService->parse($params['mytags']) : [];

        foreach ($records as $record) {
            // Get or create a resource object as needed:
            $resource = $this->resourcePopulator->getOrCreateResourceForDriver($record);

            // Create the resource link if it doesn't exist:
            $userResource = $this->userResourceService->createOrUpdateLink($resource, $user, $list);
            // Update custom order index:
            if ($index) {
                assert($userResource instanceof UserResourceEntityInterface);
                $userResource->setFinnaCustomOrderIndex($index);
                $this->userResourceService->persistEntity($resource);
                ++$index;
            }

            // Add the new tags:
            foreach ($tags as $tag) {
                $this->tagsService->linkTagToResource($tag, $resource, $user, $list);
            }

            // Cache record:
            if ($this->recordCache?->isCachable($resource->getSource())) {
                $this->recordCache->createOrUpdate(
                    $record->getUniqueID(),
                    $record->getSourceIdentifier(),
                    $record->getRawData()
                );
            }
        }

        return ['listId' => $list->getId()];
    }
}
