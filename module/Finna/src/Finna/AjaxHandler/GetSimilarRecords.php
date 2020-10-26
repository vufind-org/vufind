<?php
/**
 * "Get Similar Records" AJAX handler
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2018.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\RendererInterface;
use VuFind\Record\Loader;
use VuFind\Related\Similar;
use VuFind\Session\Settings as SessionSettings;

/**
 * "Get Similar Records" AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetSimilarRecords extends \VuFind\AjaxHandler\AbstractBase
{
    /**
     * Record loader
     *
     * @var Loader
     */
    protected $recordLoader;

    /**
     * Similar record handler
     *
     * @var Similar
     */
    protected $similar;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * Constructor
     *
     * @param SessionSettings   $ss       Session settings
     * @param Loader            $loader   Record loader
     * @param Similar           $similar  Similar record handler
     * @param RendererInterface $renderer View renderer
     */
    public function __construct(SessionSettings $ss, Loader $loader,
        Similar $similar,
        RendererInterface $renderer
    ) {
        $this->sessionSettings = $ss;
        $this->similar = $similar;
        $this->recordLoader = $loader;
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
        $this->disableSessionWrites();  // avoid session write timing bug

        $id = $params->fromPost('id', $params->fromQuery('id'));

        if (empty($id)) {
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }

        $driver = $this->recordLoader->load($id);

        $this->similar->init('', $driver);

        $html = $this->renderer->partial(
            'Related/Similar.phtml',
            ['related' => $this->similar]
        );

        return $this->formatResponse(compact('html'));
    }
}
