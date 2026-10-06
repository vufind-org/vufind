<?php

/**
 * Abstract base class for admin actions.
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

namespace VuFindAdmin\Action\Admin;

use Psr\Http\Message\ResponseInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\GlobalsContainer;

/**
 * Abstract base class for admin actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractAdminAction extends AbstractTemplateRenderingAction
{
    /**
     * Should we check if the admin_enabled setting is true in config?
     *
     * @var bool
     */
    protected bool $checkAdminEnabled = true;

    /**
     * Constructor.
     *
     * @param GlobalsContainer $globalsContainer Globals container
     * @param array            $config           VuFind configuration
     */
    public function __construct(
        protected GlobalsContainer $globalsContainer,
        #[Autowire(config: 'config')]
        protected array $config,
    ) {
        parent::__construct();
    }

    /**
     * Initialize the action.
     *
     * @return void
     */
    protected function init(): void
    {
        parent::init();

        // Disable search box:
        $this->globalsContainer['searchbox'] = false;
    }

    /**
     * Validate any access permission for the action.
     *
     * @return ?ResponseInterface A response if access is denied, null otherwise
     */
    public function validateAccessPermission(): ?ResponseInterface
    {
        // Block access to everyone when module is disabled:
        if ($this->checkAdminEnabled && !($this->config['Site']['admin_enabled'] ?? false)) {
            return $this->renderTemplate($this->request, $this->response, template: 'admin/disabled');
        }

        return parent::validateAccessPermission();
    }
}
