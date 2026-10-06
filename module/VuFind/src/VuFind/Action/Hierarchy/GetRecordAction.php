<?php

/**
 * "Get hierarchy record" action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2023.
 * Copyright (C) The National Library of Finland 2024-2026.
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

namespace VuFind\Action\Hierarchy;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\ResponseHelper;
use VuFind\Http\HttpStatus;
use VuFind\Record\Loader as RecordLoader;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Helper\Root\Record as RecordHelper;

/**
 * "Get hierarchy record" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class GetRecordAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param RecordLoader $recordLoader Record loader
     * @param RecordHelper $recordHelper Record view helper
     */
    public function __construct(
        protected RecordLoader $recordLoader,
        #[Autowire(container: 'ViewHelperManager')]
        protected RecordHelper $recordHelper,
    ) {
    }

    /**
     * Get hierarchy tree.
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
        $this->disableSessionWrites();  // avoid session write timing bug

        $responseHelper = $this->getHelper(ResponseHelper::class);
        if (null === ($id = $this->getQueryParam('id'))) {
            return $responseHelper->getJsonResponse($response, ['error' => 'Missing ID'], HttpStatus::BAD_REQUEST);
        }
        $source = $this->getQueryParam('sourceId', DEFAULT_SEARCH_BACKEND);

        try {
            $record = $this->recordLoader->load($id, $source);
            $result = ($this->recordHelper)($record)->getCollectionBriefRecord();
        } catch (\VuFind\Exception\RecordMissing $e) {
            $result = $this->getTemplateRenderer()
                ->renderTemplateAsString(template: 'collection/collection-record-error.phtml');
        }

        return $responseHelper->getAjaxResponse($response, 'text/html', $result, allowCaching: true);
    }
}
