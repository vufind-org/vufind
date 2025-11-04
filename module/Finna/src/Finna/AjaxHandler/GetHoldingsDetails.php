<?php

/**
 * AJAX handler for fetching holdings details
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2019-2020.
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
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\RendererInterface;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\ILS\Connection;
use VuFind\ILS\Logic\Holds as HoldLogic;
use VuFind\Record\Loader;
use VuFind\Session\Settings as SessionSettings;

use function in_array;

/**
 * AJAX handler for fetching holdings details
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetHoldingsDetails extends \VuFind\AjaxHandler\AbstractIlsAndUserAction
{
    /**
     * Constructor
     *
     * @param SessionSettings      $ss               Session settings
     * @param Connection           $ils              ILS connection
     * @param ILSAuthenticator     $ilsAuthenticator ILS authenticator
     * @param ?UserEntityInterface $user             Logged in user (or null)
     * @param RendererInterface    $renderer         View renderer
     * @param Loader               $recordLoader     Record loader
     * @param HoldLogic            $holdLogic        Hold Logic
     */
    public function __construct(
        SessionSettings $ss,
        Connection $ils,
        ILSAuthenticator $ilsAuthenticator,
        ?UserEntityInterface $user,
        protected RendererInterface $renderer,
        protected Loader $recordLoader,
        protected HoldLogic $holdLogic
    ) {
        parent::__construct($ss, $ils, $ilsAuthenticator, $user);
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites(); // avoid session write timing bug

        $detailsGroupKey = $params->fromPost('key', $params->fromQuery('key'));
        $page = $params->fromPost('page', $params->fromQuery('page', 1));
        $recordId = $params->fromPost('recordId', $params->fromQuery('recordId'));
        $recordSource = $params->fromPost(
            'recordSource',
            $params->fromQuery('recordSource', DEFAULT_SEARCH_BACKEND)
        );
        if (empty($detailsGroupKey) || empty($recordId)) {
            return $this->formatResponse(
                $this->translate('Missing parameters'),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }

        $params = compact('page', 'detailsGroupKey');
        $result = $this->holdLogic->getHoldings($recordId, null, $params);
        $holding = $result['details'];
        $textFieldNames = $this->ils->getHoldingsTextFieldNames();
        foreach ($textFieldNames as $fieldName) {
            if (in_array($fieldName, ['notes', 'holdings_notes'])) {
                if (empty($holding[$fieldName])) {
                    // begin aliasing
                    if (
                        $fieldName == 'notes'
                        && !empty($holding['holdings_notes'])
                    ) {
                        // using notes as alias for holdings_notes
                        $holding[$fieldName] = $holding['holdings_notes'];
                    } elseif (
                        $fieldName == 'holdings_notes'
                        && !empty($holding['notes'])
                    ) {
                        // using holdings_notes as alias for notes
                        $holding[$fieldName] = $holding['notes'];
                    }
                }
            }
            if (isset($holding[$fieldName])) {
                $holding['textfields'][$fieldName] = (array)$holding[$fieldName];
            }
        }

        $mode = 'expanded';
        $details = $this->renderer->partial(
            'RecordTab/holdings-details.phtml',
            compact('holding', 'mode')
        );
        $holdingItems = reset($result['holdings']);
        $moreLinkPage = $result['page'] * $result['itemLimit'] < $result['total']
            ? $result['page'] + 1 : null;
        $items = $holdingItems ? $this->renderer->partial(
            'RecordTab/holdings-items.phtml',
            [
                'driver'
                    => $this->recordLoader->load($recordId, $recordSource, true),
                'holding' => $holdingItems,
                'mode' => 'expanded',
                'moreLinkPage' => $moreLinkPage,
                'moreLinkKey' => $detailsGroupKey,
            ]
        ) : '';

        return $this->formatResponse(compact('details', 'items'));
    }
}
