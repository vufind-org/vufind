<?php

/**
 * Cart export action.
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
use VuFind\Cart;
use VuFind\Exception\BadConfig;
use VuFind\Export;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\Record\Loader as RecordLoader;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\Helper\FollowupHelper;
use VuFind\View\Helper\Root\Record;

use function count;
use function is_array;

/**
 * Cart export action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DoExportAction extends AbstractCartAction implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

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
     * Export records.
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
        // We use abbreviated parameters here to keep the URL short (there may
        // be a long list of IDs, and we don't want to run out of room):
        $ids = $this->getQueryParam('i', []);
        $format = $this->getQueryParam('f');

        // Make sure we have IDs to export:
        $bulkActionHelper = $this->getHelper(BulkActionHelper::class);
        if (!is_array($ids) || empty($ids)) {
            return $bulkActionHelper->redirectToSource($request, $response, 'error', 'bulk_noitems_advice');
        }

        // Check if id limit is exceeded
        $actionLimit = $bulkActionHelper->getExportActionLimit($format);
        if (count($ids) > $actionLimit) {
            return $bulkActionHelper->redirectToSource($request, $response, 'error', 'bulk_limit_exceeded');
        }

        // Send appropriate HTTP headers for requested format:
        foreach ($this->export->getHeaders($format) as $header) {
            $parts = explode(':', $header, 2);
            if (null === ($value = $parts[1] ?? null)) {
                throw new BadConfig("Unable to parse export header $header");
            }
            $response = $response->withAddedHeader($parts[0], trim($value));
        }

        // Actually export the records
        $records = $this->recordLoader->loadBatch($ids);
        $parts = [];
        foreach ($records as $record) {
            $parts[] = ($this->recordViewHelper)($record)->getExport($format);
        }

        // Process export and return the response
        $response->getBody()->write($this->export->processGroup($format, $parts));
        return $response;
    }
}
