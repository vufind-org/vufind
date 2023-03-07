<?php
/**
 * FeedbackService Test Class
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
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Db\Service;

use VuFind\Db\Entity\Feedback;
use VuFind\Db\Service\FeedbackService;

/**
 * FeedbackService Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class FeedbackServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test creating a feedback entity.
     *
     * @return void
     */
    public function testCreateEntity(): void
    {
        $feedbackService = $this->getConfiguredFeedbackService()['feedbackService'];

        $this->assertInstanceOf(Feedback::class, $feedbackService->createEntity());
    }

    /**
     * Test getting column values.
     *
     * @return void
     */
    public function testGetColumn(): void
    {
        $mocks = $this->getConfiguredFeedbackService();
        $entityManager = $mocks['entityManager'];
        $feedbackService = $mocks['feedbackService'];
        $queryStmt = "SELECT f.id, f.status FROM VuFind\Db\Entity\Feedback f "
            . "ORDER BY f.status";
        $query = $this->getMockBuilder(\Doctrine\ORM\AbstractQuery::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult'])
            ->getMockForAbstractClass();
        $entityManager->expects($this->once())->method('createQuery')
            ->with($this->equalTo($queryStmt))
            ->willReturn($query);
        $query->expects($this->once())->method('getResult')
            ->willReturn([]);
        $feedbackService->getColumn('status');
    }

    /**
     * Test delete based on id.
     *
     * @return void
     */
    public function testDeleteByIdArray(): void
    {
        $mocks = $this->getConfiguredFeedbackService();
        $entityManager = $mocks['entityManager'];
        $feedbackService = $mocks['feedbackService'];
        $queryStmt = "DELETE FROM VuFind\Db\Entity\Feedback fb WHERE fb.id IN (:ids)";

        $query = $this->getMockBuilder(\Doctrine\ORM\AbstractQuery::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute', 'setParameters'])
            ->getMockForAbstractClass();
        $entityManager->expects($this->once())->method('createQuery')
            ->with($this->equalTo($queryStmt))
            ->willReturn($query);
        $query->expects($this->once())->method('execute');
        $query->expects($this->once())->method('setParameters')
            ->with(['ids' => [1,2]])
            ->willReturn($query);
        $feedbackService->deleteByIdArray([1, 2]);
    }

    /**
     * Test Update a column.
     *
     * @return void
     */
    public function testUpdateColumn(): void
    {
        $mocks = $this->getConfiguredFeedbackService();
        $entityManager = $mocks['entityManager'];
        $feedbackService = $mocks['feedbackService'];
        $queryStmt = "UPDATE VuFind\Db\Entity\Feedback f SET f.status "
            . "= :value WHERE f.id = :id";

        $query = $this->getMockBuilder(\Doctrine\ORM\AbstractQuery::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute', 'setParameters'])
            ->getMockForAbstractClass();
        $entityManager->expects($this->once())->method('createQuery')
            ->with($this->equalTo($queryStmt))
            ->willReturn($query);
        $query->expects($this->once())->method('execute');
        $query->expects($this->once())->method('setParameters')
            ->with(['value' => 'closed', 'id' => 1])
            ->willReturn($query);
        $feedbackService->updateColumn('status', 'closed', 1);
    }

    /**
     * Data provider for testGetFeedbackByFilter.
     *
     * @return array
     */
    public function pageProvider(): array
    {
        return ['Test1' => [1],
                'Test2' => [null],
            ];
    }

    /**
     * Test getting feedback based on filters.
     *
     * @param int|null $page Page number
     *
     * @return void
     *
     * @dataProvider pageProvider
     */
    public function testGetFeedbackByFilter($page): void
    {
        $mocks = $this->getConfiguredFeedbackService();
        $entityManager = $mocks['entityManager'];
        $feedbackService = $mocks['feedbackService'];
        $queryStmt = "SELECT f, CONCAT(u.firstname, ' ', u.lastname) AS user_name, "
            . "CONCAT(m.firstname, ' ', m.lastname) AS manager_name FROM "
            . "VuFind\Db\Entity\Feedback f LEFT JOIN f.user u LEFT JOIN f.updatedBy m "
            . "WHERE f.formName = :formName AND f.siteUrl = :siteUrl AND "
            . "f.status = :status ORDER BY f.created DESC";

        $query = $this->getMockBuilder(\Doctrine\ORM\AbstractQuery::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setParameters'])
            ->addMethods(['setMaxResults', 'setFirstResult'])
            ->getMockForAbstractClass();
        $entityManager->expects($this->once())->method('createQuery')
            ->with($this->equalTo($queryStmt))
            ->willReturn($query);

        $query->expects($this->once())->method('setParameters')
            ->with(
                ['formName' => 'foo',
                    'siteUrl' => 'bar',
                    'status' => 'closed']
            )
            ->willReturn($query);
        if ($page) {
            $query->expects($this->once())->method('setFirstResult')
                ->with($this->equalTo(0));
            $query->expects($this->once())->method('setMaxResults')
                ->with($this->equalTo(20));
        }

        $feedbackService->getFeedbackByFilter('foo', 'bar', 'closed', $page);
    }

    /**
     * Get a configured FeedbackService object.
     *
     * @return array
     */
    protected function getConfiguredFeedbackService()
    {
        $entityManager = $this->getMockBuilder(\Doctrine\ORM\EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entityPluginManager = $this->getMockBuilder(\VuFind\Db\Entity\PluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entityPluginManager->expects($this->once())->method('get')
            ->with($this->equalTo(Feedback::class))
            ->willReturn(new Feedback());
        $feedbackService = new FeedbackService($entityManager, $entityPluginManager);
        return compact('entityManager', 'entityPluginManager', 'feedbackService');
    }
}
