<?php

/**
 * Factory to build UrlQueryHelper.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Factory;

use VuFind\Search\Base\Params;
use VuFind\Search\UrlQueryHelper;

use function is_callable;

/**
 * Factory to build UrlQueryHelper.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class UrlQueryHelperFactory
{
    /**
     * Name of class built by factory.
     *
     * @var string
     */
    protected string $helperClass = UrlQueryHelper::class;

    /**
     * Extract default settings from the search parameters.
     *
     * @param Params $params VuFind search parameters
     *
     * @return array
     */
    protected function getDefaults(Params $params): array
    {
        $options = $params->getOptions();
        return [
            'handler' => fn () => $options->getDefaultHandler(),
            'limit' => fn () => $options->getDefaultLimit(),
            'selectedShards' => fn () => $options->getDefaultSelectedShards(),
            'sort' => fn () => $params->getDefaultSort(),
            'view' => fn () => $options->getDefaultView(),
        ];
    }

    /**
     * Load default settings into the user-provided configuration.
     *
     * @param Params $params VuFind search parameters
     * @param array  $config Config options
     *
     * @return array
     */
    protected function addDefaultsToConfig(Params $params, array $config): array
    {
        // Load defaults unless they have been overridden in existing config
        // array.
        foreach ($this->getDefaults($params) as $key => $value) {
            if (!isset($config['defaults'][$key])) {
                $config['defaults'][$key] = $value;
            }
        }

        // Load useful callbacks if they have not been specifically overridden
        if (!isset($config['parseFilterCallback'])) {
            $config['parseFilterCallback'] = [$params, 'parseFilter'];
        }
        if (!isset($config['getAliasesForFacetFieldCallback'])) {
            $config['getAliasesForFacetFieldCallback']
                = [$params, 'getAliasesForFacetField'];
        }
        return $config;
    }

    /**
     * Extract URL query parameters from VuFind search parameters.
     *
     * @param Params $params VuFind search parameters
     * @param array  $config Config options
     *
     * @return array
     */
    protected function getUrlParams(Params $params, array $config): array
    {
        $urlParams = [];
        $sort = $params->getSort();
        if (null !== $sort && $sort != $this->extractCallableValue($config['defaults']['sort'])) {
            $urlParams['sort'] = $sort;
        }
        $limit = $params->getSetLimit();
        if (null !== $limit && $limit != $this->extractCallableValue($config['defaults']['limit'])) {
            $urlParams['limit'] = $limit;
        }
        $view = $params->getView();
        if ($view && $view != $this->extractCallableValue($config['defaults']['view'])) {
            $urlParams['view'] = $view;
        }
        if ($params->getPage() != 1) {
            $urlParams['page'] = $params->getPage();
        }
        if ($filters = $params->getFiltersAsQueryParams()) {
            $urlParams['filter'] = $filters;
        }
        if ($hiddenFilters = $params->getHiddenFiltersAsQueryParams()) {
            $urlParams['hiddenFilters'] = $hiddenFilters;
        }
        $shards = $params->getSelectedShards();
        if (!empty($shards)) {
            sort($shards);
            $defaultShards = $this->extractCallableValue(['defaults']['selectedShards']);
            sort($defaultShards);
            if (implode(':::', $shards) != implode(':::', $defaultShards)) {
                $urlParams['shard'] = $shards;
            }
        }
        if ($params->hasDefaultsApplied()) {
            $urlParams['dfApplied'] = 1;
        }
        return $urlParams;
    }

    /**
     * Construct the UrlQueryHelper.
     *
     * @param Params $params VuFind search parameters
     * @param array  $config Config options
     *
     * @return UrlQueryHelper
     */
    public function fromParams(Params $params, array $config = []): UrlQueryHelper
    {
        $finalConfig = $this->addDefaultsToConfig($params, $config);
        return new $this->helperClass(
            $this->getUrlParams($params, $finalConfig),
            $params->getQuery(),
            $finalConfig
        );
    }

    /**
     * If value is callable call it and otherwise just return value.
     *
     * @param mixed $value Value
     *
     * @return mixed
     */
    protected function extractCallableValue(mixed $value): mixed
    {
        return is_callable($value) ? $value() : $value;
    }
}
