<?php

/**
 * Conditional Filter listener.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2013.
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
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Solr;

use Laminas\EventManager\EventInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use LmcRbacMvc\Service\AuthorizationServiceAwareTrait;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Service;

use function is_array;

/**
 * Conditional Filter listener.
 *
 * @category VuFind
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class InjectConditionalFilterListener
{
    use AuthorizationServiceAwareTrait;

    /**
     * Filters to apply.
     *
     * @var array
     */
    protected $filterList;

    /**
     * Filters from configuration.
     *
     * @var array
     */
    protected $filters;

    /**
     * Constructor.
     *
     * @param BackendInterface $backend    Backend
     * @param array            $searchConf Search configuration parameters
     *
     * @return void
     */
    public function __construct(protected BackendInterface $backend, $searchConf)
    {
        $this->filters = $searchConf;
        $this->filterList = [];
    }

    /**
     * Attach listener to shared event manager.
     *
     * @param SharedEventManagerInterface $manager Shared event manager
     *
     * @return void
     */
    public function attach(SharedEventManagerInterface $manager)
    {
        $manager->attach(
            Service::class,
            Service::EVENT_PRE,
            [$this, 'onSearchPre']
        );
    }

    /**
     * Add a conditional filter.
     *
     * @param String $configOption Conditional Filter
     *
     * @return void
     */
    protected function addConditionalFilter($configOption)
    {
        $filterArr = explode('|', $configOption);
        $filterCondition = $filterArr[0];
        $filter = $filterArr[1];
        $authService = $this->getAuthorizationService();

        // if no authorization service is available, don't do anything
        if (!$authService) {
            return;
        }

        // if the filter condition starts with a minus (-), it should not match
        // to get the filter applied
        if (str_starts_with($filterCondition, '-')) {
            if (!$authService->isGranted(substr($filterCondition, 1))) {
                $this->filterList[] = $filter;
            }
        } else {
            // otherwise the condition should match to apply the filter
            if ($authService->isGranted($filterCondition)) {
                $this->filterList[] = $filter;
            }
        }
    }

    /**
     * Set up conditional hidden filters.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event)
    {
        $command = $event->getParam('command');
        if ($command->getTargetIdentifier() !== $this->backend->getIdentifier()) {
            return $event;
        }

        // Add conditional filters
        foreach ($this->filters as $fc) {
            $this->addConditionalFilter($fc);
        }

        $params = $command->getSearchParameters();
        $fq = $params->get('fq');
        if (!is_array($fq)) {
            $fq = [];
        }
        $new_fq = array_merge($fq, $this->filterList);
        $params->set('fq', $new_fq);

        return $event;
    }
}
