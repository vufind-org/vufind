<?php

/**
 * Solr highlighting listener.
 *
 * PHP version 7
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
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Solr;

use Laminas\EventManager\EventInterface;

use Laminas\EventManager\SharedEventManagerInterface;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Service;

/**
 * Solr highlighting listener.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class InjectHighlightingListener
{
    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Is highlighting active?
     *
     * @var bool
     */
    protected $active = false;

    /**
     * Fields to highlight when active.
     *
     * @var string
     */
    protected $fieldList;

    /**
     * Constructor.
     *
     * @param BackendInterface $backend   Backend
     * @param string           $fieldList Field(s) to highlight (hl.fl param)
     *
     * @return void
     */
    public function __construct(BackendInterface $backend, $fieldList = '*')
    {
        $this->backend = $backend;
        $this->fieldList = $fieldList;
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
            'VuFind\Search',
            Service::EVENT_PRE,
            [$this, 'onSearchPre']
        );
        $manager->attach(
            'VuFind\Search',
            Service::EVENT_POST,
            [$this, 'onSearchPost']
        );
    }

    /**
     * Set up highlighting parameters.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event)
    {
        $command = $event->getParam('command');
        if ($command->getContext() != 'search') {
            return $event;
        }
        if ($command->getTargetIdentifier() === $this->backend->getIdentifier()) {
            if ($params = $command->getSearchParameters()) {
                // Set highlighting parameters unless explicitly disabled:
                $hl = $params->get('hl');
                if (!isset($hl[0]) || $hl[0] != 'false') {
                    $this->active = true;
                    $params->set('hl', 'true');
                    $params->set('hl.simple.pre', '{{{{START_HILITE}}}}');
                    $params->set('hl.simple.post', '{{{{END_HILITE}}}}');

                    // Turn on hl.q generation in query builder:
                    $this->backend->getQueryBuilder()
                        ->setFieldsToHighlight($this->fieldList);
                }
            }
        }
        return $event;
    }

    /**
     * Inject highlighting results.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPost(EventInterface $event)
    {
        // Do nothing if highlighting is disabled or context is wrong
        $command = $event->getParam('command');
        if (!$this->active || $command->getContext() != 'search') {
            return $event;
        }

        // Inject highlighting details into record objects:
        if ($command->getTargetIdentifier() === $this->backend->getIdentifier()) {
            $result = $command->getResult();
            $hlDetails = $result->getHighlighting();
            foreach ($result->getRecords() as $record) {
                $id = $record->getUniqueId();
                if (isset($hlDetails[$id])) {
                    $record->setHighlightDetails($hlDetails[$id]);
                }
            }
        }
        return $event;
    }
}
