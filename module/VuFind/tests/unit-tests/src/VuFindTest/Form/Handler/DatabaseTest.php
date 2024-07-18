<?php

/**
 * Database Form Handler Test Class
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Form\Handler;

use Laminas\Mvc\Controller\Plugin\Params;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Db\Entity\FeedbackEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\FeedbackServiceInterface;
use VuFind\Form\Form;
use VuFind\Form\Handler\Database;

/**
 * Database Form Handler Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class DatabaseTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get a mock feedback object configured for tests.
     *
     * @param ?UserEntityInterface $user User expected by feedback.
     *
     * @return MockObject&FeedbackEntityInterface
     */
    protected function getMockFeedback(?UserEntityInterface $user): MockObject&FeedbackEntityInterface
    {
        $feedback = $this->createMock(FeedbackEntityInterface::class);
        $feedback->expects($this->once())->method('setUser')->with($user)->willReturn($feedback);
        $feedback->expects($this->once())->method('setMessage')->with('')->willReturn($feedback);
        $feedback->expects($this->once())->method('setFormData')->with([])->willReturn($feedback);
        $feedback->expects($this->once())->method('setFormName')->with('formy-mcformface')->willReturn($feedback);
        $feedback->expects($this->once())->method('setSiteUrl')->with('http://foo')->willReturn($feedback);
        $feedback->expects($this->once())->method('setCreated')->willReturn($feedback);
        $feedback->expects($this->once())->method('setUpdated')->willReturn($feedback);
        return $feedback;
    }

    /**
     * Test success with a user.
     *
     * @return void
     */
    public function testSuccessWithUser(): void
    {
        $user = $this->createMock(UserEntityInterface::class);
        $feedback = $this->getMockFeedback($user);
        $feedbackService = $this->createMock(FeedbackServiceInterface::class);
        $feedbackService->expects($this->once())->method('createEntity')->willReturn($feedback);
        $feedbackService->expects($this->once())->method('persistEntity')->with($feedback);
        $handler = new Database($feedbackService, 'http://foo');
        $form = $this->createMock(Form::class);
        $form->expects($this->once())->method('mapRequestParamsToFieldValues')->willReturn([]);
        $form->expects($this->once())->method('getFormId')->willReturn('formy-mcformface');
        $params = $this->createMock(Params::class);
        $params->expects($this->once())->method('fromPost')->willReturn([]);
        $this->assertTrue($handler->handle($form, $params, $user));
    }

    /**
     * Test success with no user.
     *
     * @return void
     */
    public function testSuccessWithoutUser(): void
    {
        $user = null;
        $feedback = $this->getMockFeedback($user);
        $feedbackService = $this->createMock(FeedbackServiceInterface::class);
        $feedbackService->expects($this->once())->method('createEntity')->willReturn($feedback);
        $feedbackService->expects($this->once())->method('persistEntity')->with($feedback);
        $handler = new Database($feedbackService, 'http://foo');
        $form = $this->createMock(Form::class);
        $form->expects($this->once())->method('mapRequestParamsToFieldValues')->willReturn([]);
        $form->expects($this->once())->method('getFormId')->willReturn('formy-mcformface');
        $params = $this->createMock(Params::class);
        $params->expects($this->once())->method('fromPost')->willReturn([]);
        $this->assertTrue($handler->handle($form, $params, $user));
    }
}
