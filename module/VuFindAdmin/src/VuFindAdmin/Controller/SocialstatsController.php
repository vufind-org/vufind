<?php
/**
 * Admin Social Statistics Controller
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFindAdmin\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\Db\Service\TagService;

/**
 * Class controls VuFind social statistical data.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SocialstatsController extends AbstractAdmin
{
    /**
     * Tag service
     *
     * @var TagService
     */
    protected $tagService;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        parent::__construct($sm);
        $this->tagService = $sm->get(\VuFind\Db\Service\PluginManager::class)
            ->get(TagService::class);
    }

    /**
     * Social statistics reporting
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function homeAction()
    {
        $view = $this->createViewModel();
        $view->setTemplate('admin/socialstats/home');
        $view->comments = $this->getTable('comments')->getStatistics();
        $view->ratings = $this->getTable('ratings')->getStatistics();
        $view->favorites = $this->getTable('userresource')->getStatistics();
        $view->tags = $this->tagService->getStatistics();
        return $view;
    }
}
