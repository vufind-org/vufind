<?php
/**
 * Create all the hierarchy files which are used for looking up hierarchical trees.
 * This script will search the Solr index and create the files needed so they don't
 * need to be built at runtime. If this script is run after every index, the caching
 * time for hierarchy trees can be set to -1 so that trees are always assumed to be
 * up to date.
 *
 * -!!!!-This script is specifically for trees built for HTMLTree from Solr.-!!!!-
 *
 * PHP version 8
 *
 * Copyright (C) National Library of Ireland 2012.
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
 * @package  Utilities
 * @author   Lutz Biedinger <lutz.biedinger@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki Wiki
 */

// Manipulate command line to load correct route, then run the main index page:
array_unshift($_SERVER['argv'], array_shift($_SERVER['argv']), 'util', 'createHierarchyTrees');
$_SERVER['argc'] += 2;
require_once __DIR__ . '/../public/index.php';
