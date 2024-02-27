<?php

/**
 * AJAX handler plugin manager
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

/**
 * AJAX handler plugin manager
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'checkRequestIsValid' => CheckRequestIsValid::class,
        'commentRecord' => CommentRecord::class,
        'deleteRecordComment' => DeleteRecordComment::class,
        'doiLookup' => DoiLookup::class,
        'getACSuggestions' => GetACSuggestions::class,
        'getIlsStatus' => GetIlsStatus::class,
        'getItemStatuses' => GetItemStatuses::class,
        'getLibraryPickupLocations' => GetLibraryPickupLocations::class,
        'getRecordCommentsAsHTML' => GetRecordCommentsAsHTML::class,
        'getRecordCover' => GetRecordCover::class,
        'getRecordDetails' => GetRecordDetails::class,
        'getRecordRating' => GetRecordRating::class,
        'getRecordTags' => GetRecordTags::class,
        'getRecordVersions' => GetRecordVersions::class,
        'getRequestGroupPickupLocations' => GetRequestGroupPickupLocations::class,
        'getResolverLinks' => GetResolverLinks::class,
        'getResultCount' => GetResultCount::class,
        'getSaveStatuses' => GetSaveStatuses::class,
        'getSearchResults' => GetSearchResults::class,
        'getSideFacets' => GetSideFacets::class,
        'getUserFines' => GetUserFines::class,
        'getUserHolds' => GetUserHolds::class,
        'getUserILLRequests' => GetUserILLRequests::class,
        'getUserStorageRetrievalRequests' => GetUserStorageRetrievalRequests::class,
        'getUserTransactions' => GetUserTransactions::class,
        'getVisData' => GetVisData::class,
        'keepAlive' => KeepAlive::class,
        'recommend' => Recommend::class,
        'relaisAvailability' => RelaisAvailability::class,
        'relaisInfo' => RelaisInfo::class,
        'relaisOrder' => RelaisOrder::class,
        'systemStatus' => SystemStatus::class,
        'tagRecord' => TagRecord::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        CheckRequestIsValid::class => AbstractIlsAndUserActionFactory::class,
        CommentRecord::class => CommentRecordFactory::class,
        DeleteRecordComment::class => DeleteRecordCommentFactory::class,
        DoiLookup::class => DoiLookupFactory::class,
        GetACSuggestions::class => GetACSuggestionsFactory::class,
        GetIlsStatus::class => GetIlsStatusFactory::class,
        GetItemStatuses::class => GetItemStatusesFactory::class,
        GetLibraryPickupLocations::class => AbstractIlsAndUserActionFactory::class,
        GetRecordCommentsAsHTML::class => GetRecordCommentsAsHTMLFactory::class,
        GetRecordCover::class => GetRecordCoverFactory::class,
        GetRecordDetails::class => GetRecordDetailsFactory::class,
        GetRecordRating::class => GetRecordRatingFactory::class,
        GetRecordTags::class => GetRecordTagsFactory::class,
        GetRecordVersions::class => GetRecordVersionsFactory::class,
        GetRequestGroupPickupLocations::class =>
            AbstractIlsAndUserActionFactory::class,
        GetResolverLinks::class => GetResolverLinksFactory::class,
        GetResultCount::class => GetResultCountFactory::class,
        GetSaveStatuses::class => GetSaveStatusesFactory::class,
        GetSearchResults::class => GetSearchResultsFactory::class,
        GetSideFacets::class => GetSideFacetsFactory::class,
        GetUserFines::class => GetUserFinesFactory::class,
        GetUserHolds::class => AbstractIlsAndUserActionFactory::class,
        GetUserILLRequests::class => AbstractIlsAndUserActionFactory::class,
        GetUserStorageRetrievalRequests::class =>
            AbstractIlsAndUserActionFactory::class,
        GetUserTransactions::class => AbstractIlsAndUserActionFactory::class,
        GetVisData::class => GetVisDataFactory::class,
        KeepAlive::class => KeepAliveFactory::class,
        Recommend::class => RecommendFactory::class,
        RelaisAvailability::class => AbstractRelaisActionFactory::class,
        RelaisInfo::class =>  AbstractRelaisActionFactory::class,
        RelaisOrder::class => AbstractRelaisActionFactory::class,
        SystemStatus::class => SystemStatusFactory::class,
        TagRecord::class => TagRecordFactory::class,
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return AjaxHandlerInterface::class;
    }
}
