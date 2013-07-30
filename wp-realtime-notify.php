<?php
/*
Plugin Name: WP-Realtime-Notify
Description: Real time post publish notification
Version: v1 beta
Author: Mayank Gupta / Ravinder Kumar
Author URI: http://blogdesignstudio.com
License: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */


$args = array(
  'public'   => true,
  '_builtin' => true
);
$post_types=get_post_types($args,'names'); //get names of post types

foreach($post_types as $post_type){
  //add action to each post type
  add_action( 'publish_'.$post_type, 'ravs_notify_published_post' );
}

/**
 * ravs_notify_published_post fx just retrieve some info about publish post and store it in database using wordpress transient API
 * @param  int $post_id publish post id
 */
function ravs_notify_published_post( $post_id ) {

 $post = get_post( $post_id );
 $args=array(
 	'title'=>'New Post By:'.get_the_author_meta( 'display_name', $post->post_author ),
 	'content'=>'Their is new post publish see <a href="'.get_permalink($post_id).'">'.$post->post_title.'</a>',
 	'type'=>'info'
 	);
 set_transient( 'ravs'.'_'. mt_rand( 100000, 999999 ), $args, 15 );
}



function ravs_notify_init(){   // output script on front-end,required for plugin 

	//don't run this in the admin
	if(is_admin())
		return;
    wp_enqueue_script('heartbeat'); //enqueue the Heartbeat API
    add_action("wp_footer", "ravs_wp_footer"); //load our Javascript and style in the footer
}
add_action("init", "ravs_notify_init");
 
//our Javascript to send/process from the client side
function ravs_wp_footer()
{
?>
<script>
  jQuery(document).ready(function() {

  		//notification popup script
		jQuery('<div/>', { id: 'popup_container' } ).appendTo('body');
		jQuery('body').on('click', '.ravs-close', function () { jQuery(this).parent().slideUp(200); });
		function send_popup( title, text, popup_class, delay ) {
		
			// Initialize parameters
			title = title !== '' ? '<span class="title">' + title + '</span>' : '';
			text = text !== '' ? text : '';
			popup_class = popup_class !== '' ? popup_class : 'update';
			delay = typeof delay === 'number' ? delay : 20000;
			
			var object = jQuery('<div/>', {
			    class: 'ravs_popup_notification ' + popup_class,
			    html: title + text + '<span class="ravs-close">&times;</span>'
			});
			
			jQuery('#popup_container').prepend(object);
			
			jQuery(object).hide().fadeIn(500);
			
			setTimeout(function() {
				
				jQuery(object).slideUp(500);
				
			}, delay);
	
		}

		//hook into heartbeat-send
		jQuery(document).on('heartbeat-send', function(e, data) {
			// console.log('Is Post Publish');
			data['notify_status'] = 'ready';	//need some data to kick off AJAX call
		});
		
		//hook into heartbeat-tick: client looks in data var for natifications
		jQuery(document).on('heartbeat-tick.ravs_tick', function(e, data) {
		// console.log(data['ravs_notify']);		
			if(!data['ravs_notify'])
				return;
			jQuery.each( data['ravs_notify'], function( index, notification ) {
				// console.log(typeof(index));
				if ( index != 'blabla' ){
					send_popup( notification['title'], notification['content'], notification['type'] );
				}
			// console.log(notification);			
		} ) ;
		});
				
		//hook into heartbeat-error: in case of error, let's log some stuff
		jQuery(document).on('heartbeat-error', function(e, jqXHR, textStatus, error) {
			console.log('BEGIN ERROR');
			console.log(textStatus);
			console.log(error);			
			console.log('END ERROR');			
		});
	});		
</script>
<style>
	#popup_container{
		position: fixed;
		bottom: 0;
		right: 0;
	}
	#popup_container .ravs_popup_notification.info {
		margin-bottom: 2px;
		font-size: 10px;
		position: relative;
		background-color: rgba(0, 128, 0, 0.35);
		color: rgba(0, 0, 0, 0.69);
		margin-right: 8px;
		padding: 4px 8px;
		border: 1px solid green;
	}
	.ravs_popup_notification .title {
		display: block;
		}
	.ravs_popup_notification .ravs-close {
		position: absolute;
		top: 0;
		right: 5px;
		cursor: pointer;
	}
</style>
<?php
}
 
/**
 * collect publish post trasient var from wp_options table and return it to front-end javascript( server response to heartbeat tick)
 * @param  array $response 
 * @param  array $data     collection of publish post data
 * @return array of notifications and others
 */
function ravs_heartbeat_received($response, $data){
	global $wpdb;
	
	$data['ravs_notify'] =array();

	if($data['notify_status'] != 'ready')
		return;

	$sql = $wpdb->prepare( 
		"SELECT * FROM $wpdb->options WHERE option_name LIKE %s",'_transient_'.'ravs'.'_%'
	);
		
	$notifications = $wpdb->get_results( $sql );//retrieve all publish post objects
		
	if ( empty( $notifications ) )
		return $data;
		
	foreach ( $notifications as $db_notification ) {
		// set id of each notification
		$id = str_replace( '_transient_', '', $db_notification->option_name );
			
		if ( false !== ( $notification = get_transient( $id ) ) ) 
			$data['ravs_notify'][$id] = $notification;
			
	}
		
	return $data;
	
}
add_filter('heartbeat_received', 'ravs_heartbeat_received', 10, 2); //for login user response
add_filter('heartbeat_nopriv_received', 'ravs_heartbeat_received', 10, 2); //for visiters
?>
