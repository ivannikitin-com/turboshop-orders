<?php
/**
 * Класс управления параметрами плагина
 */
namespace Turboshop_orders;

class Settings {
    /**
     * Параметры по умолчанию
     * Используем статичную переменную, так как константы класса не могут быть массивом
     */
    private static $DEFAULTS = array(

    );    

    /**
     * Конструктор класса
     */
    public function __construct() {
        // Хуки

    }

    /**
     * Чтение параметра настроек
     * @param string    $param    Имя параметра
     * @return mixed    Возвращает значение параметра или null
     */
    public function get( $param ) {
        // Если массив настроек пустой, значит нужно загрузить настройки
        if ( 0 == count( $this->settings ) ) $this->load_settings( self::$DEFAULTS );

        // Проверяем наличие параметра
        if ( !array_key_exists( $param, $this->settings ) ) return null;

        // Возвращает параметр
        return $this->settings[ $param ];
    }
    
    /**
     * Загрузка параметров плагина
     * @param mixed    $defaults    Параметры по умолчанию
     */ 
    private function load_settings( $defaults ) {
        $this->settings = $defaults;
    }    

}