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

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Controller\Plugin\Captcha;
use VuFind\Db\Row\User;
use VuFind\Db\Table\Resource;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Record\Loader as RecordLoader;
use VuFind\View\Helper\Root\Record as RecordHelper;

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
     * Captcha controller plugin
     *
     * @var Captcha
     */
    protected $captcha;

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
     * Record loader
     *
     * @var RecordLoader
     */
    protected $recordLoader;

    /**
     * Record helper
     *
     * @var RecordHelper
     */
    protected $recordHelper;

    /**
     * Constructor
     *
     * @param Resource     $table   Resource database table
     * @param Captcha      $captcha Captcha controller plugin
     * @param User|bool    $user    Logged in user (or false)
     * @param bool         $enabled Are comments enabled?
     * @param RecordLoader $loader  Record loader
     * @param RecordHelper $helper  Record helper
     */
    public function __construct(
        Resource $table,
        Captcha $captcha,
        $user,
        $enabled,
        RecordLoader $loader,
        RecordHelper $helper
    ) {
        $this->table = $table;
        $this->captcha = $captcha;
        $this->user = $user;
        $this->enabled = $enabled;
        $this->recordLoader = $loader;
        $this->recordHelper = $helper;
    }

    /**
     * Is CAPTCHA valid? (Also returns true if CAPTCHA is disabled).
     *
     * @return bool
     */
    protected function checkCaptcha()
    {
        // Not enabled? Report success!
        if (!$this->captcha->active('userComments')) {
            return true;
        }
        $this->captcha->setErrorMode('none');
        return $this->captcha->verify();
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
        $driver = $this->recordLoader->load($id, $source, false);

        if (!$this->checkCaptcha()) {
            return $this->formatResponse(
                $this->translate('captcha_not_passed'),
                self::STATUS_HTTP_FORBIDDEN
            );
        }

        $resource = $this->table->findResource($id, $source);
        $commentId = $resource->addComment($comment, $this->user);

        if (($this->recordHelper)($driver)->isRatingEnabled()
            && null !== ($rating = $params->fromPost('rating'))
            && '' !== $rating
        ) {
            $driver->addOrUpdateRating($this->user->id, intval($rating));
        }

        return $this->formatResponse(compact('commentId'));
    }
}
