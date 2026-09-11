<?php

/**
 * Checkout action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2026.
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Checkout;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\ILS\Connection;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Validator\CsrfInterface;

use function is_array;

/**
 * Checkout action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HomeAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param CsrfInterface $csrf   CSRF validator
     * @param Connection    $ils    ILS connection
     * @param array         $config VuFind configuration
     */
    public function __construct(
        protected CsrfInterface $csrf,
        protected Connection $ils,
        #[Autowire(config: 'config')]
        protected array $config,
    ) {
    }

    /**
     * Display the barcode scanning page, or process a barcode submission.
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
            return $patron;
        }

        // barcode is echoed back only when one was rejected, so the user can
        // correct it rather than rescan a label that will misread the same
        // way again.
        $barcodePattern = $this->config['Catalog']['checkout_item_barcode_pattern'] ?? '^\d{14}$';
        $templateParams = [
            'barcodePattern' => $barcodePattern,
            'barcode' => '',
        ];

        // Nothing submitted yet: just show the scanner.
        if (!$this->isPost()) {
            return $this->renderTemplate($request, $response, $templateParams, 'checkout/home');
        }

        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        if (!$this->csrf->isValid($this->getPostParam('csrf'))) {
            $flashMessagesHelper->addErrorMessage('error_inconsistent_parameters');
            return $this->renderTemplate($request, $response, [], 'checkout/home');
        }

        // The D modifier so that $ cannot be satisfied by a trailing newline.
        $barcode = trim($this->getPostParam('barcode', ''));
        $templateParams['barcode'] = $barcode;
        if (!preg_match('/' . $barcodePattern . '/D', $barcode)) {
            $this->logWarning('Self checkout: malformed barcode submitted');
            $flashMessagesHelper->addErrorMessage('checkout_barcode_invalid');
            return $this->renderTemplate($request, $response, $templateParams, 'checkout/home');
        }

        // Retrieve the record ID so we can redirect to that page
        $bibId = $this->ils->getBibIdByItemBarcode($barcode);
        if (!$bibId) {
            $this->logWarning("Self checkout: no FOLIO item for barcode $barcode");
            $flashMessagesHelper->addErrorMessage('checkout_barcode_invalid');
            return $this->renderTemplate($request, $response, $templateParams, 'checkout/home');
        }

        return $this->getHelper(RedirectHelper::class)->redirectToRoute(
            $response,
            'record-checkout',
            ['id' => $bibId],
            ['barcode' => $barcode],
        );
    }
}
