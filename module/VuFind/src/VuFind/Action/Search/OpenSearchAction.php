<?php

/**
 * OpenSearch handler action.
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

namespace VuFind\Action\Search;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\ResponseHelper;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * OpenSearch handler action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class OpenSearchAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param array $config VuFind configuration
     */
    public function __construct(
        #[Autowire(config: 'config')] protected array $config,
    ) {
        parent::__construct();
    }

    /**
     * Handle an OpenSearch request.
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
        switch ($this->getQueryParam('method')) {
            case 'describe':
                $xml = $this->getTemplateRenderer()->renderTemplateAsString(
                    template: 'search/opensearch-describe.phtml',
                    params: ['site' => $this->config['Site'] ?? '']
                );
                break;
            default:
                $xml = $this->getTemplateRenderer()->renderTemplateAsString(template: 'search/opensearch-error.phtml');
                break;
        }

        return $this->getHelper(ResponseHelper::class)->getAjaxResponse(
            $response,
            'text/xml',
            $xml
        );
    }
}
