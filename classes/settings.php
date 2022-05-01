<?php
/**
 * Класс управления параметрами плагина
 */
namespace Turboshop_orders;

class Settings {
    /**
     * Конструктор класса
     */
    public function __construct() {
        // Хуки
        add_filter( 'woocommerce_settings_tabs_array' , array( $this, 'get_settings_tabs' ), 50 );
        add_filter( 'woocommerce_settings_tabs_'.TURBOSHOP_ORDERS , array( $this, 'settings_tab' ) );
        add_filter( 'woocommerce_update_options_'.TURBOSHOP_ORDERS , array( $this, 'update_settings' ) );
    }

    /**
     * Чтение параметра настроек
     * @param string    $param    Имя параметра
     * @return mixed    Возвращает значение параметра или false
     */
    public function get( $param ) {
        // Возвращает параметр
        return get_option( TURBOSHOP_ORDERS . '_' . $param );
    }
    
    /**
     * Метод возвращает массив табов в настройках WooCommerce 
     * 
     * @param mixed $$settings_tabs   Массив с параметрами табов
     * @return mixed
     */
    public function get_settings_tabs( $settings_tabs ) {
        $settings_tabs[ TURBOSHOP_ORDERS ] = __( 'Яндекс.Турбо', TURBOSHOP_ORDERS );
        return $settings_tabs;
    }

    /**
     * Метод возвращает массив полей настроек
     * @return mixed
     */
    private function get_settings_fields() {
        return array(
            'section_title' => array(
                'name'     => __( 'Подключение Турбо‑страниц для интернет-магазинов' , TURBOSHOP_ORDERS ),
                'type'     => 'title',
                'desc'     => __( 'Эта настройка позволяет регистрировать заказы, сделанные в Яндекс.Турбо, в базе WooCommerce.', TURBOSHOP_ORDERS ) . ' ' .
                              __( 'Для работы необходимо получить токен в разделе "Турбо‑страниц для интернет-магазинов / Настройки / Настройка API" Яндекс.Вебмастер.', TURBOSHOP_ORDERS ),
                'id'       => TURBOSHOP_ORDERS . '_section_title'
            ),
            'token' => array(
                'name' => __( 'Токен', TURBOSHOP_ORDERS ),
                'type' => 'text',
                'desc' => __( 'Введите значение полученного токена', TURBOSHOP_ORDERS ),
                'id'   => TURBOSHOP_ORDERS . '_token'
            ),
            'section_end' => array(
                 'type' => 'sectionend',
                 'id' => TURBOSHOP_ORDERS . '_section_end'
            )
        );
    }

    /**
     * Метод вывода настроек 
     */
    public function settings_tab() {
        woocommerce_admin_fields( $this->get_settings_fields() );
    }

    /**
     * Метод вывода настроек 
     */
    public function update_settings() {
        woocommerce_update_options( $this->get_settings_fields() );
    }
}