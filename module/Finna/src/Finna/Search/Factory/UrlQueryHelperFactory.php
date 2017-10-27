<?php
/**
 * Factory to build UrlQueryHelper.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2016.
 * Copyright (C) The National Library of Finland 2016.
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace Finna\Search\Factory;

use Finna\Search\UrlQueryHelper;
use VuFind\Search\Base\Params;

/**
 * Factory to build UrlQueryHelper.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class UrlQueryHelperFactory extends \VuFind\Search\Factory\UrlQueryHelperFactory
{
    /**
     * Extract default settings from the search parameters.
     *
     * @param Params $params Finna search parameters
     *
     * @return array
     */
    protected function getDefaults(Params $params)
    {
        $options = $params->getOptions();
        return [
            'handler' => $options->getDefaultHandler(),
            'limit' => is_callable([$options, 'getDefaultLimitByView'])
                ? $options->getDefaultLimitByView($params->getView())
                : $options->getDefaultLimit(),
            'selectedShards' => $options->getDefaultSelectedShards(),
            'sort' => $params->getDefaultSort(),
            'view' => $options->getDefaultView(),
        ];
    }

    /**
     * Construct the UrlQueryHelper
     *
     * @param Params $params VuFind search parameters
     * @param array  $config Config options
     *
     * @return UrlQueryHelper
     */
    public function fromParams(Params $params, array $config = [])
    {
        $finalConfig = $this->addDefaultsToConfig($params, $config);
        return new UrlQueryHelper(
            $this->getUrlParams($params, $finalConfig),
            $params->getQuery(),
            $finalConfig
        );
    }
}
