<?php

/**
 * Install home action.
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
use VuFindSearch\Command\RetrieveCommand;

use function defined;
use function function_exists;
use function in_array;
use function is_callable;

/**
 * Install home action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class HomeAction extends AbstractInstallAction
{
    /**
     * Display summary of installation status.
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
        // Perform all checks (based on naming convention):
        $methods = get_class_methods($this);
        $checks = [];
        foreach ($methods as $method) {
            if (str_starts_with($method, 'checkMethod')) {
                $checks[] = $this->$method();
            }
        }
        return $this->renderTemplate($request, $response, compact('checks'));
    }

    /**
     * Check if basic configuration is taken care of.
     *
     * @return array
     */
    protected function checkMethodBasicConfig(): array
    {
        // Initialize status based on existence of config file...
        $status = $this->installBasicConfig();

        // See if the URL setting remains at the default (unless we already
        // know we've failed):
        if ($status) {
            if (stristr($this->config['Site']['url'], 'myuniversity.edu')) {
                $status = false;
            }
        }

        return [
            'title' => 'Basic Configuration',
            'status' => $status,
            'fix' => 'fixbasicconfig',
        ];
    }

    /**
     * Check if the cache directory is writable.
     *
     * @return array
     */
    protected function checkMethodCache(): array
    {
        return [
            'title' => 'Cache',
            'status' => !$this->cacheManager->hasDirectoryCreationError(),
            'fix' => 'fixcache',
        ];
    }

    /**
     * Check if the database is accessible.
     *
     * @return array
     */
    protected function checkMethodDatabase(): array
    {
        try {
            // Try to read the tags table just to see if we can connect to the DB:
            $this->tagService->getTagsByText('test');
            $status = true;
        } catch (\Exception $e) {
            $status = false;
        }
        return [
            'title' => 'Database',
            'status' => $status,
            'fix' => 'fixdatabase',
        ];
    }

    /**
     * Check for missing dependencies.
     *
     * @return array
     */
    protected function checkMethodDependencies(): array
    {
        $requiredFunctionsExist
            = function_exists('mb_substr') && is_callable('imagecreatefromstring')
              && function_exists('openssl_encrypt')
              && class_exists('XSLTProcessor')
              && defined('SODIUM_LIBRARY_VERSION');

        return [
            'title' => 'Dependencies',
            'status' => $requiredFunctionsExist && $this->phpVersionIsNewEnough(),
            'fix' => 'fixdependencies',
        ];
    }

    /**
     * Check if ILS configuration is appropriate.
     *
     * @return array
     */
    protected function checkMethodILS(): array
    {
        $driver = $this->config['Catalog']['driver'] ?? '';
        if (in_array($driver, ['Sample', 'Demo'])) {
            $status = false;
        } else {
            try {
                $status = 'ils-offline' !== $this->ilsConnection->getOfflineMode(true) || 'NoILS' === $driver;
            } catch (\Exception $e) {
                $status = false;
            }
        }
        return [
            'title' => 'ILS',
            'status' => $status,
            'fix' => 'fixils',
        ];
    }

    /**
     * Support method to test the search service.
     *
     * @return void
     * @throws \Exception
     */
    protected function testSearchService(): void
    {
        // Try to retrieve an arbitrary ID -- this will fail if Solr is down:
        $command = new RetrieveCommand('Solr', '1');
        $this->searchService->invoke($command)->getResult();
    }

    /**
     * Check if the Solr index is working.
     *
     * @return array
     */
    protected function checkMethodSolr(): array
    {
        try {
            $this->testSearchService();
            $status = true;
        } catch (\Exception $e) {
            $status = false;
        }
        return [
            'title' => 'Solr',
            'status' => $status,
            'fix' => 'fixsolr',
        ];
    }

    /**
     * Check if Security configuration is set.
     *
     * @return array
     */
    protected function checkMethodSecurity(): array
    {
        try {
            $secureDb = $this->hasSecureDatabase();
        } catch (\Throwable $e) {
            $secureDb = false;
        }
        return [
            'title' => 'Security',
            'status' => $secureDb,
            'fix' => 'fixsecurity',
        ];
    }

    /**
     * Check if SSL configuration is set properly.
     *
     * @return array
     */
    public function checkMethodSslCerts(): array
    {
        return [
            'title' => 'SSL',
            'status' => $this->testSslConnection(),
            'fix' => 'fixsslcerts',
        ];
    }
}
