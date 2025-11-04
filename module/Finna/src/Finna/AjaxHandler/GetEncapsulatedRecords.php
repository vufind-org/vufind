<?php

/**
 * Get encapsulated records via AJAX.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\AjaxHandler;

use Finna\View\Helper\Root\Record;
use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Session\Settings as SessionSettings;

/**
 * Get encapsulated records via AJAX.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetEncapsulatedRecords extends \VuFind\AjaxHandler\AbstractBase
{
    /**
     * Record helper.
     *
     * @var Record
     */
    protected Record $helper;

    /**
     * Constructor
     *
     * @param SessionSettings $ss     Session settings
     * @param Record          $helper Record helper
     */
    public function __construct(SessionSettings $ss, Record $helper)
    {
        $this->sessionSettings = $ss;
        $this->helper = $helper;
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
        $this->disableSessionWrites();  // avoid session write timing bug

        $id = $params->fromPost('id', $params->fromQuery('id'));
        $view
            = $params->fromPost('view', $params->fromQuery('view'));
        $offset = $params->fromPost('offset', $params->fromQuery('offset'));
        $indexStart
            = $params->fromPost('indexStart', $params->fromQuery('indexStart'));

        $html = ($this->helper)($id)->renderEncapsulatedRecords(
            [
                'limit' => null, // No limit
                'page' => 1,
                'view' => $view,
            ],
            $offset,
            $indexStart
        );
        return $this->formatResponse(compact('html'));
    }
}
