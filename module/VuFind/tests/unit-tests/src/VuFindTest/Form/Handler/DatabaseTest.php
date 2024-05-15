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
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Table\Feedback;
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
     * Test success with a user.
     *
     * @return void
     */
    public function testSuccessWithUser(): void
    {
        $feedback = $this->createMock(Feedback::class);
        $callback = function ($data) {
            $this->assertEquals(1234, $data['user_id']);
            $this->assertEquals('', $data['message']);
            $this->assertEquals('[]', $data['form_data']);
            $this->assertEquals('formy-mcformface', $data['form_name']);
            $this->assertEquals('http://foo', $data['site_url']);
            $this->assertStringMatchesFormat('%d-%d-%d %d:%d:%d', $data['created']);
            $this->assertStringMatchesFormat('%d-%d-%d %d:%d:%d', $data['updated']);
            return true;
        };
        $feedback->expects($this->once())->method('insert')->with($this->callback($callback))->willReturn(true);
        $handler = new Database($feedback, 'http://foo');
        $form = $this->createMock(Form::class);
        $form->expects($this->once())->method('mapRequestParamsToFieldValues')->willReturn([]);
        $form->expects($this->once())->method('getFormId')->willReturn('formy-mcformface');
        $params = $this->createMock(Params::class);
        $params->expects($this->once())->method('fromPost')->willReturn([]);
        $user = $this->createMock(UserEntityInterface::class);
        $user->expects($this->once())->method('getId')->willReturn(1234);
        $this->assertTrue($handler->handle($form, $params, $user));
    }

    /**
     * Test success with no user.
     *
     * @return void
     */
    public function testSuccessWithoutUser(): void
    {
        $feedback = $this->createMock(Feedback::class);
        $callback = function ($data) {
            $this->assertEquals(null, $data['user_id']);
            $this->assertEquals('', $data['message']);
            $this->assertEquals('[]', $data['form_data']);
            $this->assertEquals('formy-mcformface', $data['form_name']);
            $this->assertEquals('http://foo', $data['site_url']);
            $this->assertStringMatchesFormat('%d-%d-%d %d:%d:%d', $data['created']);
            $this->assertStringMatchesFormat('%d-%d-%d %d:%d:%d', $data['updated']);
            return true;
        };
        $feedback->expects($this->once())->method('insert')->with($this->callback($callback))->willReturn(true);
        $handler = new Database($feedback, 'http://foo');
        $form = $this->createMock(Form::class);
        $form->expects($this->once())->method('mapRequestParamsToFieldValues')->willReturn([]);
        $form->expects($this->once())->method('getFormId')->willReturn('formy-mcformface');
        $params = $this->createMock(Params::class);
        $params->expects($this->once())->method('fromPost')->willReturn([]);
        $this->assertTrue($handler->handle($form, $params, null));
    }
}
