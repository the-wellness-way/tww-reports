<?php
/**
 * Plugin Name: TWW Reports
 * 
 * Description: Custom reports for TWW, which tallies up the number of WooCommerce orders that have the processing
 * or completeed status - all viewable in a get request.
 */
class TWW_Reports {
    const NAMESPACE = 'tww-reports/v1';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        $routes = [
            'orders' => [
                'path' => '/orders',
                'methods' => 'GET',
                'callback' => [$this, 'get_orders'],
                'permission_callback' => '__return_true'
            ],
            'last_three_months' => [
                'path' => '/last-three-months',
                'methods' => 'GET',
                'callback' => [$this, 'generate_reports_from_last_three_months_by_month'],
                'permission_callback' => '__return_true'
            ],
            'last_six_months' => [
                'path' => '/last-six-months',
                'methods' => 'GET',
                'callback' => [$this, 'generate_reports_from_last_six_months'],
                'permission_callback' => '__return_true'
            ],
            'luncheon_orders' => [
                'path' => '/luncheon-orders',
                'methods' => 'GET',
                'callback' => [$this, 'get_luncheon_orders'],
                'permission_callback' => '__return_true'
            ]
        ];

        foreach($routes as $route => $args) {
            register_rest_route(self::NAMESPACE, $args['path'], [
                'methods' => $args['methods'],
                'callback' => $args['callback'],
                'permission_callback' => $args['permission_callback']
            ]);
        }
    }

    public function generate_reports_from_last_three_months_by_month() {
        $query = new WC_Order_Query([
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'date_paid' => '>=' . date('Y-m-d', strtotime('-3 months'))
        ]);

        $orders = $query->get_orders();

        $report = [];

        foreach($orders as $order) {
            $month = date('F', strtotime($order->get_date_paid()));
            $report[$month] = $report[$month] ?? 0;
            $report[$month]++;
        }

        return rest_ensure_response($report);
    }

    public function generate_reports_from_last_six_months() {
        $query = new WC_Order_Query([
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $orders = $query->get_orders();

        $report = [];

        foreach($orders as $order) {
            $month = date('F', strtotime($order->get_date_paid()));
            $report[$month] = $report[$month] ?? 0;
            $report[$month]++;
        }

        return rest_ensure_response($report);
    }

    // We want to turn this into a REST API endpoint
    public function get_luncheon_orders(\WP_REST_Request $request) {
        $params = $request->get_params();
        
        $add_on_title = $params['add_on_title'] ?? 'VIP Luncheon (Optional) - Limited Spots Available';
        $add_on_key = $add_on_title;
        $add_on_option = $params['add_on_option'] ?? 'Yes';
        //turn this date into gmt
        $start_date = $params['start_date'] ?? '2018-01-01';
        $end_date = $params['end_date'] ?? '2024-12-31';

        

        global $wpdb;

        $order_items = [
            'In Person Ticket' => [
                "total" => 0,
                "add_ons" => [
                    'VIP Luncheon (Optional)' => [
                        'Yes' => 0,
                    ]
                    
                ]
            ]
        ];

        $order_items['In Person Ticket']['add_ons'][$add_on_key][$add_on_option] = 0;

        $order_item_name = $params['order_item_name'] ?? 'In-Person Ticket';
        $start_date = date('Y-m-d H:i:s', strtotime($start_date));
        $end_date = date('Y-m-d H:i:s', strtotime($end_date));
        $query = $wpdb->prepare("
            SELECT items.*, orders.date_created_gmt 
            FROM {$wpdb->prefix}woocommerce_order_items AS items
            INNER JOIN {$wpdb->prefix}wc_orders AS orders ON items.order_id = orders.ID
            WHERE items.order_item_name = %s
            AND orders.date_created_gmt BETWEEN %s AND %s
            AND orders.status = 'wc-processing'
        ", $order_item_name, $start_date, $end_date);


        foreach($wpdb->get_results($query) as $result) {
            $order_items['In Person Ticket']['total']++;
            $order_item_id = $result->order_item_id;
            
        
            global $wpdb;

            $query = $wpdb->prepare("SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = %d AND meta_key = %s", $order_item_id, $add_on_key);
    

            $result = $wpdb->get_results($query);
            if($result) {
                $order_item_meta = $result[0]->meta_value;

                if($order_item_meta == $add_on_option) {
                    $order_items['In Person Ticket']['add_ons'][$add_on_key][$add_on_option]++;
                }
            }    
        }

        if($order_items) {
            return rest_ensure_response($order_items);
        } else {
            return new WP_Error('no_orders', 'No orders found', ['status' => 404]);
        }
    }
}



if(class_exists('TWW_Reports')) {
    new TWW_Reports();
}   