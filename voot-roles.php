<?php
/*
    Plugin Name: VOOT Roles
    Plugin URI: https://github.com/fkooman/wordpress-voot-role-plugin/
    Description: Sets the role of the user based on VOOT group memberships.
    Author: François Kooman <fkooman@tuxed.net>
    Version: 0.1
    Author URI: http://fkooman.wordpress.com/
 */

require_once 'extlib/php-oauth-client/lib/OAuthTwoPdoCodeClient.php';

global $vr_db_version;
$vr_db_version = "1.0";

add_action('wp_login', 'vr_set_user_role', 10, 2);
add_action('auth_cookie_valid', 'vr_handle_authorization_code_response', 10, 2);

register_activation_hook(__FILE__,'vr_install');

function vr_handle_authorization_code_response($cookie_elements, WP_User $user)
{
    error_log("ACTION: auth_cookie_valid");

    if (array_key_exists("state", $_GET)) {
        if (array_key_exists("code", $_GET)) {
            // authorization code available, continue with OAuth token fetching
            vr_set_user_role($user->user_login, $user);
        } elseif (array_key_exists("error", $_GET)) {
            // FIXME: figure out how to throw nice error, maybe some Wordpress exception?
            if (array_key_exists("error_description", $_GET)) {
                $error = "ERROR (" . $_GET["error"] . "): " . $_GET['error_description'];
            } else {
                $error = "ERROR (" . $_GET["error"] . ")";
            }
            error_log($error);
            die($error);
        }
    }
}

function vr_set_user_role($username, WP_User $user)
{
    global $wpdb;

    error_log("ACTION: wp_login");
   
    // determine where the user wants to go after logging in...
    $returnUri = NULL;
    if(array_key_exists("HTTP_REFERER", $_SERVER)) {
        $referrer = $_SERVER['HTTP_REFERER'];
        $query = parse_url($referrer, PHP_URL_QUERY);
        if(FALSE !== $query && NULL !== $query) {
            parse_str($query, $queryArray);
            if(is_array($queryArray) && !empty($queryArray)) { 
                if(array_key_exists("redirect_to", $queryArray)) {
                    $returnUri = urldecode($queryArray["redirect_to"]);
                }
            }
        }
    }
    if(NULL === $returnUri) {
        $returnUri = admin_url();
    }
    error_log("returnUri: $returnUri");

    $config = parse_ini_file("config/config.ini");
    $groups = array();
    try {
	$config += array(
            'PdoDsn' => 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
            'PdoUser' => DB_USER,
            'PdoPass' => DB_PASSWORD,
            'PdoPersistentConnection' => FALSE,
            'DbPrefix' => $wpdb->prefix,
        );
        $client = new OAuthTwoPdoCodeClient($config);
        $client->setLogFile(__DIR__ . "/data/log.txt");
        $client->setScope("read");
        $client->setResourceOwnerId($user->ID); // use Wordpress userId
        $client->setReturnUri($returnUri);
        $response = $client->makeRequest($config['apiEndpoint'] . "/groups/@me");
        $response = json_decode($response, TRUE);
        $groups = $response['entry'];
    } catch (OAuthTwoPdoCodeClientException $e) {
        // FIXME: figure out how to throw nice error, maybe some Wordpress exception?
        $error = "ERROR (" . $e->getMessage() . ")";
        error_log($error);
        die($error);
    }

    // FIXME: is there a way to enumerate all possible roles?
    // Yes there is: use WP_Roles class
    if (vr_is_member_of($config['administratorRoleGroup'], $groups)) {
        $role = "administrator";
    } elseif (vr_is_member_of($config['editorRoleGroup'], $groups)) {
        $role = "editor";
    } elseif (vr_is_member_of($config['authorRoleGroup'], $groups)) {
        $role = "author";
    } elseif (vr_is_member_of($config['contributorRoleGroup'], $groups)) {
        $role = "contributor";
    } else {
        // everyone who succesfully authenticates will become a subscriber
        $role = "subscriber";
    }

    if (!in_array($role, $user->roles) ) {
        $user->set_role($role);
        wp_update_user(array('ID' => $user->ID, 'role' => $role));
    }

    // FIXME: maybe we should return TRUE or FALSE?!
    return;
}

function vr_is_member_of($group, array $groups)
{
    foreach ($groups as $g) {
        if ($g['id'] === $group) {
            return TRUE;
        }
    }

    return FALSE;
}

function vr_install() 
{
    global $wpdb;
    global $vr_db_version;

    $tokens_table_name = $wpdb->prefix . "oauth2_tokens";
    $states_table_name = $wpdb->prefix . "oauth2_states";
  
    $tokens_sql = "CREATE TABLE $tokens_table_name (
        access_token VARCHAR(64) NOT NULL,
        resource_owner_id VARCHAR(64) NOT NULL,
        issue_time INT(11) NOT NULL,
        expires_in INT(11) NOT NULL,
        scope TEXT NOT NULL,
        refresh_token TEXT DEFAULT NULL,
        PRIMARY KEY (access_token)
    )";

    $states_sql = "CREATE TABLE $states_table_name (
        state VARCHAR(64) NOT NULL,
        request_uri TEXT NOT NULL,
        PRIMARY KEY (state)
    )";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($tokens_sql);
    dbDelta($states_sql);
 
    add_option("vr_db_version", $vr_db_version);
}
