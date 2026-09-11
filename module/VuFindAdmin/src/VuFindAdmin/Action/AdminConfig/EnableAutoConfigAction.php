<?php

/**
 * Enable auto-configuration action.
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

namespace VuFindAdmin\Action\AdminConfig;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\PathResolver;
use VuFind\Config\Writer as ConfigWriter;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\GlobalsContainer;
use VuFindAdmin\Action\Admin\AbstractAdminAction;

/**
 * Enable auto-configuration action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EnableAutoConfigAction extends AbstractAdminAction
{
    /**
     * Constructor.
     *
     * @param GlobalsContainer       $globalsContainer Globals container
     * @param array                  $config           VuFind configuration
     * @param PathResolver           $pathResolver     Path resolver
     * @param ConfigManagerInterface $configManager    Configuration manager
     */
    public function __construct(
        GlobalsContainer $globalsContainer,
        #[Autowire(config: 'config')]
        array $config,
        protected PathResolver $pathResolver,
        protected ConfigManagerInterface $configManager,
    ) {
        parent::__construct($globalsContainer, $config);
    }

    /**
     * Display config home page.
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
        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        $redirectHelper = $this->getHelper(RedirectHelper::class);
        if (!($configFile = $this->pathResolver->getLocalConfigPath('config.ini'))) {
            $flashMessagesHelper
                ->addErrorMessage('Could not enable auto-configuration; local ' . $configFile . ' not found.');
            return $redirectHelper->redirectToRoute($response, 'admin/config');
        }
        $writer = new ConfigWriter($configFile);
        $writer->set('System', 'autoConfigure', 1);
        $success = false;
        try {
            $success = $writer->save();
        } catch (\Exception $e) {
            // Failure -- leave $success set to false.
        }
        if ($success) {
            $flashMessagesHelper->addSuccessMessage('Auto-configuration enabled.');

            // Reload config now that it has been edited (otherwise, old setting
            // will persist in cache):
            $this->configManager->getConfig('config', forceReload: true);
        } else {
            $flashMessagesHelper
                ->addErrorMessage('Could not enable auto-configuration; check permissions on ' . $configFile . '.');
        }
        return $redirectHelper->redirectToRoute($response, 'admin/config');
    }
}
