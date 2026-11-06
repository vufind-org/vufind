<?php

/**
 * Fines receipt action.
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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\LoginHelper;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Db\Service\AuditEventServiceInterface;
use VuFind\Db\Service\PaymentServiceInterface;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\ILS\Connection;
use VuFind\ILS\Logic\RecordsHelper;
use VuFind\OnlinePayment\OnlinePaymentManager;
use VuFind\OnlinePayment\Receipt;
use VuFind\Service\CurrencyFormatter;
use VuFind\ServiceManager\Factory\Autowire;

use function is_array;

/**
 * Fines receipt action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ReceiptAction extends AbstractFinesAction
{
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
     * @param Receipt                    $receipt              Receipt creator
     */
    public function __construct(
        AuthManager $authManager,
        Connection $ilsConnection,
        RecordsHelper $ilsRecordsHelper,
        CurrencyFormatter $currencyFormatter,
        OnlinePaymentManager $onlinePaymentManager,
        #[Autowire(container: DbServicePluginManager::class)]
        PaymentServiceInterface $paymentService,
        #[Autowire(container: DbServicePluginManager::class)]
        AuditEventServiceInterface $auditEventService,
        #[Autowire(config: 'config')]
        array $config,
        protected Receipt $receipt,
    ) {
        parent::__construct(
            $authManager,
            $ilsConnection,
            $ilsRecordsHelper,
            $currencyFormatter,
            $onlinePaymentManager,
            $paymentService,
            $auditEventService,
            $config
        );
    }

    /**
     * Pay fines.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->getHelper(LoginHelper::class)->catalogLogin($request, $response))) {
            if (!($patron instanceof ResponseInterface)) {
                throw new \Exception('Unexpected response from LoginHelper::catalogLogin');
            }
            return $patron;
        }

        $lastPayment = null;
        $paymentConfig = $this->onlinePaymentManager->getAndValidateOnlinePaymentConfig($patron);
        if (
            !($paymentConfig['receipt'] ?? false)
            || !($lastPayment = $this->paymentService->getLastPaidPaymentForPatron($patron['cat_username']))
        ) {
            return $this->renderNotFoundPage($request, $response);
        }

        $data = $this->receipt->createReceiptPDF($lastPayment, $paymentConfig);
        if ($this->getQueryParam('html') === 'true') {
            $this->response->getBody()->write($data['html']);
            return $this->response;
        }
        $response = $this->response->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-disposition', 'inline; filename="' . addcslashes($data['filename'], '"') . '"');
        $response->getBody()->write($data['pdf']);
        return $response;
    }
}
