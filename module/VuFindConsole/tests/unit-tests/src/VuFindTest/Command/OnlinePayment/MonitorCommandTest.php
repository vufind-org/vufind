<?php

/**
 * OnlinePayment/Monitor command test.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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

namespace VuFindTest\Command\OnlinePayment;

use Laminas\View\Renderer\PhpRenderer;
use Symfony\Component\Console\Tester\CommandTester;
use VuFind\Db\Service\AuditEventService;
use VuFind\Db\Service\PaymentServiceInterface;
use VuFind\Mailer\Mailer;
use VuFind\OnlinePayment\OnlinePaymentManager;
use VuFindConsole\Command\OnlinePayment\MonitorCommand;
use VuFindConsole\Command\ScheduledSearch\NotifyCommand;
use VuFindTest\Container\MockContainer;
use VuFindTest\Feature\ConfigRelatedServicesTrait;

/**
 * OnlinePayment/Monitor command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MonitorCommandTest extends \PHPUnit\Framework\TestCase
{
    use ConfigRelatedServicesTrait;

    /**
     * Container for building mocks.
     *
     * @var MockContainer
     */
    protected $container;

    /**
     * Setup method
     *
     * @return void
     */
    public function setup(): void
    {
        $this->container = new MockContainer($this);
    }

    /**
     * Test behavior when no payments need processing.
     *
     * @return void
     */
    public function testNoNotifications(): void
    {
        $paymentService = $this->container->createMock(PaymentServiceInterface::class);
        $paymentService->expects($this->once())->method('getFailedPayments')->willReturn([]);
        $command = $this->getCommand(
            [
                'paymentService' => $paymentService,
                'onlinePaymentManager' => $this->createMock(OnlinePaymentManager::class),
                'viewRenderer' => $this->createMock(PhpRenderer::class),
                'mailer' => $this->createMock(Mailer::class),
                'config' => [],
                'auditEventService' => $this->createMock(AuditEventService::class),
            ]
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $results = array_filter(explode("\n", $commandTester->getDisplay()));
        $this->assertCount(2, $results);
        $this->assertStringEndsWith('Online payment monitor started', $results[0]);
        $this->assertStringEndsWith('Online payment monitor completed', $results[1]);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Get a notify command for testing.
     *
     * @param array $options Options to override
     *
     * @return NotifyCommand
     */
    protected function getCommand(array $options = []): MonitorCommand
    {
        return new MonitorCommand(
            $options['paymentService'],
            $this->createMock(OnlinePaymentManager::class),
            $this->createMock(PhpRenderer::class),
            $this->createMock(Mailer::class),
            [
                'Mail' => [
                    'default_from' => 'noreply@vufind.org',
                ],
            ],
            $this->createMock(AuditEventService::class),
        );
    }
}
