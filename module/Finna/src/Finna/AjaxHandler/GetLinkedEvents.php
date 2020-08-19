<?php
/**
 * GetLinkedEvents AJAX handler
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\I18n\Translator\TranslatorAwareInterface;

/**
 * GetLinkedEvents AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetLinkedEvents extends \VuFind\AjaxHandler\AbstractBase
    implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Linked Events
     *
     * @var LinkedEvents $linkedEvents
     */
    protected $linkedEvents;

    /**
     * View renderer
     *
     * @var ViewRenderer $viewRenderer
     */
    protected $viewRenderer;

    /**
     * Constructor
     *
     * @param LinkedEvents $linkedEvents linkedEvents service
     * @param ViewRenderer $viewRenderer view renderer
     */
    public function __construct(
        \Finna\Feed\LinkedEvents $linkedEvents,
        \Laminas\View\Renderer\PhpRenderer $viewRenderer
    ) {
        $this->linkedEvents = $linkedEvents;
        $this->viewRenderer = $viewRenderer;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $param = [];
        $param['query'] = $params->fromQuery('params', []);
        $param['url'] = $params->fromQuery('url', '');
        try {
            $events = $this->linkedEvents->getEvents($param);
        } catch (\Exception $e) {
            return $this->formatResponse(
                $this->translate('An error has occurred'),
                self::STATUS_HTTP_ERROR
            );
        }
        $response = false;
        if (!empty($events)) {
            if (isset($param['query']['id'])) {
                $relatedEvents = $events['events']['relatedEvents'] ?? '';
                $html = '';
                if ($relatedEvents) {
                    $html = $this->viewRenderer->partial(
                        'ajax/feed-grid.phtml',
                        ['items' => $relatedEvents, 'images' => true]
                    );
                }
                $response = [
                    'events' => $events['events'][0],
                    'relatedEvents' => $html
                ];
            } elseif (!empty($events['events'])) {
                $response['html'] = $this->viewRenderer->partial(
                    'ajax/feed-grid.phtml',
                    ['items' => $events['events'], 'images' => true]
                );
                $response['next'] = $events['next'];
            }
        }
        return $this->formatResponse($response);
    }
}
