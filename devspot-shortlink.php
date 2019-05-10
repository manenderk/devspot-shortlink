<?php
/*
Plugin Name: Devspot Shortlink
Plugin URI: 
Description: A plugin to create short links
Author: Manender Kumar
Author URI:
Version: 0.0.1
*/

include 'shortlink.php';

global $my_nonce;


function generateDB(){
    try{
        $devspot_shortlink_db_version = "0.0.5";
        $installed_ver = get_option("devspot_shortlink_db_version");
        if($installed_ver != $devspot_shortlink_db_version){
            global $wpdb;
            $table_name = $wpdb->prefix . "devspot_shortlinks";
            $user_table_name = $wpdb->prefix . "users";
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                userId bigint(20) UNSIGNED NOT NULL,
                shortLink varchar(20) NOT NULL,
                redirectLink varchar(500) NOT NULL,
                created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                PRIMARY KEY  (id),
                FOREIGN KEY (userId) REFERENCES $user_table_name(ID)
            ) $charset_collate;";
    
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );             
            update_option('devspot_shortlink_db_version', $devspot_shortlink_db_version);
        }
    }
    catch (Exception $e){
        error_log("Devspot Shortlink $e");
    }    
}

function activate_devspot_shortlink() {
    generateDB();
}
register_activation_hook( __FILE__, 'activate_devspot_shortlink' );


function do_stuff(){
        
}

function localize_variables(){
    wp_localize_script( 'devspot-script', 'wpApiSettings', array(
        'root' => esc_url_raw( rest_url() ),
        'nonce' => wp_create_nonce( 'wp_rest' )
    ) );
}
add_action('wp_enqueue_scripts', 'localize_variables', 10000);

function devspot_add_shortlink($args) {
    $response = [
        'message' => '',
        'status' => 'error'
    ];

    $data = $args->get_params();
    $shortLink = new Shortlink();
    if(empty($shortLink->__get('userId'))){
        $response['message'] = 'invalid user';
        $response['status'] = 'error';
    }
    else{
        $shortLink->__set('redirectLink', $data['redirectLink']);
        global $wpdb;
        $table_name = $wpdb->prefix . "devspot_shortlinks";
        $wpdb->insert( 
            $table_name, 
            array( 
                'userId' => $shortLink->__get('userId'),
                'shortLink' => $shortLink->__get('shortLink'), 
                'redirectLink' => $shortLink->__get('redirectLink'), 
                'created' => $shortLink->__get('created') 
            ),array(
                '%d',
                '%s',
                '%s',
                '%s'
            ) 
        );
        if(!empty($wpdb->insert_id)){
            $response['message'] = 'created';
            $response['status'] = 'success';
        }
        else{
            $response['message'] = 'insert error';
            $response['status'] = 'error';
        }
    }
    $response = new WP_REST_Response( $response );
    return $response; 
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'dshortlink/v1', '/add-shortlink', array(
		'methods' => 'POST',
		'callback' => 'devspot_add_shortlink',
	));
});


function devspot_get_shortlink($args) {
    $response = [
        'message' => '',
        'status' => 'error'
    ];

    $current_user = wp_get_current_user();
    $userId = $current_user->ID;

    if(empty($userId)){
        $response['message'] = 'invalid user';
        $response['status'] = 'error';
    }
    else{
        global $wpdb;
        $table_name = $wpdb->prefix . "devspot_shortlinks";
        $shortlinks = $wpdb->get_results("SELECT * FROM $table_name WHERE userId = $userId ORDER BY created DESC", ARRAY_A);
        $response['message'] = $shortlinks;
        $response['status'] = 'success';
    }

    $response = new WP_REST_Response( $response );
    return $response; 
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'dshortlink/v1', '/get-shortlinks', array(
		'methods' => 'GET',
		'callback' => 'devspot_get_shortlink',
	));
});

function devspot_shortlink_redirect(){
    global $wp;
    $url = $wp->request;
    if(strpos($url, "dt") === 0 && strlen($url)<= 8){
        echo 'this is a shortlink';
        exit;
    }
}
add_action('wp', 'devspot_shortlink_redirect');