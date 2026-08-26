<?php

/**
 * AJAX handler to tag/untag a record.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Psr\Http\Message\ServerRequestInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Http\HttpStatus;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Record\Loader;
use VuFind\Tags\TagsService;

use function strlen;

/**
 * AJAX handler to tag/untag a record.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class TagRecord extends AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Constructor.
     *
     * @param Loader               $loader      Record loader
     * @param TagsService          $tagsService Tags service
     * @param ?UserEntityInterface $user        Logged in user (or null)
     */
    public function __construct(
        protected Loader $loader,
        protected TagsService $tagsService,
        protected ?UserEntityInterface $user
    ) {
        parent::__construct(null);
    }

    /**
     * Handle a request.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(ServerRequestInterface $request): array
    {
        if (!$this->user) {
            return $this->formatResponse(
                $this->translate('You must be logged in first'),
                HttpStatus::STATUS_HTTP_NEED_AUTH
            );
        }

        $id = $this->getPostParam($request, 'id');
        $source = $this->getPostParam($request, 'source', DEFAULT_SEARCH_BACKEND);
        $tag = $this->getPostParam($request, 'tag', '');

        if (strlen($tag) > 0) { // don't add empty tags
            $driver = $this->loader->load($id, $source);
            $serviceMethod = ('false' === $this->getPostParam($request, 'remove', 'false'))
                ? 'linkTagsToRecord'
                : 'unlinkTagsFromRecord';
            $this->tagsService->$serviceMethod(
                $driver,
                $this->user,
                $this->tagsService->parse($tag)
            );
        }

        return $this->formatResponse('');
    }
}
