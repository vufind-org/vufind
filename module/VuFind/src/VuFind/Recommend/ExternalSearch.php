<?php

/**
 * ExternalSearch Recommendation Module.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Mark Triggs <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Recommend;

/**
 * ExternalSearch Recommendation Module.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ExternalSearch implements RecommendInterface
{
    /**
     * Link text.
     *
     * @var string
     */
    protected $linkText;

    /**
     * URL template string.
     *
     * @var string
     */
    protected $template;

    /**
     * Search query.
     *
     * @var string
     */
    protected $lookfor;

    /**
     * Name of query parameter containing search query.
     *
     * @var string
     */
    protected string $lookforParam = 'lookfor';

    /**
     * Store the configuration of the recommendation module.
     *
     * @param string $settingsStr Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settingsStr)
    {
        // Parse the settings:
        $settings = explode(':', $settingsStr);
        $this->linkText = array_shift($settings) ?? '';
        $this->template = array_shift($settings) ?? '';
        // Since URL template likely includes a colon because of ://, we need to reassemble accordingly:
        $next = array_shift($settings);
        if (str_starts_with($next ?? '', '//')) {
            $this->template .= ':' . $next;
            $next = array_shift($settings);
        }
        $this->lookforParam = $next ?? $this->lookforParam;
    }

    /**
     * Called before the Search Results object performs its main search
     * (specifically, in response to \VuFind\Search\SearchRunner::EVENT_CONFIGURED).
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function init($params, $request)
    {
        $this->lookfor = $request->get($this->lookforParam);
    }

    /**
     * Called after the Search Results object has performed its main search. This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        // no action needed
    }

    /**
     * Get the link text.
     *
     * @return string
     */
    public function getLinkText()
    {
        return $this->linkText;
    }

    /**
     * Get the link URL.
     *
     * @return string
     */
    public function getUrl()
    {
        return (!str_contains($this->template, '%%lookfor%%'))
            ? $this->template . urlencode($this->lookfor)
            : str_replace('%%lookfor%%', urlencode($this->lookfor), $this->template);
    }
}
