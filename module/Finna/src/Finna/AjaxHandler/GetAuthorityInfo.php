<?php
/**
 * AJAX handler for getting authority information.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2017-2019.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\RendererInterface;
use VuFind\Record\Loader;
use VuFind\Session\Settings as SessionSettings;
use VuFindSearch\ParamBag;

/**
 * AJAX handler for getting authority information.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetAuthorityInfo extends \VuFind\AjaxHandler\AbstractBase
{
    /**
     * Record loader
     *
     * @var Loader
     */
    protected $loader;

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
     * @param RendererInterface $renderer View renderer
     */
    public function __construct(SessionSettings $ss, Loader $loader,
        RendererInterface $renderer
    ) {
        $this->sessionSettings = $ss;
        $this->loader = $loader;
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

        $id = $params->fromQuery('id');
        $source = $params->fromQuery('source');
        $type = $params->fromQuery('type');

        if (!$id || !$type) {
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }

        $params = new ParamBag();
        $params->set('authorityType', $type);
        $params->set('recordSource', $source);
        try {
            $driver = $this->loader->load(
                $id, 'SolrAuth', false, $params
            );
        } catch (\VuFind\Exception\RecordMissing $e) {
            return $this->formatResponse('');
        }

        $html = $this->renderer->partial(
            'ajax/authority.phtml', ['driver' => $driver]
        );

        return $this->formatResponse(compact('html'));
    }
}
