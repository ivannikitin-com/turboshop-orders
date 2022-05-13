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
     * Мета-данные плагина
     * @var mixed
     */
    static $meta;

    /**
     * Параметры плагина в виде ассоциативного массива
     * @var mixed
     */
    private $settings;

    /**
     * Конструктор класса
     * 
     * @param mixed $meta   Массив мета-данных плагина 
     */
    public function __construct( $meta ) {
        // Сохраняем мета-данные
        self::$meta = $meta;

        // Хуки
        add_action( 'init',             array( $this, 'init' ) );
        add_action( 'rest_api_init',    array( $this, 'rest_api_init' ) );
        add_filter( 'manage_edit-shop_order_columns', array( $this, 'custom_shop_order_column' ), 20 );
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'custom_orders_list_column_content' ), 20, 2 );        
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
            TURBOSHOP_ORDERS . '/' . TURBOSHOP_ORDERS_API_VER,      // Namespace REST API
            '/order/accept',                                        // Маршрут
            array(
                'methods'  => 'POST',                               // Метод запроса
                'callback' => array( $this, 'post_order_accept' )   // Обработчик
            )
        );
        
        // Регистрируем конечную точку для обновления заказов
        register_rest_route( 
            TURBOSHOP_ORDERS . '/' . TURBOSHOP_ORDERS_API_VER,      // Namespace REST API
            '/order/status',                                        // Маршрут
            array(
                'methods'  => 'POST',                               // Метод запроса
                'callback' => array( $this, 'post_order_status' )   // Обработчик
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
                    __('Создание заказа Яндекс.Турбо', TURBOSHOP_ORDERS ),
                    true   // Список товаров в заказе необходимо создать
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
                    __('Обновление заказа Яндекс.Турбо', TURBOSHOP_ORDERS ), 
                    false   // Список товаров в заказе НЕ ОБНОВЛЯТЬ
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
     * @param   WC_Order  $order            Заказ WooCommerce
     * @param   mixed     $json             Массив с JSON данными
     * @param   string    $action           Строковое описание действия
     * @param   bool      $update_products  Если true, то товары, переданные в JSON, записываются в заказ, 
     *                                      предварительно все существующие в заказе товары стираются.
     *                                      Если false, товары в JSON игнорируются. 
     * @return  mixed                       Возвращает массив для отправки ответа
     */
    private function update_order( $order, $json, $action, $update_products ) {
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
        if ( isset( $json[ 'paymentType' ] ) ) {
            switch ( $json[ 'paymentType' ] ) {
                case 'PREPAID':
                    $order_status = 'wc-processing';
                    break;

                case 'POSTPAID':
                    $order_status = 'wc-pending';
                    break;

                default:
                    $order_status = 'wc-on-hold';
                    break;
            }
            $order->update_status( apply_filters( 'turboshop_orders_update_status', $order_status, $order, $json ) );
            $order_comment .= __('Статус', TURBOSHOP_ORDERS ) . ': ' . $json[ 'status' ]  . ' / ' . $json[ 'substatus' ] . '<br>' .
                __('Тип оплаты', TURBOSHOP_ORDERS ) . ': ' . $json[ 'paymentType' ] . '<br>' .PHP_EOL;
        }
      
        // Устанавливаем способ доставки
        if ( isset( $json[ 'delivery' ] ) && isset( $json[ 'delivery' ][ 'price' ] ) ) {
            $shippings = $order->get_shipping_methods();
            if ( 0 == count( $shippings  ) )
            {
                // Добавляем новый способ доставки
                $shipping_rate = new WC_Shipping_Rate(
                    'yandex_shipping',
                    __('Доставка Яндекс.Турбо', TURBOSHOP_ORDERS ),
                    $this->comma_to_decimal( $json[ 'delivery' ][ 'price' ] )
                );
                $order->add_shipping( apply_filters( 'turboshop_orders_add_shipping', $shipping_rate, $order, $json ) );                
            }
            else {
                // Обновляем способ доставки
                $shipping_rate = reset( $shippings );    // Первый в списке способ доставки. Он должен быть один в этом заказе
                $order->update_shipping( $shipping_rate, array(
                    'cost' => apply_filters( 'turboshop_orders_update_shipping_cost', $this->comma_to_decimal( $json[ 'delivery' ][ 'price' ] ), $order, $json )
                ) );
            }
            $order_comment .= __('Доставка', TURBOSHOP_ORDERS ) . ': ' . $json[ 'delivery' ][ 'price' ] . '<br>' . PHP_EOL;
        }

        // Указываем оплату
        if ( isset( $json[ 'paymentMethod' ] ) ) {
            $order->set_payment_method( apply_filters( 'turboshop_orders_set_payment_method', $json[ 'paymentMethod' ], $order, $json )  );
            $order_comment .= __('Метод оплаты', TURBOSHOP_ORDERS ) . ': ' . $json[ 'paymentMethod' ] . '<br>' . PHP_EOL;
        }
        
        // Примечание к заказу
        if ( isset( $json[ 'notes' ] ) ) {
            $order->set_customer_note( apply_filters( 'turboshop_orders_set_customer_note', $json[ 'notes' ], $order, $json ) );
        }

        // Позиции (товары) заказа
        if ( $update_products && isset( $json[ 'items' ] ) ) {
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
            $order->set_billing_phone( apply_filters( 'turboshop_orders_set_billing_phone', $json[ 'buyer' ][ 'phone' ], $order, $json ) );
            $order->set_billing_email( apply_filters( 'turboshop_orders_set_billing_email', $json[ 'buyer' ][ 'email' ], $order, $json ) );

            $name = apply_filters( 'turboshop_orders_set_name', ( isset( $json[ 'buyer' ][ 'name' ] ) ) ? $json[ 'buyer' ][ 'name' ] : '', $order, $json );
            if ( !empty( $name) ) $order->set_billing_last_name( $name );

            $last_name = apply_filters( 'turboshop_orders_set_last_name', ( isset( $json[ 'buyer' ][ 'lastName' ] ) ) ? $json[ 'buyer' ][ 'lastName' ] : '', $order, $json );
            if ( !empty( $last_name) ) $order->set_billing_last_name( $last_name );

            $first_name = apply_filters( 'turboshop_orders_set_first_name', ( isset( $json[ 'buyer' ][ 'firstName' ] ) ) ? $json[ 'buyer' ][ 'firstName' ] : '', $order, $json );
            if ( !empty( $first_name) ) $order->set_billing_first_name( $first_name );
        }

        // Сохраняем
        $order->calculate_totals();
        $order->save();        

        // Добавляем комментарий в заказ
        $order->add_order_note( apply_filters( 'turboshop_orders_add_order_note', $order_comment, $order, $json ), false, false );

        // ID обработанного заказа
        $result[ 'id' ] = apply_filters( 'turboshop_orders_result_order_id', $order->get_id(), $order, $json );

        // Если выключена отладка и это фейковый заказ, сразу удаляем его
        if ( !WP_DEBUG && isset( $json[ 'fake' ] ) && 'true' == $json[ 'fake' ] ) {
            $order->delete( false );
        }        

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

    /**
     * Функция Добавляет новую колонку в список заказов
     * 
     * @param mixed    $columns    Массив колонок
     */ 
    public function custom_shop_order_column( $columns ) {
        $new_columns = array();

        // Добавляем колонку ПОСЛЕ order_total
        foreach( $columns as $key => $column){
            $new_columns[$key] = $column;
            if( $key ==  'order_total' ){
                // Новая колонка
                $new_columns[ 'turbo_order_id' ] = __('Заказ в Яндекс.Турбо', TURBOSHOP_ORDERS );
            }
        }
        return $new_columns;
    }

    /**
     * Функция выводит данные в новую колонку в списке заказов
     * 
     * @param string    $column     ID колонки
     * @param int       $post_id    ID заказа
     */ 
    function custom_orders_list_column_content( $column, $post_id ) {
        if ( 'turbo_order_id' == $column ) {
            echo get_post_meta( $post_id, self::TURBO_ORDER_ID, true );
        }
    }
}