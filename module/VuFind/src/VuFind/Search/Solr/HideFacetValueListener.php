<?php
/** Hide single facets values from displaying */

namespace VuFind\Search\Solr;

use VuFindSearch\Backend\BackendInterface;

use Zend\EventManager\SharedEventManagerInterface;
use Zend\EventManager\EventInterface;
use Zend\ServiceManager\ServiceLocatorInterface;


/**
 * Hide single facet values from displaying.
 *
 * @category VuFind2
 * @package  Search
 * @author   Frank Morgner <morgnerf@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

class HideFacetValueListener {

    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Superior service manager.
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * Facet configuration.
     *
     * @var Config
     */
    protected $facetConfig;

    /**
     * Constructor.
     *
     * @param BackendInterface        $backend        Search backend
     * @param ServiceLocatorInterface $serviceLocator Service locator
     * @param string                  $facetConfig    Facet config file id
     *
     * @return void
     */
    public function __construct(
        BackendInterface $backend,
        ServiceLocatorInterface $serviceLocator,
        $facetConfig
    )
    {
        $this->backend = $backend;
        $this->serviceLocator = $serviceLocator;

        $config = $this->serviceLocator->get('VuFind\Config');
        $this->facetConfig = $config->get($facetConfig);
    }

    /**
     * Attach listener to shared event manager.
     *
     * @param SharedEventManagerInterface $manager Shared event manager
     *
     * @return void
     */
    public function attach(
        SharedEventManagerInterface $manager
    ) {
        $manager->attach('VuFind\Search', 'post', [$this, 'onSearchPost']);
    }

    /**
     * Hide facet values from display
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPost(EventInterface $event)
    {
        $backend = $event->getParam('backend');

        if ($backend != $this->backend->getIdentifier()) {
            return $event;
        }
        $context = $event->getParam('context');
        if ($context == 'search' || $context == 'retrieve') {
            $this->processHideFacetValue($event);
        }
        return $event;
    }

    /**
     * Process hide facet value
     *
     * @param EventInterface $event Event
     *
     * @return void
    */
    protected function processHideFacetValue($event)
    {

        if (!isset($this->facetConfig->HideFacetValue)
            || ($this->facetConfig->HideFacetValue->count()) == 0) {
            return null;
        }

        $result = $event->getTarget();
        $facets = $result->getFacets()->getFieldFacets();

        foreach ($this->facetConfig->HideFacetValue->toArray() as $facet => $value) {
            if (isset($facets[$facet])) {
                foreach ($value as $config_value) {
                    foreach ($facets[$facet] as $facet_value => $count) {
                        if ($facet_value == $config_value) {
                            $facets[$facet]->remove();
                        }
                    }
                }
            }
        }
        return null;
    }


}