<?php

/**
 * Database service for Comments.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024-2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Finna\Db\Service;

use Closure;
use DateTime;
use Doctrine\ORM\EntityManager;
use Finna\Db\Entity\Comments;
use Finna\Db\Entity\CommentsEntityInterface;
use Finna\Db\Entity\FinnaCommentsEntityInterface;
use Finna\Db\Entity\FinnaCommentsInappropriateEntityInterface;
use Finna\Db\Entity\FinnaCommentsRecordEntityInterface;
use VuFind\Db\Entity\EntityInterface;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\PersistenceManager;
use VuFind\Db\Service\DbServiceAwareTrait;

use function assert;

/**
 * Database service for Comments.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class CommentsService extends \VuFind\Db\Service\CommentsService implements CommentsServiceInterface
{
    use DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param EntityManager       $entityManager        Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager  Database entity plugin manager
     * @param PersistenceManager  $persistenceManager   Entity persistence manager
     * @param Closure             $sessionManagerLoader Session manager loader
     */
    public function __construct(
        EntityManager $entityManager,
        EntityPluginManager $entityPluginManager,
        PersistenceManager $persistenceManager,
        protected Closure $sessionManagerLoader
    ) {
        parent::__construct($entityManager, $entityPluginManager, $persistenceManager);
    }

    /**
     * Persist an entity.
     *
     * @param EntityInterface $entity Entity to persist.
     *
     * @return void
     */
    public function persistEntity(EntityInterface $entity): void
    {
        assert($entity instanceof Comments);
        $entity->setFinnaUpdated(new DateTime());
        parent::persistEntity($entity);
    }

    /**
     * Mark comment as inappropriate
     *
     * @param ?UserEntityInterface $user      User object
     * @param int                  $commentId Comment ID
     * @param string               $reason    Reason
     * @param string               $message   Expand given reason
     * @param string               $sessionId Session ID
     *
     * @return void
     */
    public function markCommentInappropriate(
        ?UserEntityInterface $user,
        int $commentId,
        string $reason,
        string $message,
        string $sessionId
    ): void {
        if (!($comment = $this->getEntityById(CommentsEntityInterface::class, $commentId))) {
            throw new \Exception('Comment not found');
        }
        $entity = $this->createFinnaCommentsInappropriateEntity();
        $entity->setUser($user)
            ->setComment($comment)
            ->setReason($reason)
            ->setMessage($message)
            ->setCreated(new DateTime())
            ->setSessionId($sessionId);
        $this->persistEntity($entity);
    }

    /**
     * Get inappropriate comments for a record reported by the given user.
     *
     * @param ?UserEntityInterface $user     Reporter, or null to use current session
     * @param string               $recordId Record ID
     * @param string               $source   Record source
     *
     * @return CommentsEntityInterface[]
     */
    public function getInappropriateForRecord(?UserEntityInterface $user, string $recordId, string $source): array
    {
        $dql = 'SELECT ci FROM ' . FinnaCommentsInappropriateEntityInterface::class . ' ci'
            . ' JOIN ' . FinnaCommentsRecordEntityInterface::class . ' cr WITH ci.comment_id = cr.comment_id'
            . ' WHERE';
        $params = [];
        if ($user) {
            $dql .= ' ci.user = :user';
            $params[':user'] = $user;
        } else {
            $dql .= ' ci.sessionId = :sessionId';
            $params[':sessionId'] = (($this->sessionManagerLoader)())->getSessionId();
        }
        $subQuery = $this->entityManager->createQuery($dql)
            ->setParameters($params);

        return $this->entityManager
            ->createQuery('SELECT c FROM ' . CommentsEntityInterface::class . ' WHERE c.id IN (:subQuery)')
            ->setParameters(compact('subQuery'))
            ->getResult();
    }

    /**
     * Edit comment.
     *
     * @param UserEntityInterface $user      User object or identifier
     * @param int                 $commentId Comment ID
     * @param string              $comment   Comment
     *
     * @return void
     */
    public function editComment(UserEntityInterface $user, int $commentId, string $comment)
    {
        $commentEntity = $this->entityManager->getRepository(CommentsEntityInterface::class)
            ->findOneBy(['id' => $commentId, 'user' => $user]);
        if (!$commentEntity) {
            throw new \Exception('Comment not found');
        }
        $commentEntity->setComment($comment)
            ->setFinnaUpdated(new DateTime());
        $this->persistEntity($commentEntity);
    }

    /**
     * Change all matching comments to use the new resource ID instead of the old one (called when an ID changes).
     *
     * @param int|CommentsEntityInterface $commentId Comment ID
     * @param array                       $recordIds Record IDs
     *
     * @return void
     */
    public function addRecordLinks(int|CommentsEntityInterface $commentId, array $recordIds): void
    {
        if (!($comment = $this->getEntityById(CommentsEntityInterface::class, $commentId))) {
            throw new \Exception('Comment not found');
        }

        // Flush only once in the end:
        foreach ($recordIds as $recordId) {
            $commentsRecord = $this->createFinnaCommentsRecordEntity();
            $commentsRecord->setComment($comment)
                ->setRecordId($recordId);
            $this->entityManager->persist($commentsRecord);
        }
        $this->entityManager->flush();
    }

    /**
     * Create a FinnaCommentsInappropriate entity
     *
     * @return FinnaCommentsInappropriateEntityInterface
     */
    protected function createFinnaCommentsInappropriateEntity(): FinnaCommentsInappropriateEntityInterface
    {
        return $this->entityPluginManager->get(FinnaCommentsInappropriateEntityInterface::class);
    }

    /**
     * Create a FinnaCommentsRecord entity
     *
     * @return FinnaCommentsRecordEntityInterface
     */
    protected function createFinnaCommentsRecordEntity(): FinnaCommentsRecordEntityInterface
    {
        return $this->entityPluginManager->get(FinnaCommentsRecordEntityInterface::class);
    }

    /**
     * Get a batch of entities.
     *
     * @param ?int $lastId    ID of last retrieved entity, or null to start from beginning
     * @param int  $batchSize Batch size
     *
     * @return FinnaCommentsEntityInterface[]
     */
    public function getEntityBatch(?int $lastId, int $batchSize): array
    {
        $dql = 'SELECT c FROM ' . CommentsEntityInterface::class . ' c';
        $params = [];
        if (null !== $lastId) {
            $dql .= ' WHERE c.id > :lastId';
            $params['lastId'] = $lastId;
        }
        $dql .= ' ORDER BY c.id';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($params);
        $query->setMaxResults($batchSize);
        return $query->getResult();
    }
}
