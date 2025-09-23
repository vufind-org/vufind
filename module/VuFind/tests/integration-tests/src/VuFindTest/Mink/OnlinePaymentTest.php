<?php

/**
 * Mink online payment actions test class.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

declare(strict_types=1);

namespace VuFindTest\Mink;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\Element;
use VuFind\Db\Entity\AuditEventEntityInterface;
use VuFind\Db\Entity\PaymentEntityInterface;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PaymentFeeServiceInterface;
use VuFind\Db\Service\PaymentServiceInterface;
use VuFind\Db\Type\AuditEventSubtype;
use VuFind\Db\Type\PaymentStatus;
use VuFindTest\Feature\DemoDriverTestTrait;
use VuFindTest\Feature\EmailTrait;
use VuFindTest\Feature\LiveDatabaseTrait;
use VuFindTest\Feature\UserCreationTrait;

use function assert;
use function count;

/**
 * Mink online payment actions test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class OnlinePaymentTest extends \VuFindTest\Integration\MinkTestCase
{
    use DemoDriverTestTrait;
    use EmailTrait;
    use LiveDatabaseTrait;
    use UserCreationTrait;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        static::failIfDataExists();
    }

    /**
     * Data provider for testPaymentDisabled
     *
     * @return array
     */
    public static function paymentDisabledProvider(): array
    {
        return [
            'without multibackend' => [false],
            'with multibackend' => [true],
        ];
    }

    /**
     * Test disabled payment.
     *
     * @param bool $multibackend Use MultiBackend driver?
     *
     * @return void
     *
     * @dataProvider paymentDisabledProvider
     */
    public function testPaymentDisabled(bool $multibackend): void
    {
        $this->changeConfigs($this->getConfigs($multibackend, null));

        $page = $this->goToFines(true, $multibackend);

        $this->unFindCss($page, '.online-payment');
    }

    /**
     * Data provider for testPayment
     *
     * @return array
     */
    public static function paymentProvider(): array
    {
        return [
            'payment with receipt enabled, single ILS' => [
                [],
                true,
                false,
            ],
            'payment with receipt disabled, single ILS' => [
                ['receipt' => false],
                false,
                false,
            ],
            'payment with receipt enabled, MultiBackend' => [
                [],
                true,
                true,
            ],
            'payment with receipt disabled, MultiBackend' => [
                ['receipt' => false],
                false,
                true,
            ],
        ];
    }

    /**
     * Test payment.
     *
     * @param array $paymentSettings Additional online payment settings
     * @param bool  $receiptEnabled  Receipt enabled?
     * @param bool  $multibackend    Use MultiBackend driver?
     *
     * @return void
     *
     * @dataProvider paymentProvider
     * @depends      testPaymentDisabled
     */
    public function testPayment(array $paymentSettings, bool $receiptEnabled, bool $multibackend): void
    {
        $this->changeConfigs($this->getConfigs($multibackend, $paymentSettings));
        $this->resetEmailLog();

        $page = $this->goToFines(false, $multibackend);

        $this->checkForMissingDevTools($page);

        $this->findCss($page, '.online-payment');
        $this->clickCss($page, '.checkbox-select-all');
        $this->assertEquals(
            'Pay Online $15.00',
            $this->findCss($page, '.js-pay-selected')->getValue()
        );
        // Test cancel from dialog:
        $this->clickCss($page, '.js-pay-selected');
        $this->assertLightboxTitle($page, 'Accept terms to continue payment');
        $this->clickCss($page, '#modal .btn.btn-primary');
        $this->assertEquals(
            'Pay Online',
            trim($this->findCss($page, '.js-pay-selected')->getValue())
        );

        // Test cancel from payment service:
        $this->clickCss($page, '.checkbox-select-all');
        $this->clickCss($page, '.js-pay-selected');
        $this->clickCss($page, '#modal .btn.btn-primary', null, 1);
        $localIdentifier = $this->getLocalIdentifierFromReturnUrl($page);
        $this->clickCss($page, '.button-cancel');
        $this->assertEquals(
            'Payment canceled',
            $this->findCssAndGetText($page, '.alert.alert-success')
        );
        $this->assertEquals(
            PaymentStatus::Canceled,
            $this->getPaymentByLocalIdentifier($localIdentifier)->getStatus()
        );

        // Test failure from payment service:
        $this->clickCss($page, '.checkbox-select-all');
        $this->clickCss($page, '.js-pay-selected');
        $this->clickCss($page, '#modal .btn.btn-primary', null, 1);
        $localIdentifier = $this->getLocalIdentifierFromReturnUrl($page);
        $this->clickCss($page, '.button-failure');
        $this->assertEquals(
            'Payment request failed',
            $this->findCssAndGetText($page, '.alert.alert-danger')
        );
        $this->assertEquals(
            PaymentStatus::PaymentFailed,
            $this->getPaymentByLocalIdentifier($localIdentifier)->getStatus()
        );

        // Test success from payment service:
        $this->clickCss($page, '.checkbox-select-all');
        $this->clickCss($page, '.js-pay-selected');
        $this->clickCss($page, '#modal .btn.btn-primary', null, 1);
        $localIdentifier = $this->getLocalIdentifierFromReturnUrl($page);
        $this->clickCss($page, '.button-success');
        $this->assertEquals(
            'Payment successful',
            $this->findCssAndGetText($page, '.alert.alert-success')
        );
        // Wait for the "Processing Payment" info alert to disappear:
        $this->unFindCss($page, '.alert.alert-info');
        $this->waitForPageLoad($page);
        $this->assertCount(
            2,
            $page->findAll('css', '.fines-table tbody tr')
        );

        if ($receiptEnabled) {
            $this->assertStringStartsWith(
                'Last paid: $15.00',
                $this->findCssAndGetText($page, '.last-payment-information')
            );
        } else {
            $this->unFindCss($page, '.last-payment-information');
        }
        $payment = $this->getPaymentByLocalIdentifier($localIdentifier);
        $this->assertEquals(
            PaymentStatus::Completed,
            $payment->getStatus()
        );

        // Check receipt email:
        if ($receiptEnabled) {
            $email = $this->getLoggedEmail();
            $this->assertStringContainsString(
                'A receipt for your payment is attached as a PDF file',
                $email->getBody()->getParts()[0]->getBody()
            );
        }

        // Verify database contents:
        $this->assertEquals(
            1500,
            $payment->getAmount()
        );
        $paymentFeeService = $this->getDbService(PaymentFeeServiceInterface::class);
        assert($paymentFeeService instanceof PaymentFeeServiceInterface);
        $this->assertEquals(
            [
                'demo1',
                'demo2',
            ],
            $paymentFeeService->getFineIdsForPayment($payment)
        );
        $paymentService = $this->getDbService(PaymentServiceInterface::class);
        assert($paymentService instanceof PaymentServiceInterface);
        $this->assertSame(
            $payment,
            $paymentService->getLastPaidPaymentForPatron($multibackend ? 'pay.catuser' : 'catuser')
        );
        $auditEventService = $this->getDbService(AuditEventServiceInterface::class);
        assert($auditEventService instanceof AuditEventServiceInterface);
        $events = array_map(
            function (AuditEventEntityInterface $event) {
                return [$event->getSubType(), $event->getMessage()];
            },
            $auditEventService->getEvents(payment: $payment, sort: ['id desc'])
        );
        $expectedEvents = [
            [AuditEventSubtype::PaymentRegistration->value, 'Successfully registered'],
            [AuditEventSubtype::PaymentRegistration->value, 'Started registration'],
            [AuditEventSubtype::PaymentRegistration->value, 'Registration requested'],
            [AuditEventSubtype::PaymentResponseHandler->value, 'Response handler called'],
        ];
        if ($receiptEnabled) {
            $expectedEvents[] = [AuditEventSubtype::PaymentReceipt->value, 'Receipt sent'];
        }
        $expectedEvents = [
            ...$expectedEvents,
            [AuditEventSubtype::Payment->value, 'Payment marked as paid'],
            [AuditEventSubtype::PaymentNotifyHandler->value, 'Handler called'],
            [AuditEventSubtype::Payment->value, 'Redirected to payment gateway'],
            [AuditEventSubtype::Payment->value, 'Payment created'],
        ];

        $this->assertEquals($expectedEvents, $events);
    }

    /**
     * Test payment without returning to VuFind.
     *
     * @return bool
     *
     * @depends testPayment
     */
    public function testNotify(): bool
    {
        $this->changeConfigs($this->getConfigs(false, []));

        $page = $this->goToFines(false, false);

        $this->checkForMissingDevTools($page);

        $this->findCss($page, '.online-payment');
        $this->clickCss($page, '.checkbox-select-all');
        $this->assertEquals(
            'Pay Online $15.00',
            $this->findCss($page, '.js-pay-selected')->getValue()
        );

        // Test success from payment service:
        $this->clickCss($page, '.js-pay-selected');
        $this->clickCss($page, '#modal .btn.btn-primary', null, 1);
        $this->waitForPageLoad($page);

        // Check payment status:
        $payment = $this->getPaymentFromReturnUrl($page);
        $this->assertEquals(
            $payment->getStatus(),
            PaymentStatus::InProgress
        );

        // Send notify event:
        $this->clickCss($page, '.button-notify');
        $this->assertEqualsWithTimeout(
            'Notify done',
            function () use ($page) {
                return $this->findCssAndGetText($page, 'body');
            }
        );

        // Check payment status again:
        $paymentService = $this->getDbService(PaymentServiceInterface::class);
        assert($paymentService instanceof PaymentServiceInterface);
        $paymentService->refreshEntity($payment);
        $this->assertEquals(
            $payment->getStatus(),
            PaymentStatus::Paid
        );

        // Resolve the payment so that it doesn't block further tests:
        $payment->applyRegistrationResolvedStatus();
        $paymentService->persistEntity($payment);

        return true;
    }

    /**
     * Test last payment info when there are no fines.
     *
     * @param bool $status Status from testNotify
     *
     * @return void
     *
     * @depends testNotify
     */
    public function testLastPaymentInfo(bool $status): void
    {
        if (true !== $status) {
            $this->markTestSkipped('Dependent test skipped');
        }
        $demoConfig = $this->getDemoIniOverrides() + $this->getDemoIniOverridesForPayment();
        $demoConfig['Records']['fines'] = json_encode([]);
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(false),
                'Demo' => $demoConfig,
            ]
        );

        $page = $this->goToFines(false, false);

        $this->checkForMissingDevTools($page);

        $this->assertStringStartsWith(
            'Last paid: $15.00',
            $this->findCssAndGetText($page, '.last-payment-information')
        );
    }

    /**
     * Data provider for testReceipt
     *
     * @return array
     */
    public static function receiptProvider(): array
    {
        return [
            'no VAT breakdown' => [false],
            'VAT breakdown' => [true],
        ];
    }

    /**
     * Test receipt on demand.
     *
     * @param bool $vatBreakdown VAT breakdown enabled?
     *
     * @return void
     *
     * @dataProvider receiptProvider
     * @depends      testPayment
     */
    public function testReceipt(bool $vatBreakdown): void
    {
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(false),
                'Demo' => $this->getDemoIniOverrides() + $this->getDemoIniOverridesForPayment(compact('vatBreakdown')),
            ]
        );

        $page = $this->goToFines(false, false);

        $this->checkForMissingDevTools($page);

        $session = $this->getMinkSession();
        $windowCount = count($session->getWindowNames());
        $this->clickCss($page, '.last-payment-information a');
        $this->assertEqualsWithTimeout(
            $windowCount + 1,
            function () use ($session) {
                return count($session->getWindowNames());
            }
        );

        // Check contents of HTML version of the receipt (the PDF version can't be loaded in chrome-headless-shell):
        $session->visit($this->getVuFindUrl('/MyResearch/Fines?paymentReceipt=true&html=true'));
        $this->findCss($page, '.fee-table');
        if ($vatBreakdown) {
            $vatBreakdownEl = $this->findCss($page, '.vat-breakdown tbody');
            $this->assertEquals('0.0% $1.50 $0.00 $1.50 25.5% $10.76 $2.74 $13.50', $vatBreakdownEl->getText());
        } else {
            $this->unFindCss($page, '.vat-breakdown');
        }
    }

    /**
     * Data provider for testBlockedPayment
     *
     * @return array
     */
    public static function blockedPaymentProvider(): array
    {
        $blockMsg = 'You have fees that cannot be paid online. Please contact the library customer service.';
        return [
            'overdue fee blocks payment' => [
                [
                    'blockingNonPayableTypes' => ['Overdue'],
                ],
                $blockMsg,
            ],
            'lost card fee blocks payment' => [
                [
                    'blockingNonPayableDescriptions' => ['Lost card replacement'],
                ],
                $blockMsg,
            ],
            'lost card fee blocks payment (regex)' => [
                [
                    'blockingNonPayableDescriptions' => ['/Lost.*replacement/'],
                ],
                $blockMsg,
            ],
            'lost card fee blocks payment (regex with modifier)' => [
                [
                    'blockingNonPayableDescriptions' => ['/Lost.*replacement/u'],
                ],
                $blockMsg,
            ],
            'minimum payable amount blocks payment' => [
                [
                    'minimumFee' => '5000',
                ],
                'Minimum payable amount: $50.00',
            ],
        ];
    }

    /**
     * Test rules that block payment.
     *
     * @param array  $paymentSettings Additional online payment settings
     * @param string $expectedMsg     Expected block message
     *
     * @return void
     *
     * @dataProvider blockedPaymentProvider
     */
    public function testBlockedPayment(array $paymentSettings, string $expectedMsg): void
    {
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(false),
                'Demo' => $this->getDemoIniOverrides() + $this->getDemoIniOverridesForPayment($paymentSettings),
            ]
        );

        $page = $this->goToFines(false, false);
        $this->checkForMissingDevTools($page);
        $this->assertEquals(
            $expectedMsg,
            $this->findCssAndGetText($page, '.fines-info-area__blocked')
        );
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['username1', 'username2']);
    }

    /**
     * Log in and display fines
     *
     * @param bool $createAccount Do we need a new user account?
     * @param bool $multibackend  Is MultiBackend driver enabled?
     *
     * @return DocumentElement
     */
    protected function goToFines(bool $createAccount, bool $multibackend): DocumentElement
    {
        // Go to user profile screen:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/MyResearch/Fines');
        $page = $session->getPage();

        // Set up user account if necessary:
        if ($createAccount) {
            $this->clickCss($page, '.createAccountLink');
            $this->fillInAccountForm(
                $page,
                $multibackend
                    ? [
                        'email' => 'username2@ignore.com',
                        'username' => 'username2',
                    ]
                    : []
            );
            $this->clickCss($page, 'input.btn.btn-primary');

            // Link ILS profile:
            $this->submitCatalogLoginForm($page, 'catuser', 'catpass');
        } else {
            $this->fillInLoginForm($page, $multibackend ? 'username2' : 'username1', 'test', false);
            $this->clickCss($page, 'input.btn.btn-primary');
        }

        $this->waitForPageLoad($page);
        return $page;
    }

    /**
     * Get config file overrides for testing payment functions.
     *
     * @param bool   $multibackend    Use MultiBackend driver?
     * @param ?array $paymentSettings Payment settings, or null to disable
     *
     * @return array
     */
    protected function getConfigs(bool $multibackend, ?array $paymentSettings = null): array
    {
        $configs = [
            'config' => $this->getConfigIniOverrides($multibackend),
        ];
        $paymentOverrides = null !== $paymentSettings
            ? $this->getDemoIniOverridesForPayment($paymentSettings)
            : [];
        if ($multibackend) {
            $configs['MultiBackend'] = [
                'Drivers' => [
                    'pay' => 'Demo',
                    'nopay' => 'Demo',
                ],
                'Login' => [
                    'default_driver' => 'pay',
                    'drivers' => [
                        'pay',
                        'nopay',
                    ],
                ],
            ];
            $configs['Demo:pay'] = $this->getDemoIniOverrides() + $paymentOverrides;
            $configs['Demo:nopay'] = $this->getDemoIniOverrides();
        } else {
            $configs['Demo'] = $this->getDemoIniOverrides() + $paymentOverrides;
        }
        return $configs;
    }

    /**
     * Get config.ini override settings for testing payment functions.
     *
     * @param bool $multibackend Use MultiBackend driver?
     *
     * @return array
     */
    protected function getConfigIniOverrides(bool $multibackend): array
    {
        $config = [
            'Catalog' => [
                'driver' => $multibackend ? 'MultiBackend' : 'Demo',
                'holds_mode' => 'driver',   // needed to display login link
            ],
            'Mail' => [
                'testOnly' => true,
                'message_log' => $this->getEmailLogPath(),
                'message_log_format' => $this->getEmailLogFormat(),
            ],
        ];
        return $config;
    }

    /**
     * Get Demo.ini override settings for enabling payment.
     *
     * @param array $additional Additional settings
     *
     * @return array
     */
    protected function getDemoIniOverridesForPayment(array $additional = []): array
    {
        return [
            'OnlinePayment' => array_merge(
                [
                    'enabled' => true,
                    'currency' => 'USD',
                    'selectFines' => true,
                    'productCodeMappings' => 'Overdue=demo_003:Long Overdue=demo_004',
                    'handler' => 'Test',
                    'url' => $this->getVuFindUrl('/devtools/payment'),
                ],
                $additional
            ),
        ];
    }

    /**
     * Get fine JSON for Demo.ini.
     *
     * @param string $bibId Bibliographic record ID to create fake item info for.
     *
     * @return string
     */
    protected function getFakeFines(string $bibId): string
    {
        return json_encode([
            // Minimal record:
            [
                'amount' => 123,
                'balance' => 123,
                'checkout' => date('Y-m-d', strtotime('now -30 days')),
                'createdate' => date('Y-m-d', strtotime('now -2 days')),
                'duedate' => date('Y-m-d', strtotime('now -5 days')),
                'description' => 'Overdue fee',
                'id' => $bibId,
            ],
            // Payable:
            [
                'fineId' => 'demo1',
                'amount' => 150,
                'balance' => 150,
                'checkout' => date('Y-m-d', strtotime('now -30 days')),
                'createdate' => date('Y-m-d', strtotime('now -2 days')),
                'fine' => 'Overdue',
                'description' => 'Overdue description',
                'payableOnline' => true,
            ],
            [
                'fineId' => 'demo2',
                'amount' => 1350,
                'balance' => 1350,
                'checkout' => date('Y-m-d', strtotime('now -60 days')),
                'createdate' => date('Y-m-d', strtotime('now -4 days')),
                'fine' => 'Overdue',
                'description' => 'Overdue description',
                'payableOnline' => true,
                'taxPercent' => 2550,
            ],
            // Potentially unpayable:
            [
                'fineId' => 'demo3',
                'amount' => 350,
                'balance' => 350,
                'createdate' => date('Y-m-d', strtotime('now -2 days')),
                'fine' => 'Manual',
                'description' => 'Lost card replacement',
                'payableOnline' => false,
            ],
        ]);
    }

    /**
     * Get the local identifier from the returl URL of the payment service
     *
     * @param Element $page Page
     *
     * @return string
     */
    protected function getLocalIdentifierFromReturnUrl(Element $page): string
    {
        $returnUrl = $this->findCss($page, 'input[name="returnUrl"]')->getValue();
        parse_str(parse_url($returnUrl, PHP_URL_QUERY), $queryParams);
        $this->assertArrayHasKey('local_payment_id', $queryParams);
        return $queryParams['local_payment_id'];
    }

    /**
     * Get a payment entity by the local identifier in the returl URL of the payment service
     *
     * @param string $localIdentifier Local identifier
     *
     * @return PaymentEntityInterface
     */
    protected function getPaymentByLocalIdentifier(string $localIdentifier): PaymentEntityInterface
    {
        $paymentService = $this->getDbService(PaymentServiceInterface::class);
        assert($paymentService instanceof PaymentServiceInterface);
        return $paymentService->getPaymentByLocalIdentifier($localIdentifier);
    }

    /**
     * Get a payment entity by the local identifier in the returl URL of the payment service
     *
     * @param Element $page Page
     *
     * @return PaymentEntityInterface
     */
    protected function getPaymentFromReturnUrl(Element $page): PaymentEntityInterface
    {
        return $this->getPaymentByLocalIdentifier($this->getLocalIdentifierFromReturnUrl($page));
    }

    /**
     * Check for blocked payment due to missing VuFindDevTools module
     *
     * @param Element $page Page
     *
     * @return void
     */
    protected function checkForMissingDevTools(Element $page): void
    {
        if (
            ($paymentBlock = $page->find('css', '.fines-info-area__blocked'))
            && $paymentBlock->getText() === 'Test handler not available (VuFindDevTools module not loaded)'
        ) {
            $this->markTestIncomplete('Cannot test payment; VuFindDevTools module not loaded');
        }
    }
}
