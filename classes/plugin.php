<?php
/**
 * Основной класс плагина turboshop-orders
 */
namespace Turboshop_orders;

class Plugin {

    /**
     * @var mixed
     * Параметры плагина в виде ассоциативного массива
     */
    private $settings;

    /**
     * Конструктор класса
     */
    public function __construct() {
        // Хуки
        add_action( 'init', array( $this, 'init' ) );
    }

    /**
     * Метод инициализации, выполняется по хуку init
     */
    public function init() {
        // Загружаем параметры плагина
        $this->settings = new Settings();
    }

    
}