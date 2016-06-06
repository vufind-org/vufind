<?php
/**
 * KohaRESTful ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) Josef Moravec, 2016.
 * Copyright (C) Jiri Kozlovsky, 2016.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Josef Moravec <josef.moravec@gmail.com>
 * @author   Jiri Kozlovsky <@>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace VuFind\ILS\Driver;
use PDO, PDOException;
use VuFind\Exception\ILS as ILSException;
use VuFindHttp\HttpServiceInterface; //todo: use Trait
use Zend\Log\LoggerInterface; //todo: use Trait
use VuFind\Exception\Date as DateException;

//todo: will extend \VuFind\ILS\Driver\AbstractBase, this is just for testing and developing purposes
class KohaRESTful extends \VuFind\ILS\Driver\KohaILSDI implements
    \VuFindHttp\HttpServiceAwareInterface, \Zend\Log\LoggerAwareInterface
{




}

