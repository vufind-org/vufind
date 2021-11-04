<?php
declare(strict_types=1);

/**
 * Trait TranslatorAwareDriverTrait
 *
 * PHP version 7
 *
 * Copyright (C) Moravian Library 2021.
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
 * @package  VuFind\ILS\Driver
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */
namespace VuFind\ILS\Driver;

/**
 * Trait TranslatorAwareDriverTrait
 *
 * @category Knihovny.cz
 * @package  VuFind\ILS\Driver
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */
trait TranslatorAwareDriverTrait
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Domain used to translate messages from ILS
     *
     * @var string
     */
    protected $translationDomain = 'ILSMessages';

    /**
     * Translate a message from ILS
     *
     * @param string $message Message to be translated
     *
     * @return string
     */
    public function translateIlsMessage(string $message): string
    {
        return $this->translate($this->translationDomain . '::' . $message);
    }
}
