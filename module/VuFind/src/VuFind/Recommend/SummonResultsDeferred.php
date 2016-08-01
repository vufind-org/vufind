<?php
/**
 * SummonResultsDeferred Recommendations Module
 *
 * PHP version 5
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
 * @package  Recommendations
 * @author   Lutz Biedinger <lutz.biedinger@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
namespace VuFind\Recommend;

/**
 * SummonResultsDeferred Recommendations Module
 *
 * This class sets up an AJAX call to trigger a call to the SummonResults
 * module.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Lutz Biedinger <lutz.biedigner@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class SummonResultsDeferred extends AbstractSummonRecommendDeferred
{
    /**
     * Label for current search type
     *
     * @var string
     */
    protected $typeLabel = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->module = 'SummonResults';
        $this->paramCount = 2;
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
     */
    public function init($params, $request)
    {
        parent::init($params, $request);

        // Collect the label for the current search type:
        if (is_object($params)) {
            $this->typeLabel = $params->getOptions()->getLabelForBasicHandler(
                $params->getSearchHandler()
            );
        }
    }

    /**
     * Get the URL parameters needed to make the AJAX recommendation request.
     *
     * @return string
     */
    public function getUrlParams()
    {
        return parent::getUrlParams() . '&typeLabel=' . urlencode($this->typeLabel);
    }
}
