<?php

/**
 * Abstract base class for Fines actions.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023-2026.
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
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Fines;

use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Config\Feature\EmailSettingsTrait;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PaymentServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\ILS\Connection;
use VuFind\ILS\Logic\RecordsHelper;
use VuFind\ILS\Logic\SummaryTrait;
use VuFind\OnlinePayment\OnlinePaymentManager;
use VuFind\RecordDriver\Missing as MissingRecord;
use VuFind\Service\CurrencyFormatter;
use VuFind\ServiceManager\Factory\Autowire;

use function count;

/**
 * Abstract base class for Fines actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractFinesAction extends AbstractTemplateRenderingAction implements TranslatorAwareInterface
{
    use EmailSettingsTrait;
    use TranslatorAwareTrait;
    use SummaryTrait;

    /**
     * Constructor.
     *
     * @param AuthManager                $authManager          Authentication manager
     * @param Connection                 $ilsConnection        ILS connection
     * @param RecordsHelper              $ilsRecordsHelper     ILS records helper
     * @param CurrencyFormatter          $currencyFormatter    Currency formatter
     * @param OnlinePaymentManager       $onlinePaymentManager Online payment manager
     * @param PaymentServiceInterface    $paymentService       Payment database service
     * @param AuditEventServiceInterface $auditEventService    Audit event database service
     * @param array                      $config               VuFind configuration
     */
    public function __construct(
        protected AuthManager $authManager,
        protected Connection $ilsConnection,
        protected RecordsHelper $ilsRecordsHelper,
        protected CurrencyFormatter $currencyFormatter,
        protected OnlinePaymentManager $onlinePaymentManager,
        #[Autowire(container: DbServicePluginManager::class)]
        protected PaymentServiceInterface $paymentService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected AuditEventServiceInterface $auditEventService,
        #[Autowire(config: 'config')]
        protected array $config,
    ) {
        parent::__construct();
    }

    /**
     * Get fines.
     *
     * @param array $patron Patron
     *
     * @return array
     */
    protected function getFines(array $patron): array
    {
        $fines = $this->ilsConnection->getMyFines($patron);
        foreach ($this->ilsRecordsHelper->getDrivers($fines) as $i => $driver) {
            if (!($driver instanceof MissingRecord)) {
                $fines[$i]['driver'] = $driver;
                if (empty($fines[$i]['title'])) {
                    $fines[$i]['title'] = $driver->tryMethod('getTitle');
                }
            }
        }
        return $fines;
    }

    /**
     * Get account status information if enabled.
     *
     * @param array $fines Fines
     *
     * @return ?array
     */
    protected function getAccountStatus(array $fines): ?array
    {
        // Collect up to date stats for ajax account notifications:
        return empty($this->config['Authentication']['enableAjax'])
            ? null
            : $this->getFineSummary($fines, $this->currencyFormatter);
    }

    /**
     * Get template params.
     *
     * @param array $patron         Patron
     * @param array $templateParams Parameters to include
     *
     * @return array
     */
    protected function getTemplateParams(array $patron, array $templateParams = []): array
    {
        $templateParams['fines'] = $fines = $this->getFines($patron);
        $templateParams['accountStatus'] = $this->getAccountStatus($fines);
        $templateParams['onlinePayment'] = false;
        $templateParams['onlinePaymentEnabled'] = false;
        $sourceIls = $patron['__source'] ?? 'default';

        // Check if online payment configuration exists and is valid for the ILS driver
        $paymentConfig = $this->onlinePaymentManager->getAndValidateOnlinePaymentConfig($patron);
        if (!$paymentConfig) {
            $this->debug("No online payment ILS configuration for $sourceIls");
            return $templateParams;
        }

        $selectFees = $paymentConfig['selectFines'] ?? false;
        $selectedIds = $selectFees ? (array)($this->getPostParam('selectedIDS', [])) : [];
        $paymentDetails = $this->onlinePaymentManager->getAndCheckOnlinePaymentDetails(
            $patron,
            $fines,
            $selectedIds
        );
        if ($selectedIds && !$paymentDetails['fines']) {
            $this->logError("Fines to pay missing from ILS driver for $sourceIls");
            return $templateParams;
        }

        $templateParams['onlinePayment'] = true;
        $templateParams['paymentHandler'] = $this->onlinePaymentManager->getHandlerName($sourceIls);
        $templateParams['serviceFee'] = $paymentConfig['serviceFee'] ?? 0;
        $templateParams['minimumFee'] = $paymentConfig['minimumFee'] ?? 0;
        $templateParams['payable'] = $paymentDetails['payable'];
        $templateParams['payableOnline'] = $paymentDetails['amount'];
        $templateParams['payableTotal'] = $paymentDetails['amount'] + $templateParams['serviceFee'];
        $templateParams['payableOnlineCnt'] = count($paymentDetails['fines']);
        $templateParams['nonPayableFines'] = count($fines) != count($paymentDetails['fines']);
        $templateParams['registerPayment'] = false;
        $templateParams['selectFees'] = $selectFees;
        $templateParams['selectedIds'] = $selectedIds;
        $templateParams['selectedFines'] = $paymentDetails['fines'];
        $templateParams['exactBalanceRequired'] = $paymentConfig['exactBalanceRequired'] ?? true;
        $templateParams['creditUnsupported'] = $paymentConfig['creditUnsupported'] ?? false;
        $templateParams['paymentMaxDuration'] = (int)($paymentConfig['paymentMaxDuration'] ?? 15);

        $templateParams['lastPayment'] = $paymentConfig['receipt'] ?? false
            ? $this->paymentService->getLastPaidPaymentForPatron($patron['cat_username'])
            : null;

        return $templateParams;
    }
}
