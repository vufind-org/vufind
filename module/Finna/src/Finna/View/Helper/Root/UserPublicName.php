<?php

/**
 * User public name view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2022.
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
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Tuure Ilmarinen <tuure.ilmarinen@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

use Finna\Db\Entity\FinnaUserEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * User public name view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Tuure Ilmarinen <tuure.ilmarinen@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class UserPublicName extends \Laminas\View\Helper\AbstractHelper implements
    \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Create publicly shown user name
     *
     * @param ?UserEntityInterface $user User, if any
     *
     * @return string
     */
    public function __invoke(?UserEntityInterface $user)
    {
        $username = '';
        if ($user instanceof FinnaUserEntityInterface) {
            if (!empty($nickname = $user->getFinnaNickname())) {
                $nicknameDescription = strtolower($this->translate('finna_nickname'));
                $username = "$nickname ($nicknameDescription)";
            } elseif (
                ($email = $user->getEmail())
                && ($pos = strpos($email, '@')) !== false
            ) {
                [$username] = explode('+', substr($email, 0, $pos));
            } elseif ($firstname = $user->getFirstname() && $lastname = $user->getLastname()) {
                $username = "$firstname $lastname";
            }
        }
        return $username;
    }
}
