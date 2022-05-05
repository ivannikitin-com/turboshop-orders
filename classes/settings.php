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
        add_action( 'woocommerce_admin_field_url_readonly', array( $this, 'show_url_readonly' ) );
        add_action( 'woocommerce_admin_field_description', array( $this, 'show_description' ) );

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
                'name'     => Plugin::$meta[ 'title' ] . ' ' . Plugin::$meta[ 'version' ],
                'type'     => 'title',
                'desc'     => Plugin::$meta[ 'description' ],
                'id'       => TURBOSHOP_ORDERS . '_section_title'
            ),
            'description' => array(
                'type'     => 'description',
                'desc'     => '<p>' . 
                                __( 'Пожалуйста, внимательно прочитайте описание', TURBOSHOP_ORDERS ) .
                                ' <a href="' . Plugin::$meta[ 'url' ] . '" target="_blank">' . Plugin::$meta[ 'url' ] . '</a>.' .
                              '</p>' . 
                              '<p>' . 
                                __( 'Мы с благодарностью принимаем любые сообщения об ошибках, любые комментарии и предложения по развитию плагина', TURBOSHOP_ORDERS ) .
                                ' <a href="https://github.com/ivannikitin-com/turboshop-orders/issues" target="_blank">' . __( 'на странице Issue', TURBOSHOP_ORDERS ) . '</a> ' . 
                                __( 'официального репозитория плагина', TURBOSHOP_ORDERS ) . '.' . 
                              '</p>',
                'id'       => TURBOSHOP_ORDERS . '_description'
            ),            
            'token' => array(
                'name'     => __( 'Токен', TURBOSHOP_ORDERS ),
                'type'     => 'text',
                'desc'     => __( 'Введите значение полученного токена', TURBOSHOP_ORDERS ),
                'id'       => TURBOSHOP_ORDERS . '_token'
            ),
            'rest_uri'     => array(
                'name'     => __( 'URL для запросов API', TURBOSHOP_ORDERS ),
                'value'    => get_site_url() . '/wp-json/' . TURBOSHOP_ORDERS . '/' . TURBOSHOP_ORDERS_API_VER,
                'type'     => 'url_readonly',
                'desc'     => __( 'Скопируйте это значение и укажите его в настройках Яндекс.Вебмастер → Турбо-страницы для интернет-магазинов → Настройки → Настройки API', TURBOSHOP_ORDERS ),
                'id'       => TURBOSHOP_ORDERS . '_rest_uri'
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

    /**
     * Вывод поля URL, доступное только для чтения
     * https://github.com/woocommerce/woocommerce/blob/master/includes/admin/class-wc-admin-settings.php
     * 
     * @param mixed $value  Массив параметров поля
     */
    public function show_url_readonly( $value ) {
        $option_value = $value['value'];
        $description = $value['desc'];

        ?><tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo $tooltip_html; // WPCS: XSS ok. ?></label>
            </th>
            <td class="forminp forminp-url">
                <input
                    name="<?php echo esc_attr( $value['id'] ); ?>"
                    id="<?php echo esc_attr( $value['id'] ); ?>"
                    type="url"
                    style="<?php echo esc_attr( $value['css'] ); ?>"
                    value="<?php echo esc_attr( $option_value ); ?>"
                    class="<?php echo esc_attr( $value['class'] ); ?>"
                    placeholder="<?php echo esc_attr( $value['placeholder'] ); ?>"
                    readonly
                    <?php echo implode( ' ', $custom_attributes ); // WPCS: XSS ok. ?>
                    /><?php echo esc_html( $value['suffix'] ); ?>
                    <p class="description"><?php echo $description; // WPCS: XSS ok. ?></p>.
            </td>
        </tr>
        <?php
    }

    /**
     * Вывод описания с текстом
     * https://github.com/woocommerce/woocommerce/blob/master/includes/admin/class-wc-admin-settings.php
     * 
     * @param mixed $value  Массив параметров поля
     */
    public function show_description( $value ) {
        if ( ! empty( $value['desc'] ) ) {
            echo '<div id="' . esc_attr( sanitize_title( $value['id'] ) ) . '-description">';
            echo wp_kses_post( wpautop( wptexturize( $value['desc'] ) ) );
            echo '</div>';
        }
    }
}