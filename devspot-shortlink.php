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

define('SHORTLINK_TABLE', 'devspot_shortlinks');
define('USER_TABLE', 'users');
define('SHORTLINK_STATS_TABLE', 'devspot_shortlink_stats');
define('PLUGIN_VER', '0.0.2');

global $my_nonce;

function get_client_ip() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function createTables(){
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    global $wpdb;
    
    $shortlinkTable = $wpdb->prefix . SHORTLINK_TABLE;
    $user_table_name = $wpdb->prefix . USER_TABLE;
    $shortlinkStatsTable = $wpdb->prefix . SHORTLINK_STATS_TABLE;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $shortlinkTable (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        userId bigint(20) UNSIGNED NOT NULL,
        shortLink varchar(20) NOT NULL,
        redirectLink varchar(500) NOT NULL,
        created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id),
        FOREIGN KEY (userId) REFERENCES $user_table_name(ID) ON DELETE CASCADE
    ) $charset_collate;";    
    dbDelta( $sql );      
    
    $sql = "CREATE TABLE $shortlinkStatsTable (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        shortLinkId bigint(20) UNSIGNED NOT NULL,
        referer varchar(50) NOT NULL,
        country varchar(20) NOT NULL,
        visited datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id),
        FOREIGN KEY (shortLinkId) REFERENCES $shortlinkTable(id) ON DELETE CASCADE
    ) $charset_collate;";
    dbDelta( $sql );          
}

function upgradeTables(){
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    global $wpdb;
    
    $shortlinkTable = $wpdb->prefix . SHORTLINK_TABLE;
    $user_table_name = $wpdb->prefix . USER_TABLE;
    $shortlinkStatsTable = $wpdb->prefix . SHORTLINK_STATS_TABLE;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $shortlinkTable (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        userId bigint(20) UNSIGNED NOT NULL,
        shortLink varchar(20) NOT NULL,
        redirectLink varchar(500) NOT NULL,
        created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id)        
    ) $charset_collate;";    
    dbDelta( $sql );      
    
    $sql = "CREATE TABLE $shortlinkStatsTable (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        shortLinkId bigint(20) UNSIGNED NOT NULL,
        referer varchar(50) NOT NULL,
        country varchar(20) NOT NULL,
        visited datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta( $sql );       
}

function operateDB(){
    try{
        $installed_ver = get_option("devspot_shortlink_db_version");
        if($installed_ver === false){
            createTables();
        }
        else if($installed_ver != PLUGIN_VER){
            upgradeTables();
        }
    }
    catch (Exception $e){
        error_log("Devspot Shortlink $e");
    }
}

function activate_devspot_shortlink() {
    operateDB();
    update_option('devspot_shortlink_db_version', PLUGIN_VER);
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
        $table_name = $wpdb->prefix . SHORTLINK_TABLE;
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
    global $wpdb;
    $url = $wp->request;
    if(strpos($url, "dt") === 0 && strlen($url)<= 8){
        $shortlinkTable = $wpdb->prefix . SHORTLINK_TABLE;
        $shortlinkStatsTable = $wpdb->prefix . SHORTLINK_STATS_TABLE;
        $shortlink = $wpdb->get_row( "SELECT * FROM $shortlinkTable WHERE shortLink = '$url'" );
        if($shortlink != NULL){
            $referer = 'direct';
            if(!empty($_SERVER['HTTP_REFERER']))
                $referer = $_SERVER['HTTP_REFERER'];
            
            $ip = get_client_ip();

            $response = wp_remote_get("http://api.ipstack.com/$ip?access_key=e779deaa5f080abaf4d5feb40da9800f&format=1");
            $response = json_decode($response['body']);
            $response = (array)$response;
            $country = $response['country_name'];
            if($country === NULL)
                $country = '';
            
            $wpdb->insert( 
                $shortlinkStatsTable, 
                array( 
                    'shortLinkId' => $shortlink->id,
                    'referer' => $referer, 
                    'country' => $country, 
                    'visited' => current_time('mysql', 1) 
                ),array(
                    '%d',
                    '%s',
                    '%s',
                    '%s'
                ) 
            );
            wp_redirect($shortlink->redirectLink);
        }
    }
}
add_action('wp', 'devspot_shortlink_redirect');

function devspot_get_stats($args) {
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
        $shortlinkTable = $wpdb->prefix . SHORTLINK_TABLE;
        $shortlinkStatsTable = $wpdb->prefix . SHORTLINK_STATS_TABLE;
        $shortlinkStatsByName = $wpdb->get_results("SELECT count($shortlinkStatsTable.shortLinkId) AS clicks, $shortlinkTable.shortLink from $shortlinkStatsTable, $shortlinkTable WHERE $shortlinkStatsTable.shortLinkId = $shortlinkTable.id AND $shortlinkTable.userId = $userId GROUP BY $shortlinkStatsTable.shortLinkId", ARRAY_A);
        $shortlinkTotalClicks = $wpdb->get_results("SELECT COUNT($shortlinkStatsTable.shortLinkId) as clicks FROM $shortlinkStatsTable, $shortlinkTable WHERE $shortlinkStatsTable.shortLinkId = $shortlinkTable.id AND $shortlinkTable.userId = $userId", ARRAY_A);
        $shortlinkStatsByCountry = $wpdb->get_results("SELECT count($shortlinkStatsTable.shortLinkId) as clicks, $shortlinkStatsTable.country from $shortlinkStatsTable, $shortlinkTable WHERE $shortlinkStatsTable.shortLinkId = $shortlinkTable.id AND $shortlinkTable.userId = $userId GROUP BY $shortlinkStatsTable.country", ARRAY_A);
        $shortlinkStatsByReferrer = $wpdb->get_results("SELECT count($shortlinkStatsTable.shortLinkId) as clicks, $shortlinkStatsTable.referer from $shortlinkStatsTable, $shortlinkTable WHERE $shortlinkStatsTable.shortLinkId = $shortlinkTable.id AND $shortlinkTable.userId = $userId GROUP BY $shortlinkStatsTable.referer", ARRAY_A);
        $response['message'] = [
            'totalClicks'   => $shortlinkTotalClicks,
            'byClicks'      => $shortlinkStatsByName,
            'byCountry'     => $shortlinkStatsByCountry,
            'byReferer'     => $shortlinkStatsByReferrer
        ];
        $response['status'] = 'success';
    }

    $response = new WP_REST_Response( $response );
    return $response; 
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'dshortlink/v1', '/get-stats', array(
		'methods' => 'GET',
		'callback' => 'devspot_get_stats',
	));
});

