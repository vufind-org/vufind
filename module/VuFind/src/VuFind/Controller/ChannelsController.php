<?php
/**
 * Channels Controller
 *
 * PHP version 7
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

use VuFind\ChannelProvider\ChannelLoader;
use Zend\Config\Config;

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
class ChannelsController extends \Zend\Mvc\Controller\AbstractActionController
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
     * @param ChannelLoader $loader Channel loader
     */
    public function __construct(ChannelLoader $loader)
    {
        $this->loader = $loader;
    }

    /**
     * Generates static front page of channels.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
        return $this->createViewModel($this->loader->getHomeContext());
    }

    /**
     * Generates channels for a record.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function recordAction()
    {
        return $this->createViewModel($this->loader->getRecordContext());
    }

    /**
     * Generates channels for a search.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function searchAction()
    {
        return $this->createViewModel($this->loader->getSearchContext());
    }
}
