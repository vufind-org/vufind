<?php

/**
 * Database service for resource.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Doctrine\ORM\EntityManager;
use Laminas\Log\LoggerAwareInterface;
use VuFind\Date\Converter as DateConverter;
use VuFind\Date\DateException;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Entity\Resource;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\User;
use VuFind\Db\Entity\UserResource;
use VuFind\Log\LoggerAwareTrait;
use VuFind\Record\Loader;

use function in_array;
use function intval;
use function strlen;

/**
 * Database service for resource.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ResourceService extends AbstractDbService implements
    ResourceServiceInterface,
    DbServiceAwareInterface,
    LoggerAwareInterface
{
    use DbServiceAwareTrait;
    use LoggerAwareTrait;

    /**
     * Record loader
     *
     * @var Loader
     */
    protected $recordLoader;

    /**
     * Date converter
     *
     * @var DateConverter
     */
    protected $dateConverter;

    /**
     * Constructor
     *
     * @param EntityManager       $entityManager       Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager VuFind entity plugin manager
     * @param Loader              $loader              Record loader
     * @param DateConverter       $converter           Date converter
     */
    public function __construct(
        EntityManager $entityManager,
        EntityPluginManager $entityPluginManager,
        Loader $loader,
        DateConverter $converter
    ) {
        parent::__construct($entityManager, $entityPluginManager);
        $this->recordLoader = $loader;
        $this->dateConverter = $converter;
    }

    /**
     * Look up a row for the specified resource.
     *
     * @param string                            $id     Record ID to look up
     * @param string                            $source Source of record to look up
     * @param bool                              $create If true, create the row if
     * it does not yet exist.
     * @param \VuFind\RecordDriver\AbstractBase $driver A record driver for the
     * resource being created (optional -- improves efficiency if provided, but will
     * be auto-loaded as needed if left null).
     *
     * @return Resource|null Matching row if found or created, null
     * otherwise.
     */
    public function findResource(
        $id,
        $source = DEFAULT_SEARCH_BACKEND,
        $create = true,
        $driver = null
    ) {
        if (empty($id)) {
            throw new \Exception('Resource ID cannot be empty');
        }
        $dql = 'SELECT r '
            . 'FROM ' . $this->getEntityClass(Resource::class) . ' r '
            . 'WHERE r.recordId = :id AND r.source = :source';
        $parameters['id'] = $id;
        $parameters['source'] = $source;
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $result = $query->getResult();

        if (empty($result) && $create) {
            $resource = $this->createEntity()
                ->setRecordId($id)
                ->setSource($source);

            // Load record if it was not provided:
            $driver ??= $this->recordLoader->load($id, $source);
            // Load metadata into the database for sorting/failback purposes:
            $this->assignMetadata($driver, $this->dateConverter, $resource);
            try {
                $this->persistEntity($resource);
            } catch (\Exception $e) {
                $this->logError('Could not save resource: ' . $e->getMessage());
                return false;
            }
            return $resource;
        }
        return current($result);
    }

    /**
     * Lookup and return a resource.
     *
     * @param int $id id value
     *
     * @return ?ResourceEntityInterface
     */
    public function getResourceById($id): ?ResourceEntityInterface
    {
        $resource = $this->entityManager->find(
            $this->getEntityClass(\VuFind\Db\Entity\Resource::class),
            $id
        );
        return $resource;
    }

    /**
     * Use a record driver to assign metadata to the current row.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver    The record driver
     * @param \VuFind\Date\Converter            $converter Date converter
     * @param \VuFind\Db\Entity\Resource        $resource  Resource entity
     *
     * @return \VuFind\Db\Entity\Resource
     */
    public function assignMetadata(
        $driver,
        \VuFind\Date\Converter $converter,
        $resource
    ) {
        // Grab title -- we have to have something in this field!
        $sortTitle = $driver->tryMethod('getSortTitle');
        $title = mb_substr(
            !empty($sortTitle) ? $sortTitle : $driver->getBreadcrumb(),
            0,
            255,
            'UTF-8'
        );
        $resource->setTitle($title);
        // Try to find an author; if not available, just leave the default null:
        $author = mb_substr(
            $driver->tryMethod('getPrimaryAuthor') ?? '',
            0,
            255,
            'UTF-8'
        );
        if (!empty($author)) {
            $resource->setAuthor($author);
        }

        // Try to find a year; if not available, just leave the default null:
        $dates = $driver->tryMethod('getPublicationDates');
        if (strlen($dates[0] ?? '') > 4) {
            try {
                $year = $converter->convertFromDisplayDate('Y', $dates[0]);
            } catch (DateException $e) {
                // If conversion fails, don't store a date:
                $year = '';
            }
        } else {
            $year = $dates[0] ?? '';
        }
        if (!empty($year)) {
            $resource->setYear(intval($year));
        }

        if ($extra = $driver->tryMethod('getExtraResourceMetadata')) {
            $resource->setExtraMetadata(json_encode($extra));
        }
        return $resource;
    }

    /**
     * Create a resource entity object.
     *
     * @return Resource
     */
    public function createEntity(): Resource
    {
        $class = $this->getEntityClass(Resource::class);
        return new $class();
    }

    /**
     * Get a set of records that do not have metadata stored in the resource
     * table.
     *
     * @return array|null
     */
    public function findMissingMetadata()
    {
        $dql = 'SELECT r '
            . 'FROM ' . $this->getEntityClass(Resource::class) . ' r '
            . "WHERE r.title = '' OR r.author IS NULL OR r.year IS NULL";

        $query = $this->entityManager->createQuery($dql);
        $result = $query->getResult();
        return $result;
    }

    /**
     * Update resource IDs for a specific class of entity (useful when merging
     * duplicate resources).
     *
     * @param string       $entity      Entity class
     * @param int|Resource $newResource New resourceid.
     * @param int|User     $oldResource Old resourceid.
     *
     * @return void
     */
    public function updateResource($entity, $newResource, $oldResource)
    {
        $dql = 'UPDATE ' . $this->getEntityClass($entity) . ' e '
            . 'SET e.resource = :newResource WHERE e.resource = :oldResource';
        $parameters = compact('newResource', 'oldResource');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }

    /**
     * Apply a sort parameter to a query on the resource table. Returns an
     * array with two keys: 'orderByClause' (the actual ORDER BY) and
     * 'extraSelect' (extra values to add to SELECT, if necessary)
     *
     * @param string $sort  Field to use for sorting (may include
     *                      'desc' qualifier)
     * @param string $alias Alias to the resource table (defaults to 'r')
     *
     * @return array
     */
    public static function getOrderByClause(string $sort, string $alias = 'r'): array
    {
        // Apply sorting, if necessary:
        $legalSorts = [
            'title', 'title desc', 'author', 'author desc', 'year', 'year desc',
        ];
        $orderByClause = $extraSelect = '';
        if (!empty($sort) && in_array(strtolower($sort), $legalSorts)) {
            // Strip off 'desc' to obtain the raw field name -- we'll need it
            // to sort null values to the bottom:
            $parts = explode(' ', $sort);
            $rawField = trim($parts[0]);

            // Start building the list of sort fields:
            $order = [];

            // The title field can't be null, so don't bother with the extra
            // isnull() sort in that case.
            if (strtolower($rawField) != 'title') {
                $extraSelect = 'CASE WHEN ' . $alias . '.' . $rawField . ' IS NULL THEN 1 ELSE 0 END';
                $order[] = $extraSelect;
            }

            // Apply the user-specified sort:
            $order[] = $alias . '.' . $sort;
            // Inject the sort preferences into the query object:
            $orderByClause = ' ORDER BY ' . implode(', ', $order);
        }
        return compact('orderByClause', 'extraSelect');
    }

    /**
     * Look up a rowset for a set of specified resources.
     *
     * @param array  $ids    Array of IDs
     * @param string $source Source of records to look up
     *
     * @return array
     */
    public function findResources($ids, $source = DEFAULT_SEARCH_BACKEND)
    {
        $repo = $this->entityManager->getRepository($this->getEntityClass(Resource::class));
        $criteria = [
            'recordId' => $ids,
            'source' => $source,
        ];
        return $repo->findBy($criteria);
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
    public function updateRecordId($oldId, $newId, $source = DEFAULT_SEARCH_BACKEND)
    {
        if (
            $oldId !== $newId
            && $resource = $this->findResource($oldId, $source, false)
        ) {
            // Do this as a transaction to prevent odd behavior:
            $this->entityManager->getConnection()->beginTransaction();
            // Does the new ID already exist?
            $deduplicate = false;
            if ($newResource = $this->findResource($newId, $source, false)) {
                // Special case: merge new ID and old ID:
                $entitiesToUpdate = [
                    \VuFind\Db\Entity\Comments::class,
                    \VuFind\Db\Entity\UserResource::class,
                    \VuFind\Db\Entity\ResourceTags::class,
                ];
                foreach ($entitiesToUpdate as $entityToUpdate) {
                    $this->updateResource($entityToUpdate, $newResource, $resource);
                }
                $this->entityManager->remove($resource);
                $deduplicate = true;
            } else {
                // Default case: just update the record ID:
                $resource->setRecordId($newId);
            }
            // Done -- commit the transaction:
            try {
                $this->entityManager->flush();
                $this->entityManager->getConnection()->commit();
            } catch (\Exception $e) {
                $this->logError('Could not update the record: ' . $e->getMessage());
                $this->entityManager->getConnection()->rollBack();
                throw $e;
            }
            // Deduplicate rows where necessary (this can be safely done outside of the transaction):
            if ($deduplicate) {
                $tagService = $this->getDbService(TagService::class);
                $tagService->deduplicateResourceLinks();
                $userResourceService = $this->getDbService(UserResourceService::class);
                $userResourceService->deduplicate();
            }
        }
    }

    /**
     * Get a set of records from the requested favorite list.
     *
     * @param int|User     $user   ID of user owning favorite list
     * @param int|UserList $list   ID of list to retrieve (null for all favorites)
     * @param array        $tags   Tags to use for limiting results
     * @param string       $sort   Resource table field to use for sorting (null for
     *                             no particular sort).
     * @param int          $offset Offset for results
     * @param int          $limit  Limit for results (null for none)
     *
     * @return mixed
     */
    public function getFavorites(
        $user,
        $list = null,
        $tags = [],
        $sort = null,
        $offset = 0,
        $limit = null
    ) {
        $orderByDetails = empty($sort) ? [] : ResourceService::getOrderByClause($sort);
        $dql = 'SELECT DISTINCT(r.id), r';
        if (!empty($orderByDetails['extraSelect'])) {
            $dql .= ', ' . $orderByDetails['extraSelect'];
        }
        $dql .= ' FROM ' . $this->getEntityClass(Resource::class) . ' r '
            . 'JOIN ' . $this->getEntityClass(UserResource::class) . ' ur WITH r.id = ur.resource ';
        $dqlWhere = [];
        $dqlWhere[] = 'ur.user = :user';
        $parameters = compact('user');
        if (null !== $list) {
            $dqlWhere[] = 'ur.list = :list';
            $parameters['list'] = $list;
        }

        // Adjust for tags if necessary:
        if (!empty($tags)) {
            $linkingTable = $this->getDbService(TagService::class);
            $matches = [];
            foreach ($tags as $tag) {
                $matches[] = $linkingTable
                    ->getResourceIDsForTag($tag, $user, $list);
            }
            $dqlWhere[] = 'r.id IN (:ids)';
            $parameters['ids'] = $matches;
        }
        $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        if (!empty($orderByDetails['orderByClause'])) {
            $dql .= $orderByDetails['orderByClause'];
        }

        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);

        if ($offset > 0) {
            $query->setFirstResult($offset);
        }
        if (null !== $limit) {
            $query->setMaxResults($limit);
        }

        $result = $query->getResult();
        return $result;
    }

    /**
     * Delete a resource by source and id
     *
     * @param string $id     Resource ID
     * @param string $source Resource source
     *
     * @return mixed
     */
    public function deleteResource($id, $source)
    {
        $dql = 'DELETE FROM ' . $this->getEntityClass(Resource::class) . ' r '
            . 'WHERE r.recordId = :id AND r.source = :source';
        $parameters = compact('id', 'source');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $result = $query->execute();
        return $result;
    }
}
