<?php

/**
 * Class for updating the database when a record ID changes.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Record;

use VuFind\Db\Service\CommentsServiceInterface;
use VuFind\Db\Service\Feature\TransactionInterface;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Db\Service\ResourceTagsServiceInterface;
use VuFind\Db\Service\UserResourceServiceInterface;

/**
 * Class for updating the database when a record ID changes.
 *
 * @category VuFind
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class RecordIdUpdater
{
    /**
     * Constructor
     *
     * @param ResourceServiceInterface&TransactionInterface $resourceService     Resource database service
     * @param CommentsServiceInterface                      $commentsService     Comments database service
     * @param UserResourceServiceInterface                  $userResourceService User/Resource database service
     * @param ResourceTagsServiceInterface                  $resourceTagsService Resource/Tags database service
     */
    public function __construct(
        protected ResourceServiceInterface&TransactionInterface $resourceService,
        protected CommentsServiceInterface $commentsService,
        protected UserResourceServiceInterface $userResourceService,
        protected ResourceTagsServiceInterface $resourceTagsService
    ) {
    }

    /**
     * Update the database to reflect a changed record identifier.
     *
     * @param string $oldId  Original record ID
     * @param string $newId  Revised record ID
     * @param string $source Record source
     *
     * @return void
     */
    public function updateRecordId(string $oldId, string $newId, string $source = DEFAULT_SEARCH_BACKEND): void
    {
        if (
            $oldId !== $newId
            && $resource = $this->resourceService->getResourceByRecordId($oldId, $source)
        ) {
            $needsDeduplication = false;

            // Do this as a transaction to prevent odd behavior:
            $this->resourceService->beginTransaction();
            // Does the new ID already exist?
            if ($newResource = $this->resourceService->getResourceByRecordId($newId, $source)) {
                // Special case: merge new ID and old ID:
                foreach ([$this->commentsService, $this->userResourceService, $this->resourceTagsService] as $service) {
                    $service->changeResourceId($resource->getId(), $newResource->getId());
                }
                $this->resourceService->deleteResource($resource);
                $needsDeduplication = true;
            } else {
                // Default case: just update the record ID:
                $resource->setRecordId($newId);
                $this->resourceService->persistEntity($resource);
            }
            // Done -- commit the transaction:
            $this->resourceService->commitTransaction();

            // Deduplicate rows where necessary (this can be safely done outside of the transaction):
            if ($needsDeduplication) {
                $this->resourceTagsService->deduplicate();
                $this->userResourceService->deduplicate();
            }
        }
    }
}
