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

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Db\Entity\UserEntityInterface;
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
     * Constructor
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
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        if (!$this->user) {
            return $this->formatResponse(
                $this->translate('You must be logged in first'),
                self::STATUS_HTTP_NEED_AUTH
            );
        }

        $id = $params->fromPost('id');
        $source = $params->fromPost('source', DEFAULT_SEARCH_BACKEND);
        $tag = $params->fromPost('tag', '');

        if (strlen($tag) > 0) { // don't add empty tags
            $driver = $this->loader->load($id, $source);
            $serviceMethod = ('false' === $params->fromPost('remove', 'false'))
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
