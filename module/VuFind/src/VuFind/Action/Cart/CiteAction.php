<?php

/**
 * Cart cite form action.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Cart;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\BulkActionHelper;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\Cart;
use VuFind\Export;
use VuFind\Record\Loader as RecordLoader;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;
use VuFind\View\Helper\Root\Citation;

use function count;

/**
 * Cart cite form action.
 *
 * @category VuFind
 * @package  Action
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class CiteAction extends AbstractCartAction
{
    /**
     * Constructor.
     *
     * @param Export         $export             Export handler
     * @param Cart           $cart               Cart handler
     * @param FollowupHelper $followupHelper     Followup helper
     * @param RecordLoader   $recordLoader       Record loader
     * @param Citation       $citationViewHelper Citation view helper
     */
    public function __construct(
        Export $export,
        Cart $cart,
        FollowupHelper $followupHelper,
        protected RecordLoader $recordLoader,
        #[Autowire(container: 'ViewHelperManager')]
        protected Citation $citationViewHelper,
    ) {
        parent::__construct($export, $cart, $followupHelper);
    }

    /**
     * Cite cart.
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

        // Get id limit
        $actionLimit = $bulkActionHelper->getBulkActionLimit('cite');

        if (!$ids) {
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
        }

        // Load the records:
        $records = $this->recordLoader->loadBatch($ids);

        // Get all available citations
        $citations = [];
        foreach ($records as $record) {
            $citationHelper = ($this->citationViewHelper)($record);
            foreach ($record->getCitationFormats() as $format) {
                $citations[$format][$record->getUniqueId()] = $citationHelper->getCitation($format);
            }
        }

        if (!$citations) {
            $this->getHelper(FlashMessagesHelper::class)
                ->addErrorMessage('No citations are available for these records');
        }

        return $this->renderTemplate($request, $response, compact('citations'));
    }
}
