<?php

/**
 * Short link controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use Laminas\Config\Config;
use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\UrlShortener\UrlShortenerInterface;

use function is_callable;
use function strlen;

/**
 * Short link controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ShortlinkController extends AbstractBase
{
    /**
     * Amount of seconds after which HTML redirect is performed.
     *
     * @var int
     */
    protected $redirectDelayHtml = 3;

    /**
     * Which redirect mechanism to use (html, http, threshold:<urlLength>)
     *
     * @var string
     */
    protected $redirectMethod = 'threshold:1000';

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm     Service manager
     * @param Config                  $config VuFind configuration
     */
    public function __construct(ServiceLocatorInterface $sm, Config $config)
    {
        // Call standard record controller initialization:
        parent::__construct($sm);

        // Set redirect method, if specified:
        if (isset($config->Mail->url_shortener_redirect_method)) {
            $this->redirectMethod = strtolower(
                trim($config->Mail->url_shortener_redirect_method)
            );
        }
    }

    /**
     * Redirect to given URL by using a HTML meta redirect mechanism.
     *
     * @param string $url Redirect target
     *
     * @return mixed
     */
    protected function redirectViaHtml($url)
    {
        $view = $this->createViewModel();
        $view->redirectTarget = $url;
        $view->redirectDelay = $this->redirectDelayHtml;
        return $view;
    }

    /**
     * Redirect to given URL by using a HTTP header.
     *
     * @param string $url Redirect target
     *
     * @return mixed
     */
    protected function redirectViaHttp($url)
    {
        return $this->redirect()->toUrl($url);
    }

    /**
     * Resolve full version of shortlink & redirect to target.
     *
     * @return mixed
     */
    public function redirectAction()
    {
        if ($id = $this->params('id')) {
            $resolver = $this->getService(UrlShortenerInterface::class);
            if ($url = $resolver->resolve($id)) {
                $threshRegEx = '"^threshold:(\d+)$"i';
                if (preg_match($threshRegEx, $this->redirectMethod, $hits)) {
                    $threshold = $hits[1];
                    $method = (strlen($url) > $threshold) ? 'Html' : 'Http';
                } else {
                    $method = ucwords($this->redirectMethod);
                }
                if (!is_callable([$this, 'redirectVia' . $method])) {
                    throw new \VuFind\Exception\BadConfig(
                        'Invalid redirect method: ' . $method
                    );
                }
                return $this->{'redirectVia' . $method}($url);
            }
        }

        $this->getResponse()->setStatusCode(404);
    }
}
