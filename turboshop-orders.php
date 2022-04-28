<?php
/**
 * TurboShop Orders
 *
 * @package           turboshop-orders
 * @author            Ivan Nikitin
 * @copyright         2022 IvanNikitin.com
 * @license           GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       TurboShop Orders
 * Plugin URI:        https://github.com/ivannikitin-com/turboshop-orders
 * Description:       Плагин для Woocommerce, который сохраняет ClientID Google Analytics, Яндекс.Метрика и других счетчиков в полях заказа и в формах ContactForm 7.
 * Version:           0.1
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Иван Никитин
 * Author URI:        https://ivannikitin.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Update URI:        https://github.com/ivannikitin-com/turboshop-orders
 * Text Domain:       turboshop_orders
 * Domain Path:       /lang
 */
// Напрямую не вызываем!
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/* Глобальные константы плагина */
define( 'TURBOSHOP_ORDERS', 'turboshop_orders' );	      // Text Domain
define( 'TURBOSHOP_ORDERS_DIR', dirname( __FILE__ ) );	  // Папка плагина

/* Файлы плагина */
require_once( 'classes/plugin.php' );
require_once( 'classes/settings.php' );

/**
 * Запуск плагина
 */
new \Turboshop_orders\Plugin();