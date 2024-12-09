<?php

/**
 * SOLR SimilarBuilder.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2016.
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
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\Solr;

use VuFindSearch\ParamBag;

use function sprintf;

/**
 * SOLR SimilarBuilder.
 *
 * @category VuFind
 * @package  Search
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class SimilarBuilder implements SimilarBuilderInterface
{
    /**
     * Solr field used to store unique identifier
     *
     * @var string
     */
    protected $uniqueKey;

    /**
     * Whether to use MoreLikeThis Handler instead of the traditional MoreLikeThis
     * component.
     *
     * @var bool
     */
    protected $useHandler = false;

    /**
     * MoreLikeThis Handler parameters
     *
     * @var string
     */
    protected $handlerParams = '';

    /**
     * Number of similar records to retrieve
     *
     * @var int
     */
    protected $count = 5;

    /**
     * Constructor.
     *
     * @param \Laminas\Config\Config $searchConfig Search config
     * @param string                 $uniqueKey    Solr field used to store unique
     * identifier
     *
     * @return void
     */
    public function __construct(
        \Laminas\Config\Config $searchConfig = null,
        $uniqueKey = 'id'
    ) {
        $this->uniqueKey = $uniqueKey;
        if (isset($searchConfig->MoreLikeThis)) {
            $mlt = $searchConfig->MoreLikeThis;
            if (
                isset($mlt->useMoreLikeThisHandler)
                && $mlt->useMoreLikeThisHandler
            ) {
                $this->useHandler = true;
                $this->handlerParams = $mlt->params ?? '';
            }
            if (isset($mlt->count)) {
                $this->count = $mlt->count;
            }
        }
    }

    /// Public API

    /**
     * Return SOLR search parameters based on a record Id and params.
     *
     * @param string $id Record Id
     *
     * @return ParamBag
     */
    public function build($id)
    {
        $params = new ParamBag();
        if ($this->useHandler) {
            $mltParams = $this->handlerParams
                ? $this->handlerParams
                : 'qf=title,title_short,callnumber-label,topic,language,author,'
                    . 'publishDate mintf=1 mindf=1';
            $params->set('q', sprintf('{!mlt %s}%s', $mltParams, $id));
        } else {
            $params->set(
                'q',
                sprintf('%s:"%s"', $this->uniqueKey, addcslashes($id, '"'))
            );
            $params->set('qt', 'morelikethis');
        }
        if (null === $params->get('rows')) {
            $params->set('rows', $this->count);
        }
        return $params;
    }
}
