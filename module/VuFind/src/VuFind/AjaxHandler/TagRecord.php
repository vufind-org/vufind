<?php
/**
 * AJAX handler to tag/untag a record.
 *
 * PHP version 7
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
use VuFind\Db\Row\User;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Record\Loader;
use VuFind\Tags;

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
     * Record loader
     *
     * @var Loader
     */
    protected $loader;

    /**
     * Tag parser
     *
     * @var Tags
     */
    protected $tagParser;

    /**
     * Logged in user (or false)
     *
     * @var User|bool
     */
    protected $user;

    /**
     * Constructor
     *
     * @param Loader    $loader Record loader
     * @param Tags      $parser Tag parser
     * @param User|bool $user   Logged in user (or false)
     */
    public function __construct(Loader $loader, Tags $parser, $user)
    {
        $this->loader = $loader;
        $this->tagParser = $parser;
        $this->user = $user;
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
            ('false' === $params->fromPost('remove', 'false'))
                ? $driver->addTags($this->user, $this->tagParser->parse($tag))
                : $driver->deleteTags($this->user, $this->tagParser->parse($tag));
        }

        return $this->formatResponse('');
    }
}
