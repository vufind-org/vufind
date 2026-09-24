<?php

/**
 * Delete expired sessions action.
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

namespace VuFindAdmin\Action\AdminMaintenance;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\SessionServiceInterface;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\GlobalsContainer;

/**
 * Delete expired sessions action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DeleteExpiredSessionsAction extends AbstractExpirationAction
{
    /**
     * Constructor.
     *
     * @param GlobalsContainer        $globalsContainer Globals container
     * @param array                   $config           VuFind configuration
     * @param SessionServiceInterface $sessionService   Session service
     */
    public function __construct(
        GlobalsContainer $globalsContainer,
        #[Autowire(config: 'config')]
        array $config,
        #[Autowire(container: DbServicePluginManager::class)]
        protected SessionServiceInterface $sessionService,
    ) {
        parent::__construct($globalsContainer, $config);
    }

    /**
     * Delete expired sessions.
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
        // Delete the expired sessions--this cleans up any junk left in the database by the session garbage collector.
        $this->expire(
            $this->sessionService,
            '%%count%% expired sessions deleted.',
            'No expired sessions to delete.'
        );
        return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'admin/maintenance');
    }
}
