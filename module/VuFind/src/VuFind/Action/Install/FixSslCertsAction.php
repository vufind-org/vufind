<?php

/**
 * Install "fix SSL certificates" action.
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
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\RedirectHelper;

/**
 * Install "fix SSL certificates" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FixSslCertsAction extends AbstractInstallAction
{
    /**
     * Display repair instructions for SSL certificate problems.
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
        // Bail out if we've fixed the problem:
        $result = $this->testSslConnection();
        if ($result) {
            $this->getHelper(FlashMessagesHelper::class)->addInfoMessage('SSL configuration fixed.');
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'install-home');
        }

        // Find out which test to try next:
        $try = $this->getQueryParam('try', 0);

        // Configurations to test:
        $configsToTest = [
            ['sslcapath' => '/etc/ssl/certs'],
            ['sslcafile' => '/etc/pki/tls/cert.pem'],
            [], // reset configuration as last attempt
        ];
        if (isset($configsToTest[$try])) {
            $this->updateSslCertConfig($configsToTest[$try], $try);

            // Jump back to fix action so we can check if it worked (and attempt the next config by incrementing the
            // $try variable, if necessary):
            return $this->getHelper(RedirectHelper::class)->redirectToRoute(
                $response,
                'install-fixsslcerts',
                queryParams: ['try' => $try + 1]
            );
        }

        // If we got this far, we can't fix this automatically and must display a message.
        return $this->renderTemplate($request, $response);
    }

    /**
     * Switch to a specific SSL configuration.
     *
     * @param array $config Setting(s) to add to [Http] section of config.ini.
     * @param int   $try    Which config index are we trying right now?
     *
     * @return void
     */
    protected function updateSslCertConfig($config, $try): void
    {
        // Reset old settings
        $fixedConfig = [
            'Http' => [
                'sslcapath' => null,
                'sslcafile' => null,
            ],
        ];
        // Load new settings
        foreach ($config as $setting => $value) {
            $fixedConfig['Http'][$setting] = $value;
        }
        $this->changeConfig('config', $fixedConfig);
    }
}
