<?php /** @noinspection SqlNoDataSourceInspection */
/*
Plugin Name: Abivia Short Links
Description: A custom URL shortener with analytics, rotating links, and password protection.
Version: 1.0.0
Author: Abivia
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 8.4
*/

/**
 * CHANGELOG v1.0.0 – 2025-09-23
 * ------------------------------------------------
 * - Adapted from links plugin 1.4.3 by Lukastech
 * - Extracted views into PenKnife templates.
 * - Changed the link stub, made it a constant.
 * - Encapsulated everything in a class.
 * - Link aliases are converted to a lowercase slug.
 * - Minimum PHP 8.4.
 * - Removed the "random" feature.
 * - Added geo-based redirection
 * - Modified geo-lookups to only run on geo-directed links
 * - Logged which link was used on a rotating shortcode.
 * ------------------------------------------------
 */

use Abivia\Wp\LinkShortener\LinkShortener;

defined('ABSPATH') or die('Go away.');

require_once __DIR__ . '/vendor/autoload.php';

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

$als = LinkShortener::singleton();

register_activation_hook(__FILE__, $als->call('activate'));

$als->hookIn();
