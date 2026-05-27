<?php

/**
 * Browse tags action.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Browse;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Exception\Forbidden as ForbiddenException;

use function array_slice;

/**
 * Browse tags action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class TagAction extends AbstractBrowseAction
{
    /**
     * Browse tags.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        if (!$this->tagsEnabled()) {
            throw new ForbiddenException('Tags disabled.');
        }

        $templateParams = $this->createTemplateParams(
            'Tag',
            [
                'categoryList' => [
                    'alphabetical' => 'By Alphabetical',
                    'popularity'   => 'By Popularity',
                    'recent'       => 'By Recent',
                ],
            ]
        );

        if ($findBy = $this->getQueryParam('findby')) {
            $resultLimit = (int)($this->config['Browse']['result_limit'] ?? 100);
            // Special case -- display alphabet selection if necessary:
            if ('alphabetical' === $findBy) {
                $templateParams['secondaryList'] = $this->getAlphabetList('Tag');
                // Only display tag list when a valid letter is selected:
                if (null !== ($query = $this->getQueryParam('query'))) {
                    // Note -- this does not need to be escaped because
                    // $params['query'] has already been validated against
                    // the getAlphabetList() method below!
                    $tags = $this->tagsService->getNonListTagsFuzzilyMatchingString($query);
                    $tagList = [];
                    foreach ($tags as $tag) {
                        if ($tag['cnt'] > 0) {
                            $tagList[] = [
                                'displayText' => $tag['tag'],
                                'value' => $tag['tag'],
                                'count' => $tag['cnt'],
                            ];
                        }
                    }
                    $templateParams['resultList'] = array_slice(
                        $tagList,
                        0,
                        $resultLimit
                    );
                }
            } else {
                // Default case: always display tag list for non-alphabetical modes:
                $tagList = $this->tagsService->getTagBrowseList($findBy, $resultLimit);
                $resultList = [];
                foreach ($tagList as $i => $tag) {
                    $resultList[$i] = [
                        'displayText' => $tag['tag'],
                        'value' => $tag['tag'],
                        'count'    => $tag['cnt'],
                    ];
                }
                $templateParams['resultList'] = $resultList;
            }
            $templateParams['paramTitle'] = 'lookfor=';
            $templateParams['searchParams'] = [];
        }

        return $this->performSearch('Tag', $templateParams);
    }
}
