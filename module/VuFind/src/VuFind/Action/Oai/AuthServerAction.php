<?php

/**
 * OAI-PMH authority server action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2016-2026.
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

namespace VuFind\Action\Oai;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Http\ServerUrlHelper;
use VuFind\OAI\Server\Auth as AuthServer;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\Helper\Root\RecordLinker;
use VuFindApi\Formatter\RecordFormatter;

/**
 * OAI-PMH authority server action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AuthServerAction extends AbstractOaiServerAction
{
    /**
     * Constructor.
     *
     * @param ServerUrlHelper $serverUrlHelper        Server URL helper
     * @param RecordFormatter $recordFormatter        Record formatter
     * @param RecordLinker    $recordLinkerViewHelper RecordLinker view helper
     * @param array           $config                 VuFind configuration
     * @param AuthServer      $server                 Authority server
     */
    public function __construct(
        protected ServerUrlHelper $serverUrlHelper,
        protected RecordFormatter $recordFormatter,
        #[Autowire(container: 'ViewHelperManager')]
        protected RecordLinker $recordLinkerViewHelper,
        #[Autowire(config: 'config')]
        protected array $config,
        protected AuthServer $server,
    ) {
        parent::__construct($serverUrlHelper, $recordFormatter, $recordLinkerViewHelper, $config);
    }

    /**
     * Handle an OAI-PMH request on the authority index.
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
        return $this->handleOAI($request, $response, $this->server);
    }
}
