<?php

/**
 * Cart export form action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2026.
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

namespace VuFind\Action\Cart;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\BulkActionHelper;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\FormHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Cart;
use VuFind\Export;
use VuFind\Record\Loader as RecordLoader;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;
use VuFind\View\Helper\Root\Record;

use function count;
use function is_array;

/**
 * Cart export form action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ExportAction extends AbstractCartAction
{
    /**
     * Constructor.
     *
     * @param Export         $export           Export handler
     * @param Cart           $cart             Cart handler
     * @param FollowupHelper $followupHelper   Followup helper
     * @param RecordLoader   $recordLoader     Record loader
     * @param Record         $recordViewHelper Record view helper
     */
    public function __construct(
        Export $export,
        Cart $cart,
        FollowupHelper $followupHelper,
        protected RecordLoader $recordLoader,
        #[Autowire(container: 'ViewHelperManager')]
        protected Record $recordViewHelper,
    ) {
        parent::__construct($export, $cart, $followupHelper);
    }

    /**
     * Show export options.
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
        // Get the desired ID list:
        $bulkActionHelper = $this->getHelper(BulkActionHelper::class);
        $ids = $bulkActionHelper->getSelectedIds($request);

        // Get export tools:
        $export = $this->export;

        // Get id limit
        $format = $this->getPostParam('format');
        $actionLimit = $format
            ? $bulkActionHelper->getExportActionLimit($format)
            : $bulkActionHelper->getBulkActionLimit('export');

        if (!is_array($ids) || empty($ids)) {
            if ($redirect = $bulkActionHelper->redirectToSource($request, $response, 'error', 'bulk_noitems_advice')) {
                return $redirect;
            }
        } elseif (count($ids) > $actionLimit) {
            $errorMsg = [
                'msg' => 'bulk_limit_exceeded',
                'tokens' => ['%%count%%' => count($ids), '%%limit%%' => $actionLimit],
            ];
            if ($redirect = $bulkActionHelper->redirectToSource($request, $response, 'error', $errorMsg)) {
                return $redirect;
            }
        } elseif ($this->getHelper(FormHelper::class)->formWasSubmitted($request)) {
            $url = $export->getBulkUrl($format, $ids);
            if ($export->needsRedirect($format)) {
                return $this->getHelper(RedirectHelper::class)->redirectToUrl($response, $url);
            }
            $exportType = $export->getBulkExportType($format);
            $params = [
                'exportType' => $exportType,
                'format' => $format,
            ];
            if ('post' === $exportType) {
                $records = $this->recordLoader->loadBatch($ids);
                $parts = [];
                foreach ($records as $record) {
                    $parts[] = ($this->recordViewHelper)($record)->getExport($format);
                }

                $params['postField'] = $export->getPostField($format);
                $params['postData'] = $export->processGroup($format, $parts);
                $params['targetWindow'] = $export->getTargetWindow($format);
                $params['url'] = $export->getRedirectUrl($format, '');
            } else {
                $params['url'] = $url;
            }
            $msg = [
                'translate' => false,
                'html' => true,
                'msg' => $this->getTemplateRenderer()->renderTemplateAsString(
                    template: 'cart/export-success.phtml',
                    params: $params
                ),
            ];
            return $bulkActionHelper->redirectToSource($request, $response, 'success', $msg, true);
        }

        // Load the records:
        $templateParams = [
            'records' => $this->recordLoader->loadBatch($ids),
        ];

        // Assign the list of legal export options. We'll filter them down based on what the selected records actually
        // support.
        $templateParams['exportOptions'] = $this->export->getFormatsForRecords($templateParams['records']);

        // No legal export options?  Display a warning:
        if (!$templateParams['exportOptions']) {
            $this->getHelper(FlashMessagesHelper::class)->addErrorMessage('bulk_export_not_supported');
        }

        return $this->renderTemplate($request, $response, $templateParams);
    }
}
