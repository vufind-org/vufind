<?php
/**
 * Wrapper class for handling logged-in user in session.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  Authentication
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Auth;

use Finna\Db\Row\User;
use VuFind\Auth\AbstractBase;

/**
 * Wrapper class for handling logged-in user in session.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Manager extends \VuFind\Auth\Manager
{
    /**
     * Get the active authentication handler.
     *
     * @return AbstractBase
     */
    public function getActiveAuth()
    {
        return $this->getAuth($this->activeAuth);
    }

    /**
     * Get secondary login field label (if any)
     *
     * @param string $target Login target (only for MultiILS)
     *
     * @return string|false
     */
    public function getSecondaryLoginFieldLabel($target = '')
    {
        $auth = $this->getAuth();
        if (is_callable([$auth, 'getSecondaryLoginFieldLabel'])) {
            return $auth->getSecondaryLoginFieldLabel($target);
        }
        return false;
    }

    /**
     * Check if ILS supports password recovery
     *
     * @param string $target Login target (only for MultiILS)
     *
     * @return string|false
     */
    public function ilsSupportsPasswordRecovery($target = '')
    {
        $auth = $this->getAuth();
        if (is_callable([$auth, 'ilsSupportsPasswordRecovery'])) {
            return $auth->ilsSupportsPasswordRecovery($target);
        }
        return false;
    }

    /**
     * Check if ILS supports self-registration
     *
     * @param string $target Login target (only for MultiILS)
     *
     * @return string|false
     */
    public function ilsSupportsSelfRegistration($target = '')
    {
        $auth = $this->getAuth();
        if (is_callable([$auth, 'ilsSupportsSelfRegistration'])) {
            return $auth->ilsSupportsSelfRegistration($target);
        }
        return false;
    }
}
