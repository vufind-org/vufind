<?php

/**
 * LoadClassMetadata event listener test class
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Db;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\LoadClassMetadataListener;

/**
 * LoadClassMetadata event listener test class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class LoadClassMetadataListenerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Simplest test: the listener should not take any action on a base entity class.
     *
     * @return void
     */
    public function testBaseEntityIsUnaffected(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $listener = new LoadClassMetadataListener($entityManager, []);
        $metadata = new ClassMetadata(UserEntityInterface::class);
        $serializedMetadata = serialize($metadata);
        $args = $this->createMock(LoadClassMetadataEventArgs::class);
        $args->expects($this->once())->method('getClassMetadata')->willReturn($metadata);
        $listener->loadClassMetadata($args);
        // Check that the object serializes the same to prove that nothing has been changed:
        $this->assertSame($serializedMetadata, serialize($metadata));
    }
}
