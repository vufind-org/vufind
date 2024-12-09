<?php

/**
 * Lightweight translator aware marker interface.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\I18n\Translator;

use Laminas\I18n\Translator\TranslatorInterface;

/**
 * Lightweight translator aware marker interface (used as an alternative to
 * \Laminas\I18n\Translator\TranslatorAwareInterface, which requires an excessive
 * number of methods to be implemented).
 *
 * @category VuFind
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface TranslatorAwareInterface
{
    /**
     * Set a translator
     *
     * @param TranslatorInterface $translator Translator
     *
     * @return TranslatorAwareInterface
     */
    public function setTranslator(TranslatorInterface $translator);
}
