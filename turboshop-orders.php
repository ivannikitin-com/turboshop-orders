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
 * Plugin URI:        https://ivannikitin-com.github.io/turboshop-orders/
 * Description:       Плагин для регистрации в WooCommerce заказов, сделанных в магазине Яндекс.Турбо.
 * Version:           1.0.1
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Иван Никитин
 * Author URI:        https://ivannikitin.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Update URI:        https://ivannikitin-com.github.io/turboshop-orders/
 * Text Domain:       turboshop_orders
 * Domain Path:       /lang
 */
// Напрямую не вызываем!
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/* Глобальные константы плагина */
define( 'TURBOSHOP_ORDERS',  'turboshop_orders' );	      // Название и текст домен
define( 'TURBOSHOP_ORDERS_API_VER', 'v1' );	              // Версия REST API плагина
define( 'TURBOSHOP_ORDERS_DIR', dirname( __FILE__ ) );	  // Папка плагина

/* Файлы плагина */
require_once( 'classes/plugin.php' );
require_once( 'classes/settings.php' );

/**
 * Запуск плагина с мета-данными
 */
new \Turboshop_orders\Plugin( get_file_data( __FILE__, array( 
    'title'         =>  'Plugin Name',
    'description'   =>  'Description',
    'version'       =>  'Version',
    'url'           =>  'Plugin URI'
) ) );