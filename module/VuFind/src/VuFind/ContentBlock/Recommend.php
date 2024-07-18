<?php

/**
 * Recommend ContentBlock.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  VuFind\ContentBlock
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ContentBlock;

use Laminas\Stdlib\Parameters;
use VuFind\Recommend\PluginManager as RecommendPluginManager;
use VuFind\Recommend\RecommendInterface;
use VuFind\Search\Params\PluginManager as ParamsPluginManager;

/**
 * Recommend ContentBlock.
 *
 * @category VuFind
 * @package  VuFind\ContentBlock
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Recommend implements ContentBlockInterface
{
    /**
     * Recommendation module.
     *
     * @var RecommendInterface
     */
    protected $recommend;

    /**
     * Constructor.
     *
     * @param ParamsPluginManager    $paramsManager    Search params plugin manager
     * @param RecommendPluginManager $recommendManager Recommendation plugin manager
     * @param Parameters             $request          Query parameters from request
     */
    public function __construct(
        protected ParamsPluginManager $paramsManager,
        protected RecommendPluginManager $recommendManager,
        protected Parameters $request
    ) {
    }

    /**
     * Store the configuration of the content block.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        $parts = explode(':', $settings, 3);
        $backend = array_shift($parts);
        $module = array_shift($parts);
        $this->recommend = $this->recommendManager->get($module);
        $this->recommend->setConfig($parts[0] ?? '');
        $params = $this->paramsManager->get($backend ?: DEFAULT_SEARCH_BACKEND);
        $this->recommend->init($params, $this->request);
    }

    /**
     * Return context variables used for rendering the block's template.
     *
     * @return array
     */
    public function getContext()
    {
        return ['recommend' => $this->recommend];
    }
}
