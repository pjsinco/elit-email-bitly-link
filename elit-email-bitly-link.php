<?php 

require_once ( 'vendor/autoload.php' );

/**
 * Plugin Name: Elit Email Bitly Link
 * Plugin URI: https://github.com/pjsinco/elit-email-bitly-link
 * Description: Emails a bitly link of a just-published post
 * Version: 1.0.3
 * Author: Patrick Sinco
 * Author URI: https://github.com/pjsinco
 * License: GPL2
 */

/**
 * TODO
 * -- get rid of the config file; on a settings page, let user enter 
 *      1. email recipients
 *      2. bitly token
 */

if ( !defined( 'WPINC' ) ) {
  die;
}

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

require_once( plugin_dir_path( __FILE__ ) .  'config.php' );
require_once( plugin_dir_path( __FILE__ ) .  'inc/http-build-url.php' );

define( 'ELIT_BITLY_API_URL', 'https://api-ssl.bitly.com' );
define( 'ELIT_EMAIL_SUBJECT', 'New story posted on The DO' );
define( 'ELIT_EMAIL_HEADERS', 'Content-Type: text/plain' );
define( 'MIN_POST_ID', 179511 ); // the last post ID on old site



/**
 * Emails a bitly short link to the specified recipients
 * when a new post is published.
 *
 * @param string $new_status - the post's lastest status
 * @param string $old_status - the post's previous status
 * @param WP_Post $post - the post object
 *
 * @return boolean - whether the email was sent
 */
function elit_email_bitly_link( $new_status, $old_status, $post ) {

  $logger = new Logger( 'elit_bitly_logger' );
  $logger->pushHandler( new StreamHandler( plugin_dir_path( __FILE__ ) . 'log.txt' ), Logger::DEBUG  );
  $logger->addInfo( 'Post ID', array( $post->ID ) ) ;


  if ( get_post_type( $post )  !=  'post'  ||
    $post->ID <= MIN_POST_ID ) {
    return false;
  }
  
  $msg = $new_status . ', ' . $old_status . ', ' . $post->post_title;

  if ( elit_post_is_newly_published( $new_status, $old_status ) ) {

    $post_title = wp_kses_decode_entities( get_the_title( $post->ID ) );
    $request_url = elit_url_for_bitly_link_save_request( $post->ID, $post_title );
    $logger->addInfo( 'Request URL', array( $request_url ) );
    $response = wp_remote_get( $request_url );

    $logger->addInfo( $response );
    
    if ( $response ) {
      elit_send_email( $response, $post );
    } else {
      return false;
    }

  } else {
    return false;
  }
}
add_action( 'transition_post_status' , 'elit_email_bitly_link', 10, 3 );


/**
 * TODO
 *   -- let the user choose the recipients on a settings page
 * Send an email to recipients
 * Recipients are defined in the config file.
 * 
 * @return boolean - whether the email was sent
 */
function elit_send_email( $response, $post_title ) {
  $success = false;
  if ( empty( $response ) === FALSE ) {

    $bitly_link = elit_get_bitly_link_from_response( $response );

    if ( $bitly_link ) {
      $msg = elit_get_email_message( $bitly_link, $post_title );

      $recips = array( 
        'schaney@osteopathic.org',
        'rraymond@osteopathic.org',
        'aaltus@osteopathic.org',
        'vmartinka@osteopathic.org',
        'spalikuca@osteopathic.org',
        'bjohnson@osteopathic.org',
        'lselby@osteopathic.org',
        'psinco@osteopathic.org',
      );
      $success = wp_mail( 
        $recips, ELIT_EMAIL_SUBJECT, $msg, ELIT_EMAIL_HEADERS 
      );
    }  
  }

  return $success;

}

/**
 * Generates the text for the email message.
 * 
 * @param string $link - the bitly link to include in the message
 * @return string - the email message, with the link
 */
function elit_get_email_message( $link, $post ) {
  $post_title = wp_kses_decode_entities( get_the_title( $post->ID ) );

  // for now
  $message  = '';
//  $message .= elit_beta_notification();

  $message .= elit_new_post_string() . PHP_EOL . PHP_EOL;

  $message .= $post_title . PHP_EOL;
  $message .= get_the_permalink( $post->ID ) . PHP_EOL . PHP_EOL . PHP_EOL;

  $message .= '- - - - - - -     KICKER     - - - - - - -' . PHP_EOL;
  $message .= elit_get_kicker( $post->ID ). PHP_EOL . PHP_EOL . PHP_EOL;

  $message .= '- - - - - - -     EXCERPT    - - - - - - -' . PHP_EOL;
  $message .= $post->post_excerpt . PHP_EOL . PHP_EOL . PHP_EOL;

  $message .= '- - - - - - -     BITLY      - - - - - - -' . PHP_EOL;
  $message .= $link . PHP_EOL . PHP_EOL . PHP_EOL;

  return $message;
}

