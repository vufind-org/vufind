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

use function dirname;
use function function_exists;

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
            $path = $this->routeHelper->getUrlFromRoute('home');
            $fixedConfig['Site']['url'] = rtrim($this->serverUrlHelper->getUrlForPath($path), '/');
            if ($solrUrl = $this->getSolrUrlFromImportConfig()) {
                $fixedConfig['Index']['url'] = $solrUrl;
            }
            $this->changeConfig('config', $fixedConfig);
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'install-home');
        } catch (\Exception $e) {
            $templateParams['configDir'] = dirname($this->getForcedLocalConfigPath('config'));
            $templateParams['errorMessage'] = $e->getMessage();
            if (
                function_exists('posix_getpwuid')
                && function_exists('posix_geteuid')
            ) {
                $processUser = posix_getpwuid(posix_geteuid());
                $templateParams['runningUser'] = $processUser['name'];
            }
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
