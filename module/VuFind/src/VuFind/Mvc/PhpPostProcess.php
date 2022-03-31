<?php
/**
 * PHP-rendered page post-processor.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Mvc;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\Mvc\MvcEvent;

/**
 * PHP-rendered page post-processor.
 *
 * @category VuFind
 * @package  Service
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class PhpPostProcess extends AbstractListenerAggregate
{
    /**
     * Attach the listener
     *
     * @param EventManagerInterface $events   EventManager
     * @param int                   $priority Event priority
     *
     * @return void
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_FINISH,
            [$this, 'handlePostProcess'],
            $priority
        );
    }

    /**
     * Post-process the response.
     *
     * @param MvcEvent $e Event
     *
     * @return void
     */
    public function handlePostProcess(MvcEvent $e)
    {
        $response = $e->getResponse();
        if (!($response instanceof Response)) {
            return;
        }
        $contentTypeHeader = $response->getHeaders()->get('Content-Type');
        $contentType = $contentTypeHeader ? $contentTypeHeader->getFieldValue() : '';
        if (!$contentType || 'text/html' === $contentType) {
            $content = $response->getContent();
            $response->setContent($this->postProcessHtml($response->getContent()));
        } elseif ('application/json' === $contentType) {
            $content = json_decode($response->getContent(), true);
            if (!empty($content['data']['html'])) {
                $content['data']['html']
                    = $this->postProcessHtml($content['data']['html']);
                $response->setContent(json_encode($content));
            }
        }
    }

    /**
     * Process response HTML content.
     *
     * @param string $content HTML
     *
     * @return string
     */
    protected function postProcessHtml(string $content): string
    {
        return preg_replace('/\s+<Consume-Whitespace\s*\/>\s+/', '', $content);
    }
}