/**
 * Fetches the post's kicker 
 * 
 * @param string id - the post's ID
 * @return string - the post's kicker
 */
function elit_get_kicker( $id ) {
  $meta = get_post_meta( $id );
  if ( empty( $meta['elit_kicker'] ) === false ) {
    return strtoupper( $meta['elit_kicker'][0] );
  } else {
    return false;

  }
}

/**
 * Grabs the bitly link out of a returned value from 
 * a wp_remote_get() to the bitly API
 *
 * @param array $response - the return value of a wp_remote_get()-wrapped 
 *    request to the bitly API
 * @return string - the bitly link for
 */
function elit_get_bitly_link_from_response( $response ) {
  if ( ! is_wp_error(  $response ) ) {
    $bitly_resp = json_decode( $response['body'], true );

    $logger = new Logger( 'elit_bitly_logger' );
    $logger->pushHandler( new StreamHandler( plugin_dir_path( __FILE__ ) . 'log.txt' ), Logger::DEBUG  );
    $logger->addInfo( print_r( $bitly_resp, true ) ) ;

    return $bitly_resp['data']['link_save']['link'];
  }
  
  return false;
}


/**
 * Generates the url for the request to bitly.
 * 
 * @param string $post_title - the title of the post
 */
function elit_url_for_bitly_link_save_request( $post_id, $post_title, $token = ELIT_BITLY_TOKEN ) {
  $parts = array( 
    'path' => '/v3/user/link_save',
    'query' => elit_query_string_for_link_save( $post_id, $post_title, $token ),
  );

  return http_build_url( ELIT_BITLY_API_URL, $parts );
}

/**
 * Generates the query string for a bitly link_save request.
 * See:
 *   http://dev.bitly.com/links.html#v3_user_link_save
 * 
 * @param string $post_title - the title of the post
 * @param string $bitly_token - the bitly access token
 * @return string - the url-encoded query string for the URL
 *
 */
function elit_query_string_for_link_save( $post_id, $post_title,
    $bitly_token = ELIT_BITLY_TOKEN ) {
  $data = array(
  'access_token' => $bitly_token,
  'title' => $post_title,
  'longUrl' => elit_format_article_for_url( $post_id ),
  );
  
  return http_build_query( $data );
}

function elit_format_article_for_url( $post_id ) {

  $args = array( 
    'query' => http_build_query( array( 
        'p' => $post_id
       ) ),
  );

  $url = http_build_url( site_url() . '/', $args );
  return $url;
  
}

/**
 * Determines whether the a post has just been published.
 * 
 * @param string $new_status - the post's lastest status
 * @param string $old_status - the post's previous status
 * @return boolean - whether the post is newly published
 */
function elit_post_is_newly_published( $new_status, $old_status ) {
  if ( 'publish' !== $new_status || $new_status === $old_status ||
  empty( $new_status ) || empty( $old_status ) ) {
    return false;
  }
  
  return true;
}

/**
 * Makes a fancy heading for the email
 * 
 * @return string - ASCII 'art' version of the words 'NEW POST'
 */
function elit_new_post_string() {
//  $str  = " " . PHP_EOL;
//  $str .= " _   _                 ____           _" . PHP_EOL;
//  $str .= "| \ | | _____      __ |  _ \ ___  ___| |_" . PHP_EOL;
//  $str .= "|  \| |/ _ \ \ /\ / / | |_) / _ \/ __| __|" . PHP_EOL;
//  $str .= "| |\  |  __/\ V  V /  |  __/ (_) \__ \ |_" . PHP_EOL;
//  $str .= "|_| \_|\___| \_/\_/   |_|   \___/|___/\__|" . PHP_EOL;

  $str  = " " . PHP_EOL;
  $str .= " _   _                   ____  _" . PHP_EOL;
  $str .= "| \ | | _____      __   / ___|| |_ ___  _ __ _   _" . PHP_EOL;
  $str .= "|  \| |/ _ \ \ /\ / /   \___ \| __/ _ \| '__| | | |" . PHP_EOL;
  $str .= "| |\  |  __/\ V  V /     ___) | || (_) | |  | |_| |" . PHP_EOL;
  $str .= "|_| \_|\___| \_/\_/     |____/ \__\___/|_|   \__, |" . PHP_EOL;
  $str .= "                                             |___/" . PHP_EOL;

  return $str;
}

function elit_beta_notification() {
  $str  = PHP_EOL;
  $str .= "* * * * *                        * * * * *" . PHP_EOL;
  $str .= "* * * * * *      B E T A       * * * * * *" . PHP_EOL;
  $str .= "* * * * *                        * * * * *" . PHP_EOL . PHP_EOL;
//  $str .= "       still working out some bugs        " . PHP_EOL;
//  $str .= "         in these announcements!          " . PHP_EOL . PHP_EOL;

  return $str;

}
