<?php
/**
 * Основной класс плагина turboshop-orders
 */
namespace Turboshop_orders;
use \Exception  AS Exception;
use \WP_Error AS WP_Error;
use \WC_Order AS WC_Order;

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
        add_action( 'init',             array( $this, 'init' ) );
        add_action( 'rest_api_init',    array( $this, 'rest_api_init' ) );
    }

    /**
     * Метод инициализации, выполняется по хуку init
     */
    public function init() {
        // Загружаем параметры плагина
        $this->settings = new Settings();
    }

    /**
     * Регистрация маршрута REST API
     * https://wp-kama.ru/handbook/rest/extending/routes-endpoints
     */
    public function rest_api_init() {
        // Регистрируем конечную точку для приема заказов
        // https://wp-kama.ru/function/register_rest_route
        register_rest_route( 
            TURBOSHOP_ORDERS . '/' . TURBOSHOP_ORDERS_MAJOR_VER,    // Namespace REST API
            '/order/accept',                                        // Маршрут
            array(
                'methods'  => 'POST',                                // Метод запроса
                'callback' => array( $this, 'post_order_accept' )    // Обработчик
            )
        );        
    }

    /**
     * Обработчик POST /order/accept
     * 
     * @param WP_REST_Request   $request    Объект запроса
     */
    public function post_order_accept( $request ) {
        // Проверка авторизации
        if ( ! $this->is_authorized( $request->get_header( 'Authorization' ) ) ) {
            return new WP_Error( 
                'bad_authorization', 
                __('Требуется авторизация и правильный токен магазина Яндекс.Турбо', TURBOSHOP_ORDERS ), 
                array( 'status' => 403 ) 
            );
        }

        // Получаем данные заказа
        $turbo_order = $request->get_json_params()['order'];
        if ( empty( $turbo_order ) ) {
            return new WP_Error( 
                'no_order_data', 
                __('Не переданы данные заказа Яндекс.Турбо', TURBOSHOP_ORDERS ), 
                array( 'status' => 400 ) 
            );            
        }

        // Ответ магазина
        // https://yandex.ru/dev/turbo-shop/doc/settings/order-accept.html#response-format
        $response = array(
            'order' => array()
        );

        // Пытаемся создать заказ
        try {
            // Создаем новый заказ в Woocommerce
            $order = new WC_Order();
            $order->set_created_via( TURBOSHOP_ORDERS ); // Указываем как создан заказ

            // Оплата
            $order->set_payment_method_title( $turbo_order[ 'paymentMethod' ] );

            // Добавляем товары
            foreach ( $turbo_order['items'] as $item ) {
                $order->add_product( wc_get_product( $item[ 'offerId' ] ), $item[ 'count' ] );
            }

            // Сохраняем
            $order->save();
            
            // Добавляем комментарий
            $order->add_order_note( __('Создан заказ Яндекс.Турбо', TURBOSHOP_ORDERS ) . '# ' . $turbo_order[ 'id' ], false, false );

            $response[ 'order' ][ 'accepted' ] = true;
            $response[ 'order' ][ 'id' ] = $order->get_id();

        }
        catch ( Exception $ex ) {
            // Возникла ошибка
            $response[ 'order' ][ 'accepted' ] = false;
            $response[ 'order' ][ 'reason' ] = 'INTERNAL_ERROR';
            $response[ 'order' ][ 'message' ] = $ex->getMessage();
        }

        return $response;
    }

    /**
     * Проверка авторизации запроса
     * https://yandex.ru/dev/turbo-shop/doc/settings/shop-api.html#authorization
     * 
     * @param string   $token    Токен авторизации
     */
    public function is_authorized( $token ) {
        // Запросы без токена авторизации не обслуживаются
        if ( empty( $token) ) return false;

        // Проверяем переданный токен
        return $this->settings->get( 'token' ) == $token;
    }    

}