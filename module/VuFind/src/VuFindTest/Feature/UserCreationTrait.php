<?php

/**
 * Trait with utility methods for user creation/management.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Feature;

use Behat\Mink\Element\Element;

/**
 * Trait with utility methods for user creation/management.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait UserCreationTrait
{
    /**
     * Mink support function: fill in the account creation form.
     *
     * @param Element $page      Page element.
     * @param array   $overrides Optional overrides for form values.
     *
     * @return void
     */
    protected function fillInAccountForm(Element $page, $overrides = [])
    {
        $defaults = [
            'firstname' => 'Tester',
            'lastname' => 'McTestenson',
            'email' => 'username1@ignore.com',
            'username' => 'username1',
            'password' => 'test',
            'password2' => 'test',
        ];

        foreach ($defaults as $field => $default) {
            $this->findCssAndSetValue(
                $page,
                '#account_' . $field,
                $overrides[$field] ?? $default
            );
        }
    }

    /**
     * Mink support function: fill in the login form.
     *
     * @param Element $page     Page element.
     * @param string  $username Username to set (null to skip)
     * @param string  $password Password to set (null to skip)
     * @param bool    $inModal  Should we assume the login box is in a lightbox?
     * @param string  $prefix   Extra selector prefix
     *
     * @return void
     */
    protected function fillInLoginForm(
        Element $page,
        $username,
        $password,
        $inModal = true,
        $prefix = ''
    ) {
        $prefix = ($inModal ? '.modal-body ' : '') . $prefix;
        if (null !== $username) {
            $this->findCssAndSetValue(
                $page,
                $prefix . '[name="username"]',
                $username
            );
        }
        if (null !== $password) {
            $this->findCssAndSetValue(
                $page,
                $prefix . '[name="password"]',
                $password
            );
        }
    }

    /**
     * Mink support function: fill in the change password form.
     *
     * @param Element $page    Page element.
     * @param string  $old     Old password
     * @param string  $new     New password
     * @param bool    $inModal Should we assume the login box is in a lightbox?
     * @param string  $prefix  Extra selector prefix
     *
     * @return void
     */
    protected function fillInChangePasswordForm(
        Element $page,
        $old,
        $new,
        $inModal = false,
        $prefix = '#newpassword '
    ) {
        $prefix = ($inModal ? '.modal-body ' : '') . $prefix;
        $this->findCssAndSetValue($page, $prefix . '[name="oldpwd"]', $old);
        $this->findCssAndSetValue($page, $prefix . '[name="password"]', $new);
        $this->findCssAndSetValue($page, $prefix . '[name="password2"]', $new);
    }

    /**
     * Submit the login form (assuming it's open).
     *
     * @param Element $page    Page element.
     * @param bool    $inModal Should we assume the login box is in a lightbox?
     * @param string  $prefix  Extra selector prefix
     *
     * @return void
     */
    protected function submitLoginForm(Element $page, $inModal = true, $prefix = '')
    {
        $prefix = ($inModal ? '.modal-body ' : '') . $prefix;
        $button = $this->findCss($page, $prefix . 'input.btn.btn-primary');
        $button->click();
    }
}
