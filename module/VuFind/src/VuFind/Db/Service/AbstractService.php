<?php
/**
 * Database service abstract base class
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
namespace VuFind\Db\Service;

use Doctrine\ORM\EntityManager;
use VuFind\Db\Entity\EntityInterface;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;

/**
 * Database service abstract base class
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
abstract class AbstractService
{
    /**
     * Doctrine ORM entity manager
     *
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * VuFind entity plugin manager
     *
     * @var EntityPluginManager
     */
    protected $entityPluginManager;

    /**
     * Constructor
     *
     * @param EntityManager       $entityManager       Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager VuFind entity plugin manager
     */
    public function __construct(
        EntityManager $entityManager,
        EntityPluginManager $entityPluginManager
    ) {
        $this->entityManager = $entityManager;
        $this->entityPluginManager = $entityPluginManager;
    }

    /**
     * Resolve an entity class name using the plugin manager.
     *
     * @param string $entity Entity class name or alias
     *
     * @return string
     */
    protected function getEntityClass(string $entity): string
    {
        $entity = $this->entityPluginManager->get($entity);
        return get_class($entity);
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
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }
}
