<?php

/**
 * Install "fix Solr" action.
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
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\RedirectHelper;

/**
 * Install "fix Solr" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FixSolrAction extends AbstractInstallAction
{
    /**
     * Display repair instructions for Solr problems.
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
        // In Windows, localhost may fail -- see if switching to 127.0.0.1 helps:
        $indexUrl = $this->config['Index']['url'] ?? '';
        if (stristr($indexUrl, 'localhost')) {
            $newUrl = str_replace('localhost', '127.0.0.1', $indexUrl);
            try {
                $this->testSearchService();
                try {
                    $this->changeConfig(
                        'config',
                        ['Index' => ['url' => $newUrl]]
                    );
                } catch (\Exception $e) {
                    return $this->getHelper(ForwardHelper::class)
                        ->forwardTo($request, $response, 'Install/fixbasicconfig');
                }
                return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'install-home');
            } catch (\Exception $e) {
                // Didn't work!
            }
        }

        // If we got this far, the automatic fix didn't work, so let's just assign some variables to use in offering
        // troubleshooting advice:
        $templateParams = [
            'rawUrl' => $indexUrl,
            'userUrl' => str_replace(
                ['localhost', '127.0.0.1'],
                $request->getServerParams()['HTTP_HOST'] ?? '',
                $indexUrl
            ),
            'core' => $this->config['Index']['default_core'] ?? 'biblio',
            'configFile' => $this->getForcedLocalConfigPath('config'),
        ];
        return $this->renderTemplate($request, $response, $templateParams);
    }
}
