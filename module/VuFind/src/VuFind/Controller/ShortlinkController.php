<?php
/**
 * Short link controller
 *
 * PHP version 7
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

use VuFind\UrlShortener\UrlShortenerInterface;

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
     * Resolve full version of shortlink & redirect to target.
     *
     * @return mixed
     */
    public function redirectAction()
    {
        if ($id = $this->params('id')) {
            $resolver = $this->serviceLocator->get(UrlShortenerInterface::class);
            if ($url = $resolver->resolve($id)) {
                return $this->redirect()->toUrl($url);
            }
        }

        $this->getResponse()->setStatusCode(404);
    }
}
