<?php
/**
 * AJAX handler to comment on a record.
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

use VuFind\Controller\Plugin\Recaptcha;
use VuFind\Db\Row\User;
use VuFind\Db\Table\Resource;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use Zend\Mvc\Controller\Plugin\Params;

/**
 * AJAX handler to comment on a record.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CommentRecord extends AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Resource database table
     *
     * @var Resource
     */
    protected $table;

    /**
     * Recaptcha controller plugin
     *
     * @var Recaptcha
     */
    protected $recaptcha;

    /**
     * Logged in user (or false)
     *
     * @var User|bool
     */
    protected $user;

    /**
     * Are comments enabled?
     *
     * @var bool
     */
    protected $enabled;

    /**
     * Constructor
     *
     * @param Resource  $table     Resource database table
     * @param Recaptcha $recaptcha Recaptcha controller plugin
     * @param User|bool $user      Logged in user (or false)
     * @param bool      $enabled   Are comments enabled?
     */
    public function __construct(Resource $table, Recaptcha $recaptcha, $user,
        $enabled = true
    ) {
        $this->table = $table;
        $this->recaptcha = $recaptcha;
        $this->user = $user;
        $this->enabled = $enabled;
    }

    /**
     * Is CAPTCHA valid? (Also returns true if CAPTCHA is disabled).
     *
     * @return bool
     */
    protected function checkCaptcha()
    {
        // Not enabled? Report success!
        if (!$this->recaptcha->active('userComments')) {
            return true;
        }
        $this->recaptcha->setErrorMode('none');
        return $this->recaptcha->validate();
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
        // Make sure comments are enabled:
        if (!$this->enabled) {
            return $this->formatResponse(
                $this->translate('Comments disabled'),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }

        if ($this->user === false) {
            return $this->formatResponse(
                $this->translate('You must be logged in first'),
                self::STATUS_HTTP_NEED_AUTH
            );
        }

        $id = $params->fromPost('id');
        $source = $params->fromPost('source', DEFAULT_SEARCH_BACKEND);
        $comment = $params->fromPost('comment');
        if (empty($id) || empty($comment)) {
            return $this->formatResponse(
                $this->translate('bulk_error_missing'),
                self::STATUS_HTTP_BAD_REQUEST
            );
        }

        if (!$this->checkCaptcha()) {
            return $this->formatResponse(
                $this->translate('recaptcha_not_passed'),
                self::STATUS_HTTP_FORBIDDEN
            );
        }

        $resource = $this->table->findResource($id, $source);
        $commentId = $resource->addComment($comment, $this->user);
        return $this->formatResponse(compact('commentId'));
    }
}
