<?php

/**
 * Install "fix basic configuration" action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010, 2022.
 * Copyright (C) The National Library of Finland 2026.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
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
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Action\Install;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\PathResolver;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\TagServiceInterface;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\ILS\Connection;
use VuFind\ServiceManager\Factory\Autowire;
use VuFindHttp\HttpService;
use VuFindSearch\Service as SearchService;

use function dirname;

/**
 * Install "fix basic configuration" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FixBasicConfigAction extends AbstractInstallAction
{
    /**
     * Constructor.
     *
     * @param PathResolver             $pathResolver    Path resolver
     * @param ConfigManagerInterface   $configManager   Config manager
     * @param UserServiceInterface     $userService     User database service
     * @param UserCardServiceInterface $userCardService User card database service
     * @param array                    $config          VuFind configuration
     * @param Connection               $ilsConnection   ILS connection
     * @param SearchService            $searchService   Search service
     * @param HttpService              $httpService     HTTP service
     * @param TagServiceInterface      $tagService      Tags database service
     * @param string                   $absoluteBaseUrl Absolute base URL
     */
    public function __construct(
        PathResolver $pathResolver,
        ConfigManagerInterface $configManager,
        #[Autowire(container: DbServicePluginManager::class)]
        UserServiceInterface $userService,
        #[Autowire(container: DbServicePluginManager::class)]
        UserCardServiceInterface $userCardService,
        #[Autowire(config: 'config')]
        array $config,
        Connection $ilsConnection,
        SearchService $searchService,
        HttpService $httpService,
        #[Autowire(container: DbServicePluginManager::class)]
        TagServiceInterface $tagService,
        #[Autowire(container: \VuFind\Application\UriRegistry::class, service: 'AbsoluteBaseUrl')]
        protected string $absoluteBaseUrl,
    ) {
        parent::__construct(
            $pathResolver,
            $configManager,
            $userService,
            $userCardService,
            $config,
            $ilsConnection,
            $searchService,
            $httpService,
            $tagService
        );
    }

    /**
     * Fix basic configuration.
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
        $templateParams = [];
        try {
            if (!$this->installBasicConfig()) {
                throw new \Exception('Cannot copy file into position.');
            }
            // Choose secure defaults when creating initial config.ini:
            $fixedConfig = $this->getFixedSecurityConfiguration($this->config);
            // Set appropriate URLs:
            $fixedConfig['Site']['url'] = rtrim($this->absoluteBaseUrl, '/');
            if ($solrUrl = $this->getSolrUrlFromImportConfig()) {
                $fixedConfig['Index']['url'] = $solrUrl;
            }
            $this->changeConfig('config', $fixedConfig);
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'install-home');
        } catch (\Exception $e) {
            $templateParams['configDir'] = dirname($this->getForcedLocalConfigPath('config'));
            $templateParams['errorMessage'] = $e->getMessage();
            $templateParams['runningUser'] = $this->getProcessUserName();
        }
        return $this->renderTemplate($request, $response, $templateParams);
    }

    /**
     * Extract the Solr base URL from the SolrMarc configuration file,
     * so a custom Solr port configured in install.php can be applied to
     * the initial config.ini file.
     *
     * Return null if no custom Solr URL can be found.
     *
     * @return ?string
     */
    protected function getSolrUrlFromImportConfig(): ?string
    {
        $importConfig = $this->pathResolver->getLocalConfigPath('import.properties', 'import');
        if (file_exists($importConfig)) {
            $props = file_get_contents($importConfig);
            preg_match('|solr.hosturl\s*=\s*(https?://\w+:\d+/\w+)|', $props, $matches);
            if (!empty($matches[1])) {
                return $matches[1];
            }
        }
        return null;
    }
}
