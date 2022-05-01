<?php
/**
 * Основной класс плагина turboshop-orders
 */
namespace Turboshop_orders;
use \Exception  AS Exception;
use \WP_Error AS WP_Error;
use \WC_Order AS WC_Order;
use \WC_Shipping_Rate AS WC_Shipping_Rate;
use \WC_Product AS WC_Product;

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
        
        // Регистрируем конечную точку для обновления заказов
        register_rest_route( 
            TURBOSHOP_ORDERS . '/' . TURBOSHOP_ORDERS_MAJOR_VER,    // Namespace REST API
            '/order/status',                                        // Маршрут
            array(
                'methods'  => 'POST',                                // Метод запроса
                'callback' => array( $this, 'post_order_status' )    // Обработчик
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

        // Получаем заказ
        try {
            // Ответ магазина
            // https://yandex.ru/dev/turbo-shop/doc/settings/order-accept.html#response-format
            $response = array(
                'order' => $this->update_order( 
                    $this->get_order_by_turbo_id( $turbo_order[ 'id' ] ), 
                    $turbo_order,
                    __('Создание заказа Яндекс.Турбо', TURBOSHOP_ORDERS )
                )
            );
        }
        catch ( Exception $ex ) {
            // Возникла ошибка
            $response = array(
                'order' => array(
                    'accepted'  => false,
                    'reason'    => 'INTERNAL_ERROR',
                    'message'   => $ex->getMessage()
                )
            );
        }

        return $response;
    }

 /**
     * Обработчик POST /order/status
     * 
     * @param WP_REST_Request   $request    Объект запроса
     */
    public function post_order_status( $request ) {
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

        // Получаем заказ
        try {
            // Ответ магазина
            // https://yandex.ru/dev/turbo-shop/doc/settings/order-accept.html#response-format
            $response = array(
                'order' => $this->update_order( 
                    $this->get_order_by_turbo_id( $turbo_order[ 'id' ] ), 
                    $turbo_order,
                    __('Обновление заказа Яндекс.Турбо', TURBOSHOP_ORDERS )
                )
            );
        }
        catch ( Exception $ex ) {
            // Возникла ошибка
            $response = array(
                'order' => array(
                    'accepted'  => false,
                    'reason'    => 'INTERNAL_ERROR',
                    'message'   => $ex->getMessage()
                )
            );
        }

        return $response;
    }

    /**
     * Обновление заказа по данным JSON
     * 
     * @param   WC_Order  $order    Заказ WooCommerce
     * @param   mixed     $json     Массив с JSON данными
     * @param   string    $action   Строковое описание действия
     * @return  mixed               Возвращает массив для отправки ответа 
     */
    private function update_order( $order, $json, $action ) {
        // Массив возврата
        $result = array(
            'id'        => '',
            'accepted'  => true
        );

        // Проверка входных данных
        if ( !$order ) throw new Exception( __('Нет заказа для обновления', TURBOSHOP_ORDERS ) );
        if ( !$json  ) throw new Exception( __('Нет JSON данных для обновления', TURBOSHOP_ORDERS ) );

        // Комментарий в заказ
        $order_comment = $action . ' # ' . $json[ 'id' ] . '<br>' . PHP_EOL;

        // Устанавливаем статус заказа
        if ( isset( $json[ 'status' ] ) ) {
            $order->update_status( strtolower( $json[ 'status' ] ));
            $order_comment .= __('Статус', TURBOSHOP_ORDERS ) . ': ' . $json[ 'status' ]  . ' / ' . $json[ 'substatus' ] . '<br>' . PHP_EOL;
        }
      
        // Устанавливаем способ доставки
        if ( isset( $json[ 'delivery' ] ) && isset( $json[ 'delivery' ][ 'price' ] ) ) {
            $shippings = $order->get_shipping_methods();
            if ( 0 == count( $shippings  ) )
            {
                // Добавляем новый способ доставки
                $order->add_shipping( new WC_Shipping_Rate(
                    'yandex_shipping',
                    __('Доставка Яндекс.Турбо', TURBOSHOP_ORDERS ),
                    $this->comma_to_decimal( $json[ 'delivery' ][ 'price' ] )
                ) );                
            }
            else {
                // Обновляем способ доставки
                $shipping = reset( $shippings );    // Первый в списке способ доставки. Он должен быть один в этом заказе
                $order->update_shipping( $shipping, array(
                    'cost' => $this->comma_to_decimal( $json[ 'delivery' ][ 'price' ] )
                ) );
            }
            $order_comment .= __('Доставка', TURBOSHOP_ORDERS ) . ': ' . $json[ 'delivery' ][ 'price' ] . '<br>' . PHP_EOL;
        }

        // Указываем оплату
        if ( isset( $json[ 'paymentType' ] ) ) {
            $order->set_payment_method( $json[ 'paymentType' ] );
            $order_comment .= __('Оплата', TURBOSHOP_ORDERS ) . ': ' . $json[ 'paymentType' ] . '<br>' . PHP_EOL;
        }
        
        // Примечание к заказу
        if ( isset( $json[ 'notes' ] ) ) {
            $order->set_customer_note( $json[ 'notes' ] );
        }

        // Позиции (товары) заказа
        if ( isset( $json[ 'items' ] ) ) {
            // Удалим из заказа все старые позиции
            $order->remove_order_items( 'line_item' );            

            // Товары, полученные в запросе
            foreach ( $json['items'] as $item ) {
                // Добавим товары
                $total = $item[ 'count' ] * $this->comma_to_decimal( $item[ 'price' ] );
                $order->add_product( 
                    new WC_Product( $item[ 'offerId' ] ), 
                    $item[ 'count' ], 
                    array(
                        'total'     => $total,
                        'subtotal'  => $total, 
                        'quantity'  => $item[ 'count' ] 
                    )
                );                
            }
        }

        // Покупатель
        if ( isset( $json[ 'buyer' ] ) ) {
            $order->set_billing_phone( $json[ 'buyer' ][ 'phone' ] );
            $order->set_billing_email( $json[ 'buyer' ][ 'email' ] );
            if ( isset( $json[ 'buyer' ][ 'name' ] ) ) $order->set_billing_last_name( $json[ 'buyer' ][ 'name' ] );
            if ( isset( $json[ 'buyer' ][ 'lastName' ] ) ) $order->set_billing_last_name( $json[ 'buyer' ][ 'lastName' ] );
            if ( isset( $json[ 'buyer' ][ 'firstName' ] ) ) $order->set_billing_first_name( $json[ 'buyer' ][ 'firstName' ] );
        }  

        // Сохраняем
        $order->save();
        
        // Добавляем комментарий в заказ
        $order->add_order_note( $order_comment, false, false );

        // ID обработанного заказа
        $result[ 'id' ] = $order->get_id();

        // Вернем результат
        return $result;
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

    /**
     * Мета-поле ID заказа Яндекс.Турбо
     */
    const TURBO_ORDER_ID = 'turbo_order_id';

    /**
     * Метод находит и при необходимости создает новый заказ WooCommerce по ID заказа в Яндекс.Турбо
     * https://yandex.ru/dev/turbo-shop/doc/settings/shop-api.html#authorization
     * 
     * @param string   $turbo_id    ID заказа в Яндекс.Турбо
     * @return WC_Order             Объект WC_Order
     */
    private function get_order_by_turbo_id( $turbo_id ) {
        // Проверяем ID
        if ( empty( $turbo_id ) ) throw new Exception( __('Не передан ID заказа Яндекс.Турбо', TURBOSHOP_ORDERS ) );
        
        // Заказ
        $order = null;

        // Ищем заказ
        $posts = get_posts(array(
            'numberposts'   => 1,
            'post_type'     => 'shop_order',
            'post_status'   => 'any',           // ЭТО ОБЯЗАТЕЛЬНО !!!
            'meta_key'      => self::TURBO_ORDER_ID,
            'meta_value'    => $turbo_id
        ));

        // Если заказ не найден...
        if ( count( $posts ) == 0 ) {
            // Создаем новый заказ в Woocommerce
            $order = new WC_Order();
            // Указываем как создан заказ
            $order->set_created_via( TURBOSHOP_ORDERS );         
            // Сохраним заказ
            $order->save();
            // Ставим ему мета-поле
            update_post_meta( $order->get_id(), self::TURBO_ORDER_ID, $turbo_id );
        }
        else {
            // Берем заказ WooCommerce по найденному ID
            $order = wc_get_order( $posts[0]->ID );
        }

        // Возвращаем заказ
        return $order;
    }

    /**
     * Преобразует запятую в точку в числах REST
     * 
     * @param string    $value  Число, переданное в REST
     * @return float            Нормальное число
     */
    private function comma_to_decimal( $value ) {
        return (float) str_replace(',', '.', $value );
    }
}