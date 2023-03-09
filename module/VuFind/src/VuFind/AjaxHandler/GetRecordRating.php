<?php

/**
 * AJAX handler to get the rating for a record.
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Record\Loader as RecordLoader;
use VuFind\View\Helper\Root\Record as RecordHelper;

/**
 * AJAX handler to get the rating for a record.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetRecordRating extends AbstractBase
{
    /**
     * Record loader
     *
     * @var RecordLoader
     */
    protected $recordLoader;

    /**
     * Record helper
     *
     * @var RecordHelper
     */
    protected $recordHelper;

    /**
     * Constructor
     *
     * @param RecordLoader $loader Record loader
     * @param RecordHelper $helper Record helper
     */
    public function __construct(
        RecordLoader $loader,
        RecordHelper $helper
    ) {
        $this->recordLoader = $loader;
        $this->recordHelper = $helper;
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
        $id = $params->fromQuery('id');
        $source = $params->fromQuery('source', DEFAULT_SEARCH_BACKEND);
        if (empty($id)) {
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }
        $driver = $this->recordLoader->load($id, $source, false);
        $html = ($this->recordHelper)($driver)->renderTemplate('rating.phtml');
        return $this->formatResponse(
            [
                'ratingData' => $driver->getRatingData(),
                'html' => $html
            ]
        );
    }
}
