<?php

/**
 * Test class to validate the Doctrine schema.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Db\Service;

use Doctrine\ORM\Tools\SchemaValidator;

/**
 * Test class to validate the Doctrine schema.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
final class DoctrineSchemaValidationTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\LiveDetectionTrait;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            $this->markTestSkipped('Continuous integration not running.');
            return;
        }
    }

    /**
     * Test schema validation.
     *
     * @return void
     */
    public function testSchemaValidation(): void
    {
        $container = $this->getLiveDatabaseContainer();
        // Flush the Doctrine cache to be sure we're validating the latest data:
        $cache = $container->get('doctrine.cache.filesystem');
        $cache->flushAll();
        $entityManager = $container->get('doctrine.entitymanager.orm_vufind');
        $platform = $entityManager->getConnection()->getDatabasePlatform()->getName();
        $validator = new SchemaValidator($entityManager);
        $schemaList = $validator->getUpdateSchemaList();
        if ($platform === 'postgresql') {
            $schemaList = $this->filterIndexRecreation($schemaList);
        }
        $this->assertEquals([], $validator->validateMapping(), 'Unexpected validation error');
        $this->assertEquals([], $schemaList, 'Unexpected schema updates pending');
    }

    /**
     * Filter out warnings related to cross-platform index recreation differences.
     *
     * MySQL supports column length limits in indexes (e.g., KEY email (email(190))),
     * but PostgreSQL doesn't support this syntax natively. This causes Doctrine's
     * schema validator to detect differences where MySQL indexes have length
     * specifications but PostgreSQL indexes don't, even though both achieve the
     * same functional result.
     *
     * The validator incorrectly reports these as schema mismatches, suggesting
     * to DROP and recreate indexes that are functionally identical. This filter
     * removes those false positives by excluding any index that appears in both
     * DROP and CREATE operations within the same schema diff.
     *
     * @param array $schemaDiff Array of SQL statements from Doctrine schema comparison
     *
     * @return array Filtered array with cross-platform index differences removed
     */
    public function filterIndexRecreation(array $schemaDiff): array
    {
        $droppedIndexes = [];
        $createdIndexes = [];

        // Collect all dropped and created index names
        foreach ($schemaDiff as $statement) {
            if (preg_match('/DROP INDEX (\w+)/', $statement, $matches)) {
                $droppedIndexes[] = $matches[1];
            } elseif (preg_match('/CREATE (?:UNIQUE )?INDEX (\w+)/', $statement, $matches)) {
                $createdIndexes[] = $matches[1];
            }
        }

        // Find indexes that are both dropped and created (recreated)
        $recreatedIndexes = array_intersect($droppedIndexes, $createdIndexes);

        // Filter out statements for recreated indexes
        return array_filter($schemaDiff, function ($statement) use ($recreatedIndexes) {
            foreach ($recreatedIndexes as $indexName) {
                if (str_contains($statement, $indexName)) {
                    return false;
                }
            }
            return true;
        });
    }
}
