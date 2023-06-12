<?php
/**
 * Database service for resource.
 *
 * PHP version 7
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

use Laminas\Log\LoggerAwareInterface;
use VuFind\Db\Entity\Resource;
use VuFind\Exception\LoginRequired as LoginRequiredException;
use VuFind\Log\LoggerAwareTrait;

/**
 * Database service for resource.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ResourceService extends AbstractService
implements \VuFind\Db\Service\ServiceAwareInterface, LoggerAwareInterface
{
    use \VuFind\Db\Service\ServiceAwareTrait;
    use LoggerAwareTrait;

    /**
     * Look up a row for the specified resource.
     *
     * @param string $id     Record ID to look up
     * @param string $source Source of record to look up
     *
     * @return Resource|null Matching row if found, null
     * otherwise.
     *
     * Note that this method is partially migrated.
     * Creation of a database record if does not yet exist has to be migrated.
     */
    public function findResource(
        $id,
        $source = DEFAULT_SEARCH_BACKEND
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
}
