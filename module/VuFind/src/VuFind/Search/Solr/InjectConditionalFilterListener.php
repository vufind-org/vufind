<?php

/**
 * Conditional Filter listener.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search\Solr;

use Zend\EventManager\SharedEventManagerInterface;
use Zend\EventManager\EventInterface;

use ZfcRbac\Service\AuthorizationServiceAwareInterface,
    ZfcRbac\Service\AuthorizationServiceAwareTrait;

/**
 * Conditional Filter listener.
 *
 * @category VuFind2
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
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
     * @param array $searchConf Search configuration parameters
     *
     * @return void
     */
    public function __construct($searchConf)
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
        $manager->attach('VuFind\Search', 'pre', [$this, 'onSearchPre']);
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
        if (substr($filterCondition, 0, 1) == '-') {
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
        // Add conditional filters
        foreach ($this->filters as $fc) {
            $this->addConditionalFilter($fc);
        }

        $params = $event->getParam('params');
        $fq = $params->get('fq');
        if (!is_array($fq)) {
            $fq = [];
        }
        $new_fq = array_merge($fq, $this->filterList);
        $params->set('fq', $new_fq);

        return $event;
    }

}
