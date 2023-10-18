<?php

/**
 * Channels Controller
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:alphabetical_heading_browse Wiki
 */

namespace VuFind\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\ChannelProvider\ChannelLoader;

/**
 * Channels Class
 *
 * Controls the alphabetical browsing feature
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:alphabetical_heading_browse Wiki
 */
class ChannelsController extends AbstractBase
{
    /**
     * Channel loader
     *
     * @var ChannelLoader
     */
    protected $loader;

    /**
     * Constructor
     *
     * @param ChannelLoader           $loader Channel loader
     * @param ServiceLocatorInterface $sm     Top-level service manager (needed for
     * some AbstractBase behavior)
     */
    public function __construct(ChannelLoader $loader, ServiceLocatorInterface $sm)
    {
        $this->loader = $loader;
        parent::__construct($sm);
    }

    /**
     * Generates static front page of channels.
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function homeAction()
    {
        $source = $this->params()->fromQuery('source', DEFAULT_SEARCH_BACKEND);
        $activeChannel = $this->params()->fromQuery('channelProvider');
        $token = $this->params()->fromQuery('channelToken');
        $context = $this->loader->getHomeContext($token, $activeChannel, $source);
        return $this->createViewModel($context);
    }

    /**
     * Generates channels for a record.
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function recordAction()
    {
        $recordId = $this->params()->fromQuery('id');
        $source = $this->params()->fromQuery('source', DEFAULT_SEARCH_BACKEND);
        $activeChannel = $this->params()->fromQuery('channelProvider');
        $token = $this->params()->fromQuery('channelToken');
        $context = $this->loader
            ->getRecordContext($recordId, $token, $activeChannel, $source);
        return $this->createViewModel($context);
    }

    /**
     * Generates channels for a search.
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function searchAction()
    {
        // Send both GET and POST variables to search class:
        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();
        $source = $this->params()->fromQuery('source', DEFAULT_SEARCH_BACKEND);
        $activeChannel = $this->params()->fromQuery('channelProvider');
        $token = $this->params()->fromQuery('channelToken');
        $context = $this->loader
            ->getSearchContext($request, $token, $activeChannel, $source);
        return $this->createViewModel($context);
    }
}
