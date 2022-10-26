<?php
/**
 * AJAX handler to get all tags for a record as HTML.
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
use Laminas\View\Renderer\RendererInterface;
use VuFind\Db\Row\User;
use VuFind\Db\Table\Tags;

/**
 * AJAX handler to get all tags for a record as HTML.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetRecordTags extends AbstractBase
{
    /**
     * Tags database table
     *
     * @var Tags
     */
    protected $table;

    /**
     * Logged in user (or false)
     *
     * @var User|bool
     */
    protected $user;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * Constructor
     *
     * @param Tags              $table    Tags table
     * @param User|bool         $user     Logged in user (or false)
     * @param RendererInterface $renderer View renderer
     */
    public function __construct(Tags $table, $user, RendererInterface $renderer)
    {
        $this->table = $table;
        $this->user = $user;
        $this->renderer = $renderer;
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
        $is_me_id = !$this->user ? null : $this->user->id;

        // Retrieve from database:
        $tags = $this->table->getForResource(
            $params->fromQuery('id'),
            $params->fromQuery('source', DEFAULT_SEARCH_BACKEND),
            0,
            null,
            null,
            'count',
            $is_me_id
        );

        // Build data structure for return:
        $tagList = [];
        foreach ($tags as $tag) {
            $tagList[] = [
                'tag'   => $tag->tag,
                'cnt'   => $tag->cnt,
                'is_me' => !empty($tag->is_me)
            ];
        }

        $viewParams = ['tagList' => $tagList, 'loggedin' => (bool)$this->user];
        $html = $this->renderer->render('record/taglist', $viewParams);
        return $this->formatResponse(compact('html'));
    }
}
