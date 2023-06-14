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
use VuFind\Exception\LoginRequired as LoginRequiredException;
use VuFind\Log\LoggerAwareTrait;
use VuFind\Record\Loader;

/**
 * Database service for resource.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ResourceService extends AbstractService implements \VuFind\Db\Service\ServiceAwareInterface, LoggerAwareInterface
{
    use \VuFind\Db\Service\ServiceAwareTrait;
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
        $dql = "SELECT r "
            . "FROM " . $this->getEntityClass(Resource::class) . " r "
            . "WHERE r.recordId = :id AND r.source = :source";
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
     * Add a comment to the current resource.
     *
     * @param string                         $comment  The comment to save.
     * @param int|\VuFind\Db\Entity\User     $user     User object or identifier
     * @param int|\VuFind\Db\Entity\Resource $resource Resource object or identifier
     *
     * @throws LoginRequiredException
     * @return int
     */
    public function addComment($comment, $user, $resource)
    {
        if (null === $user) {
            throw new LoginRequiredException(
                "Can't add comments without logging in."
            );
        }
        if (is_int($user)) {
            $userVal = $this->getDbService(\VuFind\Db\Service\UserService::class)
                ->getUserById($user);
        } else {
            $userVal = $user;
        }
        $commentsService = $this->getDbService(
            \VuFind\Db\Service\CommentsService::class
        );
        $resourceVal = is_int($resource) ? $this->getResourceById($resource)
            : $resource;
        $now = new \DateTime();
        $data = $commentsService->createEntity()
            ->setUser($userVal)
            ->setComment($comment)
            ->setCreated($now)
            ->setResource($resourceVal);

        try {
            $commentsService->persistEntity($data);
        } catch (\Exception $e) {
            $this->logError('Could not save comment: ' . $e->getMessage());
            return false;
        }

        return $data->getId();
    }

    /**
     * Lookup and return a resource.
     *
     * @param int $id id value
     *
     * @return Resource
     */
    public function getResourceById($id)
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
            "UTF-8"
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
}
