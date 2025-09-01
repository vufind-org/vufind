<?php

/**
 * Enum for representing an event subtype.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Type;

/**
 * Enum for representing an event subtype.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
enum AuditEventSubtype: string
{
    // ILS
    case CancelHolds = 'cancel_holds';
    case PlaceHold = 'place_hold';
    case PlaceILLRequest = 'place_ill_request';
    case PlaceStorageRetrievalRequest = 'place_storage_retrieval_request';
    case RenewLoans = 'renew_loans';
    case UpdateHolds = 'update_holds';

    // User
    case ConnectCard = 'connect_card';
    case ConnectCardByEmail = 'connect_card_by_email';
    case Create = 'create';
    case Delete = 'delete';
    case DeleteCard = 'delete_card';
    case DeleteLoginToken = 'delete_login_token';
    case DeleteLoginTokens = 'delete_login_tokens';
    case EditCard = 'edit_card';
    case VerifyEmailHash = 'verify_email_hash';
    case VerifyEmail = 'verify_email';
    case ILSLogin = 'ils_login';
    case ILSLoginFailure = 'ils_login_fail';
    case Login = 'login';
    case LoginFailure = 'login_fail';
    case Logout = 'logout';
    case PasswordChanged = 'password_changed';
    case RememberLogin = 'remember_login';
    case SaveSearch = 'save_search';
    case ScheduleSearch = 'schedule_search';
    case SendAddressVerificationEmail = 'send_address_verification_email';
    case SendCardAuthEmail = 'send_card_auth_email';
    case SendEmailLoginLink = 'send_email_login_link';
    case SendEmailRecoveryLink = 'send_email_recovery_link';
    case TokenLogin = 'token_login';
    case UnSaveSearch = 'un_save_search';
    case Update = 'update';
}
