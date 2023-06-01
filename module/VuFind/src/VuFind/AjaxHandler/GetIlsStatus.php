<?php

/**
 * "Get ILS Status" AJAX handler
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\RendererInterface;
use VuFind\ILS\Connection;
use VuFind\Session\Settings as SessionSettings;

/**
 * "Get ILS Status" AJAX handler
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
     * ILS connection
     *
     * @var Connection
     */
    protected $ils;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * Constructor
     *
     * @param SessionSettings   $ss       Session settings
     * @param Connection        $ils      ILS connection
     * @param RendererInterface $renderer View renderer
     */
    public function __construct(
        SessionSettings $ss,
        Connection $ils,
        RendererInterface $renderer
    ) {
        $this->sessionSettings = $ss;
        $this->ils = $ils;
        $this->renderer = $renderer;
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
        $html = null;
        $this->disableSessionWrites();
        if ($this->ils->getOfflineMode(true) == 'ils-offline') {
            $offlineModeMsg = $params->fromPost(
                'offlineModeMsg',
                $params->fromQuery('offlineModeMsg')
            );
            $html = $this->renderer
                ->render('Helpers/ils-offline.phtml', compact('offlineModeMsg'));
        }
        return $this->formatResponse(['html' => $html ?? '']);
    }
}
