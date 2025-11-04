<?php

/**
 * List API Controller
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021-2022.
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
 * @package  Controller
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace FinnaApi\Controller;

use Exception;
use Laminas\Http\Response;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\Parameters;
use VuFind\Db\Service\TagServiceInterface;
use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFind\Search\Results\PluginManager;
use VuFind\View\Helper\Root\Record as RecordHelper;
use VuFindApi\Controller\ApiInterface;
use VuFindApi\Controller\ApiTrait;
use VuFindApi\Formatter\RecordFormatter;

use function count;
use function is_array;

/**
 * List API Controller
 *
 * Controls the List API functionality
 *
 * @category VuFind
 * @package  Service
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class ListApiController extends \VuFind\Controller\AbstractBase implements ApiInterface
{
    use ApiTrait;
    use \Finna\Controller\Feature\FinnaUserListTrait;

    /**
     * Default record fields to return if a request does not define the fields
     *
     * @var array
     */
    protected $defaultRecordFields = [];

    /**
     * Max limit of list records in API response (default 100);
     *
     * @var int
     */
    protected $maxLimit = 100;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm              Service manager
     * @param RecordFormatter         $recordFormatter Record formatter
     * @param RecordHelper            $recordHelper    Record view helper
     */
    public function __construct(
        ServiceLocatorInterface $sm,
        protected RecordFormatter $recordFormatter,
        protected RecordHelper $recordHelper
    ) {
        parent::__construct($sm);
        foreach ($recordFormatter->getRecordFields() as $fieldName => $fieldSpec) {
            if (!empty($fieldSpec['vufind.default'])) {
                $this->defaultRecordFields[] = $fieldName;
            }
        }
    }

    /**
     * Get API specification JSON fragment for services provided by the
     * controller
     *
     * @return string
     */
    public function getApiSpecFragment()
    {
        $config = $this->getConfig();
        $results = $this->serviceLocator
            ->get(PluginManager::class)->get('Favorites');
        $options = $results->getOptions();

        $viewParams = [
            'config' => $config,
            'recordFields' => $this->recordFormatter->getRecordFieldSpec(),
            'defaultFields' => $this->defaultRecordFields,
            'sortOptions' => $options->getSortOptions(),
            'defaultSort' => $options->getDefaultSortByHandler(),
            'maxLimit' => $this->maxLimit,
        ];

        return $this->getViewRenderer()->render(
            'listapi/openapi',
            $viewParams
        );
    }

    /**
     * List action
     *
     * @return Response
     * @throws Exception
     */
    public function listAction(): Response
    {
        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();

        if (!isset($request['id'])) {
            return $this->output([], self::STATUS_ERROR, 400, 'Missing id');
        }

        if (
            isset($request['limit'])
            && (!ctype_digit($request['limit'])
            || $request['limit'] < 0 || $request['limit'] > $this->maxLimit)
        ) {
            return $this->output([], self::STATUS_ERROR, 400, 'Invalid limit');
        }

        try {
            $results = $this->serviceLocator->get(PluginManager::class)->get('Favorites');
            $results->getParams()->initFromRequest(new Parameters($request));
            $results->performAndProcessSearch();
            $listObj = $results->getListObject();

            $response = [
                'id' => $listObj->getId(),
                'title' => $listObj->getTitle(),
                'recordCount' => $results->getResultTotal(),
            ];

            $description = $listObj->getDescription();
            if ('' !== $description) {
                $response['description'] = $description;
            }

            if ($this->listTagsEnabled()) {
                $tags = $this->getDbService(TagServiceInterface::class)->getListTags($listObj, $listObj->getUser());
                if (count($tags) > 0) {
                    $response['tags'] = [];
                    foreach ($tags as $tag) {
                        $response['tags'][] = $tag['tag'];
                    }
                }
            }

            if ($results->getResultTotal() > 0) {
                $response['records'] = [];
                $requestedFields = $this->getFieldList($request);

                foreach ($results->getResults() as $result) {
                    $record = [
                        'record' => ($this->recordFormatter
                            ->format([$result], $requestedFields))[0],
                    ];

                    $notes = ($this->recordHelper)($result)->getListNotes($listObj);
                    if (!empty($notes)) {
                        $record['notes'] = $notes[0];
                    }

                    if ($this->tagsEnabled()) {
                        $tags = ($this->recordHelper)($result)->getTags($listObj);
                        if ($tags) {
                            $record['tags'] = [];
                            foreach ($tags as $tag) {
                                $record['tags'][] = $tag->getTag();
                            }
                        }
                    }

                    $response['records'][] = $record;
                }
            }

            return $this->output($response, self::STATUS_OK);
        } catch (RecordMissingException | ListPermissionException $e) {
            return $this->output([], self::STATUS_ERROR, 404, 'List not found');
        }
    }

    /**
     * Get field list based on the request
     *
     * TODO: Move to ApiTrait
     *
     * @param array $request Request params
     *
     * @return array
     */
    protected function getFieldList($request)
    {
        $fieldList = [];
        if (isset($request['field'])) {
            if (!empty($request['field']) && is_array($request['field'])) {
                $fieldList = $request['field'];
            }
        } else {
            $fieldList = $this->defaultRecordFields;
            $fieldList[] = '__index__';
        }
        return $fieldList;
    }
}
