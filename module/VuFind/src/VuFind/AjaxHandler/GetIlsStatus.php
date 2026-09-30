<?php

/**
 * "Get ILS Status" AJAX handler.
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
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Psr\Http\Message\ServerRequestInterface;
use VuFind\ILS\Connection;
use VuFind\Session\Settings as SessionSettings;
use VuFind\View\Renderer\TemplateRendererInterface;

/**
 * "Get ILS Status" AJAX handler.
 *
 * This will check the ILS for being online and will return the ils-offline
 * template upon failure.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetIlsStatus extends AbstractBase
{
    /**
     * Constructor.
     *
     * @param SessionSettings           $ss       Session settings
     * @param Connection                $ils      ILS connection
     * @param TemplateRendererInterface $renderer Template renderer
     */
    public function __construct(
        SessionSettings $ss,
        protected Connection $ils,
        protected TemplateRendererInterface $renderer
    ) {
        parent::__construct($ss);
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
        $html = null;
        $this->disableSessionWrites();
        if ($this->ils->getOfflineMode(true) == 'ils-offline') {
            $offlineModeMsg = $this->getPostOrQueryParam($request, 'offlineModeMsg');
            $html = $this->renderer
                ->renderTemplateAsString($request, 'Helpers/ils-offline.phtml', compact('offlineModeMsg'));
        }
        return $this->formatResponse(['html' => $html ?? '']);
    }
}
