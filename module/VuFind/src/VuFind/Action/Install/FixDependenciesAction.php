<?php

/**
 * Install "fix dependencies" action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010, 2022.
 * Copyright (C) The National Library of Finland 2026.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
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
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Action\Install;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\FlashMessagesHelper;

use function defined;
use function function_exists;
use function is_callable;

/**
 * Install "fix dependencies" action.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FixDependenciesAction extends AbstractInstallAction
{
    /**
     * Display instructions for fixing dependency problems.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $problems = 0;

        // Is our version new enough?
        if (!$this->phpVersionIsNewEnough()) {
            $msg = 'VuFind requires PHP version ' . $this->getMinimalPhpVersion()
                . ' or newer; you are running ' . phpversion() . '. Please upgrade.';
            $this->getHelper(FlashMessagesHelper::class)->addErrorMessage($msg);
            $problems++;
        }

        $missingExtensions = [];
        // Is the mbstring library missing?
        if (!function_exists('mb_substr')) {
            $missingExtensions[] = 'mbstring';
        }

        // Is the GD library missing?
        if (!is_callable('imagecreatefromstring')) {
            $missingExtensions[] = 'GD';
        }

        // Is the openssl library missing?
        if (!function_exists('openssl_encrypt')) {
            $missingExtensions[] = 'openssl';
        }

        // Is the XSL library missing?
        if (!class_exists('XSLTProcessor')) {
            $missingExtensions[] = 'XSL';
        }

        // Is the sodium extension missing?
        if (!defined('SODIUM_LIBRARY_VERSION')) {
            $missingExtensions[] = 'sodium';
        }
        if ($missingExtensions) {
            ++$problems;
        }

        return $this->renderTemplate($request, $response, compact('problems', 'missingExtensions'));
    }
}
