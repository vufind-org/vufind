<?php
/**
 * Facet-driven channel provider.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\ChannelProvider;
use VuFind\Cover\Router as CoverRouter;

/**
 * Facet-driven channel provider.
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
                'id' => $current->getUniqueId(),
            ];
        }
        return $summary;
    }
}
