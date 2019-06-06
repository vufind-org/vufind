<?php
/**
 * Record tab plugin manager
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
namespace VuFind\RecordTab;

use Zend\ServiceManager\Factory\InvokableFactory;

/**
 * Record tab plugin manager
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'collectionhierarchytree' => CollectionHierarchyTree::class,
        'collectionlist' => CollectionList::class,
        'description' => Description::class,
        'excerpt' => Excerpt::class,
        'hierarchytree' => HierarchyTree::class,
        'holdingsils' => HoldingsILS::class,
        'holdingsworldcat' => HoldingsWorldCat::class,
        'map' => Map::class,
        'preview' => Preview::class,
        'reviews' => Reviews::class,
        'similaritemscarousel' => SimilarItemsCarousel::class,
        'staffviewarray' => StaffViewArray::class,
        'staffviewmarc' => StaffViewMARC::class,
        'toc' => TOC::class,
        'usercomments' => UserComments::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        CollectionHierarchyTree::class => CollectionHierarchyTreeFactory::class,
        CollectionList::class => CollectionListFactory::class,
        Description::class => InvokableFactory::class,
        Excerpt::class => ExcerptFactory::class,
        HierarchyTree::class => HierarchyTreeFactory::class,
        HoldingsILS::class => HoldingsILSFactory::class,
        HoldingsWorldCat::class => HoldingsWorldCatFactory::class,
        Map::class => MapFactory::class,
        Preview::class => PreviewFactory::class,
        Reviews::class => ReviewsFactory::class,
        SimilarItemsCarousel::class => SimilarItemsCarouselFactory::class,
        StaffViewArray::class => InvokableFactory::class,
        StaffViewMARC::class => InvokableFactory::class,
        TOC::class => TOCFactory::class,
        UserComments::class => UserCommentsFactory::class,
    ];

    /**
     * Constructor
     *
     * Make sure plugins are properly initialized.
     *
     * @param mixed $configOrContainerInstance Configuration or container instance
     * @param array $v3config                  If $configOrContainerInstance is a
     * container, this value will be passed to the parent constructor.
     */
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->addAbstractFactory(PluginFactory::class);
        $this->addInitializer('ZfcRbac\Initializer\AuthorizationServiceInitializer');
        parent::__construct($configOrContainerInstance, $v3config);
    }

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return TabInterface::class;
    }
}
