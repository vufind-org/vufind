<?php

/**
 * "Get Record Details" AJAX handler.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Psr7Bridge\Psr7ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Record\Loader;
use VuFind\RecordTab\TabManager;
use VuFind\View\Renderer\TemplateRendererInterface;

/**
 * "Get Record Details" AJAX handler.
 *
 * Get record for integrated list view.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetRecordDetails extends AbstractBase
{
    /**
     * Constructor.
     *
     * @param array                     $config       Framework configuration
     * @param Loader                    $recordLoader Record loader
     * @param TabManager                $tabManager   Record Tab manager
     * @param TemplateRendererInterface $renderer     Template renderer
     */
    public function __construct(
        protected array $config,
        protected Loader $recordLoader,
        protected TabManager $tabManager,
        protected TemplateRendererInterface $renderer,
    ) {
        parent::__construct(null);
    }

    /**
     * Handle a request.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(ServerRequestInterface $request): array
    {
        $driver = $this->recordLoader->load(
            $this->getQueryParam($request, 'id'),
            $this->getQueryParam($request, 'source')
        );
        $viewtype = preg_replace(
            '/\W/',
            '',
            trim(strtolower($this->getQueryParam($request, 'type')))
        );

        $details = $this->tabManager->getTabDetailsForRecord(
            $driver,
            Psr7ServerRequest::toLaminas($request),
            'Information'
        );

        $html = $this->renderer->renderTemplateAsString(
            $request,
            'record/ajaxview-' . $viewtype . '.phtml',
            [
                'defaultTab' => $details['default'],
                'driver' => $driver,
                'tabs' => $details['tabs'],
                'backgroundTabs' => $this->tabManager
                    ->getBackgroundTabNames($driver),
            ]
        );
        return $this->formatResponse(compact('html'));
    }
}
