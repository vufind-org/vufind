<?php
/**
 * AuthorityRecommend Recommendations Module
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2012.
 * Copyright (C) The National Library of Finland 2019.
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
 * @package  Recommendations
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Recommend;

use Finna\Search\Solr\AuthorityHelper;
use Zend\StdLib\Parameters;

/**
 * AuthorityRecommend Module
 *
 * This class provides recommendations based on Authority records.
 * i.e. searches for a pseudonym will provide the user with a link
 * to the official name (according to the Authority index)
 *
 * Originally developed at the National Library of Ireland by Lutz
 * Biedinger and Ronan McHugh.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AuthorityRecommend extends \VuFind\Recommend\AuthorityRecommend
{
    /**
     * Authority ids
     *
     * @var array
     */
    protected $authorIds = null;

    /**
     * Authority roles
     *
     * @var array
     */
    protected $roles = null;

    /**
     * Authority helper
     *
     * @var \Finna\Search\Solr\AuthorityHelper
     */
    protected $authorityHelper = null;

    /**
     * Session
     *
     * @var \Zend\Session\Container
     */
    protected $session = null;

    /**
     * Cookie manager
     *
     * @var \VuFind\Cookie\CookieManager
     */
    protected $cookieManager = null;

    /**
     * Config
     *
     * @var \Zend\Config\Config
     */
    protected $config = null;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Results\PluginManager $results         Results
     * plugin manager
     * @param \Finna\Search\Solr\AuthorityHelper   $authorityHelper Authority helper
     * @param \Zend\Session\Container              $session         Session
     * @param \VuFind\Cookie\CookieManager         $cookieManager   Cookiemanager
     * @param \Zend\Config\Config                  $config          Configuration
     */
    public function __construct(
        \VuFind\Search\Results\PluginManager $results,
        \Finna\Search\Solr\AuthorityHelper $authorityHelper,
        \Zend\Session\Container $session,
        \VuFind\Cookie\CookieManager $cookieManager,
        \Zend\Config\Config $config
    ) {
        $this->resultsManager = $results;
        $this->authorityHelper = $authorityHelper;
        $this->session = $session;
        $this->cookieManager = $cookieManager;
        $this->config = $config;
    }

    /**
     * Get recommendations (for use in the view).
     *
     * @return array
     */
    public function getRecommendations()
    {
        if (!$this->authorIds) {
            return array_unique($this->recommendations, SORT_REGULAR);
        }

        // Make sure that authority records are sorted in the same order
        // as active filters
        $sorted = [];
        $rest = [];
        foreach ($this->recommendations as $r) {
            $pos = array_search($r->getUniqueID(), $this->authorIds);
            if (false === $pos) {
                $rest[] = $r;
            } else {
                $sorted[$pos] = $r;
            }
        }
        ksort($sorted);
        return array_merge($sorted, $rest);
    }

    /**
     * Get authority link type (authority page or search by authority-id).
     *
     * @param string $type Authority type
     *
     * @return string
     */
    public function getAuthorityLinkType($type = 'author')
    {
        return $this->authorityHelper->getAuthorityLinkType($type);
    }

    /**
     * Get active recommendation (authority id) from session.
     *
     * @return string|null
     */
    public function getActiveRecommendation()
    {
        return $this->session->activeId ?? null;
    }

    /**
     * Should authority info be rendered as collapsed?
     *
     * @return boolean
     */
    public function collapseAuthorityInfo()
    {
        return $this->cookieManager->get('collapseAuthorityInfo') === 'true';
    }

    /**
     * Called at the end of the Search Params objects' initFromRequest() method.
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Zend\StdLib\Parameters    $request Parameter object representing user
     * request.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function init($params, $request)
    {
        if ($ids = $params->getAuthorIdFilter()) {
            $this->authorIds = $ids;
            $this->lookfor = implode(
                ' OR ',
                array_map(
                    function ($id) {
                        return "(id:\"{$id}\")";
                    },
                    $ids
                )
            );
            $this->header = 'Author';

            // Detect if authority filters have been changed and switch active
            // authority recommendation tab accordingly.
            $idsWithRoles = $params->getAuthorIdFilter(true);
            if ($this->session->idsWithRoles
                && $this->session->idsWithRoles !== $idsWithRoles
            ) {
                $added = array_values(
                    array_diff($idsWithRoles, $this->session->idsWithRoles)
                );
                $removed = array_values(
                    array_diff($this->session->idsWithRoles, $idsWithRoles)
                );

                if ($added) {
                    // New authority filter added, activate it
                    list($activeId, $activeRole)
                        = $this->authorityHelper->extractRole($added[0]);
                    $this->session->activeId = $activeId;
                    $this->session->idsWithRoles[] = $added[0];
                } else {
                    // Active filter removed, reset session so that the last tab
                    // is rendered as active
                    $this->session->activeId;

                    if ($removed) {
                        $remaining = array_filter(
                            $removed,
                            function ($id) use ($removed) {
                                return $id !== $removed[0];
                            }
                        );
                        $this->session->idsWithRoles = $remaining;
                    }
                }
            } else {
                $this->session->idsWithRoles = $idsWithRoles;
            }
        } else {
            parent::init($params, $request);
        }
    }

    /**
     * Called after the Search Results object has performed its main search.  This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        // Override parent::process to allow advanced search

        $this->results = $results;

        // Empty searches such as New Items will return blank
        if ($this->lookfor == null) {
            return;
        }

        // Check result limit before proceeding...
        if ($this->resultLimit > 0
            && $this->resultLimit < $results->getResultTotal()
        ) {
            return;
        }

        // See if we can add main headings matching use_for/see_also fields...
        if ($this->isModeActive('usefor')) {
            $this->addUseForHeadings();
        }

        // See if we can add see-also references associated with main headings...
        if ($this->isModeActive('seealso')) {
            $this->addSeeAlsoReferences();
        }
    }

    /**
     * Return roles for author.
     *
     * @param string $id Author id
     *
     * @return array
     */
    public function getRoles($id)
    {
        try {
            $resultsOrig = $this->results;

            $results = clone $resultsOrig;
            $params = $results->getParams();
            $authorIdFilters = $params->getAuthorIdFilter(true);

            $params->addFacet(AuthorityHelper::AUTHOR_ID_ROLE_FACET);
            $paramsCopy = clone $params;

            $paramsCopy->addFacetFilter(
                AuthorityHelper::AUTHOR_ID_ROLE_FACET,
                $id,
                AuthorityHelper::AUTHOR_ID_ROLE_SEPARATOR
            );

            $results->setParams($paramsCopy);
            $results->performAndProcessSearch();
            $facets = $results->getFacetList();

            if (!isset($facets[AuthorityHelper::AUTHOR_ID_ROLE_FACET])) {
                return [];
            }

            $roles = $facets[AuthorityHelper::AUTHOR_ID_ROLE_FACET]['list'] ?? [];
            if ($this->authorityHelper) {
                foreach ($roles as &$role) {
                    $authorityInfo = $this->authorityHelper->formatFacet(
                        $role['displayText'], true
                    );
                    $role['displayText'] = $authorityInfo['displayText'];
                    $role['role'] = $authorityInfo['role'];
                    $role['enabled'] = in_array($role['value'], $authorIdFilters);
                }
            }
        } catch (RequestErrorException $e) {
            return [];
        }

        return $roles;
    }

    /**
     * Add main headings from records that match search terms on use_for/see_also.
     *
     * @return void
     */
    protected function addUseForHeadings()
    {
        $params = ['lookfor' => $this->lookfor, 'type' => 'MainHeading'];
        foreach ($this->performSearch($params) as $result) {
            $this->recommendations[] = $result;
        }
    }
}
