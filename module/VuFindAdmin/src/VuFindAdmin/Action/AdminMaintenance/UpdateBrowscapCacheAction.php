<?php

/**
 * Update Browscap cache action.
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

use Laminas\Cache\Psr\SimpleCache\SimpleCacheDecorator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Http\GuzzleServiceAwareInterface;
use VuFind\Http\GuzzleServiceAwareTrait;
use VuFind\Log\LoggerAwareTrait;

use function ini_get;

/**
 * Update Browscap cache action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class UpdateBrowscapCacheAction extends AbstractMaintenanceAction implements
    LoggerAwareInterface,
    GuzzleServiceAwareInterface
{
    use LoggerAwareTrait;
    use GuzzleServiceAwareTrait;

    /**
     * Display maintenance home page.
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
        if (ini_get('max_execution_time') < 3600) {
            ini_set('max_execution_time', '3600');
        }
        $this->updateBrowscapCache();
        return $this->getHelper(RedirectHelper::class)->redirectToRoute($response, 'admin/maintenance');
    }

    /**
     * Update browscap cache.
     *
     * Note that there's also similar functionality in BrowscapCommand CLI utility.
     *
     * @return void
     */
    protected function updateBrowscapCache(): void
    {
        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        ini_set('memory_limit', '1024M');
        $type = match ($this->getQueryParam('cacheType', 'standard')) {
            'full' => \BrowscapPHP\Helper\IniLoaderInterface::PHP_INI_FULL,
            'lite' => \BrowscapPHP\Helper\IniLoaderInterface::PHP_INI_LITE,
            'standard' =>  \BrowscapPHP\Helper\IniLoaderInterface::PHP_INI,
            default => null,
        };
        if (!$type) {
                $flashMessagesHelper->addErrorMessage('Invalid browscap file-type specified');
                return;
        }

        $cache = new SimpleCacheDecorator($this->cacheManager->getCache('browscap'));
        $client = $this->guzzleService->createGuzzleClient();

        $bc = new \BrowscapPHP\BrowscapUpdater($cache, $this->logger, $client);
        try {
            $bc->checkUpdate();
        } catch (\BrowscapPHP\Exception\NoNewVersionException $e) {
            $flashMessagesHelper
                ->addSuccessMessage('No newer browscap version available. Clear the cache to force update.');
            return;
        } catch (\BrowscapPHP\Exception\FetcherException $e) {
            $flashMessagesHelper->addErrorMessage($e->getMessage());
            $this->logException($e);
            return;
        } catch (\BrowscapPHP\Exception\NoCachedVersionException $e) {
            // Fall through...
        } catch (\Exception $e) {
            // Output the exception and continue (assume we don't have a current version):
            $flashMessagesHelper->addWarningMessage($e->getMessage());
            $this->logWarning((string)$e);
        }
        try {
            $bc->update($type);
            $this->logger->info('Browscap cache updated');
            $flashMessagesHelper->addSuccessMessage('Browscap cache successfully updated.');
        } catch (\Exception $e) {
            $flashMessagesHelper->addErrorMessage($e->getMessage());
            $this->logWarning((string)$e);
        }
    }
}
