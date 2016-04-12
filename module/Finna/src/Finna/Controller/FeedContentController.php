<?php
/**
 * Feed Content Controller
 *
 * PHP Version 5
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2014-2016.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Controller;

/**
 * Loads feed content pages
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FeedContentController extends ContentController
{
    /**
     * Default action if none provided
     *
     * @return Zend\View\Model\ViewModel
     */
    public function contentAction()
    {
        $event = $this->getEvent();
        $routeMatch = $event->getRouteMatch();
        $page = strtolower($routeMatch->getParam('page'));
        $element = $routeMatch->getParam('element');
        $rssConfig = $this->getServiceLocator()->get('VuFind\Config')->get('rss');

        if (!isset($rssConfig[$page])) {
            return $this->notFoundAction($this->getResponse());
        }

        $config = $rssConfig[$page];
        $modal = isset($config->linkTo) && $config->linkTo == 'modal';
        
        return $this->createViewModel(
            ['page' => 'feed-content', 'feed' => $page,
            'element' => $element, 'modal' => $modal]
        );
    }
}
