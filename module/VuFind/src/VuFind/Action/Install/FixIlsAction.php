<?php

/**
 * Install "fix ILS" action.
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

use function in_array;

/**
 * Install "fix ILS" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FixIlsAction extends AbstractInstallAction
{
    /**
     * Display repair instructions for ILS problems.
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
        // Process incoming parameter -- user may have selected a new driver:
        $newDriver = $this->getPostParam('driver');
        if (!empty($newDriver)) {
            try {
                $this->changeConfig(
                    'config',
                    ['Catalog' => ['driver' => $newDriver]]
                );
            } catch (\Exception $e) {
                return $this->getHelper(ForwardHelper::class)->forwardTo($request, $response, 'Install/FixBasicConfig');
            }
            // Copy configuration, if applicable:
            $ilsIni = $this->getBaseConfigFilePath($newDriver);
            $localIlsIni = $this->getForcedLocalConfigPath($newDriver);
            if (file_exists($ilsIni) && !file_exists($localIlsIni)) {
                if (!copy($ilsIni, $localIlsIni)) {
                    return $this->getHelper(ForwardHelper::class)
                        ->forwardTo($request, $response, 'Install/FixBasicConfig');
                }
            }
            return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'install-home');
        }

        // If we got this far, check whether we have an error with a real driver or if we need to warn the user that
        // they have selected a fake driver:
        $templateParams = [];
        $currentDriver = $this->config['Catalog']['driver'] ?? '';
        if (in_array($currentDriver, ['Sample', 'Demo'])) {
            $templateParams['demo'] = true;
            // Get a list of available drivers:
            $dir = opendir(APPLICATION_PATH . '/module/VuFind/src/VuFind/ILS/Driver');
            $drivers = [];
            $excludeList = [
                'Sample.php', 'Demo.php', 'DriverInterface.php', 'PluginManager.php',
            ];
            while ($line = readdir($dir)) {
                if (
                    stristr($line, '.php') && !in_array($line, $excludeList)
                    && !str_starts_with($line, 'Abstract')
                    && !str_ends_with($line, 'Factory.php')
                    && !str_ends_with($line, 'Trait.php')
                ) {
                    $drivers[] = str_replace('.php', '', $line);
                }
            }
            closedir($dir);
            sort($drivers);
            $templateParams['drivers'] = $drivers;
        } else {
            $templateParams['configPath'] = $this->getForcedLocalConfigPath($currentDriver);
        }
        return $this->renderTemplate($request, $response, $templateParams);
    }
}
