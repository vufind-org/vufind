<?php

/**
 * Base class for cover image actions.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Cover;

use Psr\Http\Message\ResponseInterface;
use VuFind\Action\AbstractAction;
use VuFind\Cover\CachingProxy;
use VuFind\Cover\Loader as CoverLoader;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Show cover image action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractCoverAction extends AbstractAction
{
    /**
     * Constructor.
     *
     * @param CoverLoader  $loader Cover loader
     * @param CachingProxy $proxy  Proxy loader
     * @param array        $config VuFind configuration
     */
    public function __construct(
        protected CoverLoader $loader,
        protected CachingProxy $proxy,
        #[Autowire(config: 'config')]
        protected array $config,
    ) {
        parent::__construct();
    }

    /**
     * Support method -- update the response with the image currently found in cover loader.
     *
     * @param ResponseInterface $response Response
     * @param ?string           $type     Content type of image (null to access loader)
     * @param ?string           $image    Image data (null to access loader)
     *
     * @return ResponseInterface
     */
    protected function displayImage(
        ResponseInterface $response,
        ?string $type = null,
        ?string $image = null
    ): ResponseInterface {
        $response = $response->withHeader(
            'Content-Type',
            $type ?: $this->loader->getContentType()
        );

        // Send proper caching headers so that the user's browser
        // is able to cache the cover images and not have to re-request
        // then on each page load. Default TTL set at 14 days
        if ($this->config['Content']['coverimagesBrowserCache'] ?? true) {
            $coverImageTtl = (60 * 60 * 24 * 14); // 14 days
            $response = $response->withHeader(
                'Cache-Control',
                'maxage=' . $coverImageTtl
            )->withHeader(
                'Pragma',
                'public'
            )->withHeader(
                'Expires',
                gmdate('D, d M Y H:i:s', time() + $coverImageTtl) . ' GMT'
            );
        } else {
            $response = $response->withHeader(
                'Cache-Control',
                'no-cache, no-store, must-revalidate'
            )->withHeader(
                'Pragma',
                'no-cache'
            )->withHeader(
                'Expires',
                '0'
            );
        }

        $response->getBody()->write($image ?: $this->loader->getImage());
        return $response;
    }
}
