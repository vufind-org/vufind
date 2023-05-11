<?php

/**
 * Abstract base class for channel providers.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ChannelProvider;

use VuFind\Cover\Router as CoverRouter;
use VuFind\Record\Router as RecordRouter;
use VuFind\Search\Base\Params;

/**
 * Abstract base class for channel providers.
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractChannelProvider implements ChannelProviderInterface
{
    /**
     * Cover router
     *
     * @var CoverRouter
     */
    protected $coverRouter = null;

    /**
     * Provider ID
     *
     * @var string
     */
    protected $providerId = '';

    /**
     * Record router
     *
     * @var RecordRouter
     */
    protected $recordRouter = null;

    /**
     * Hook to configure search parameters before executing search.
     *
     * @param Params $params Search parameters to adjust
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function configureSearchParams(Params $params)
    {
        // No action necessary by default.
    }

    /**
     * Inject cover router
     *
     * @param CoverRouter $coverRouter Cover router.
     *
     * @return void
     */
    public function setCoverRouter(CoverRouter $coverRouter)
    {
        $this->coverRouter = $coverRouter;
    }

    /**
     * Inject record router
     *
     * @param RecordRouter $recordRouter Record router.
     *
     * @return void
     */
    public function setRecordRouter(RecordRouter $recordRouter)
    {
        $this->recordRouter = $recordRouter;
    }

    /**
     * Set an identifier that will be injected as the 'providerId' key of all
     * channels created by this provider.
     *
     * @param string $id Provider ID
     *
     * @return void
     */
    public function setProviderId($id)
    {
        $this->providerId = $id;
    }

    /**
     * Set the options for the provider.
     *
     * @param array $options Options
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function setOptions(array $options)
    {
        // No options required by default
    }

    /**
     * Convert a search results object into channel contents.
     *
     * @param array|\Traversable $drivers Record drivers to summarize.
     *
     * @return array
     */
    protected function summarizeRecordDrivers($drivers)
    {
        $summary = [];
        foreach ($drivers as $current) {
            $summary[] = [
                'title' => $current->getTitle(),
                'source' => $current->getSourceIdentifier(),
                'thumbnail' => $this->coverRouter
                    ? $this->coverRouter->getUrl($current, 'medium')
                    : false,
                'routeDetails' => $this->recordRouter
                    ? $this->recordRouter->getTabRouteDetails($current)
                    : false,
                'id' => $current->getUniqueId(),
            ];
        }
        return $summary;
    }
}
