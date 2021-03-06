<?php
/**
 * BP-Registration-Options Core Initialization
 *
 * @package BP-Registration-Options
 */

/**
 * set $bp_moderate & $bprwg_privacy_network globals and filter off bp buttons
 */
function wds_bp_registration_options_core_init(){
	global $bp_moderate, $bprwg_privacy_network, $wpdb, $bp, $user_ID, $blog_id;
	if ( !is_admin() ) {
		if ( is_multisite() ) {
			$blogid = $blog_id;
			switch_to_blog(1);
		}
		$bprwg_privacy_network = get_option('bprwg_privacy_network');
		$bp_moderate = get_option('bprwg_moderate');
		if ( is_multisite() ) {
			switch_to_blog($blogid);
		}
		//non approved members and non logged in members can not view any buddypress pages
		if ( $bprwg_privacy_network == 1 ) {
			//redirect non logged in users to registration page, if register page is not set then kill it
			if ( $bp->current_component && $user_ID == 0 && $bp->current_component != 'register' && $bp->current_component != 'activate' ) {
				if ( $bp->pages->register->slug ) {
					wp_redirect( site_url() . '/' . $bp->pages->register->slug );
					exit();
				} else {
					exit();
				}
			//if logged in and not approved then redirect to their profile page
			} elseif ( $bp->current_component && $user_ID > 0 && ( $bp->displayed_user->userdata == '' || $bp->displayed_user->userdata != '' && $bp->displayed_user->id != $user_ID ) ) {
				$user = get_userdata( $user_ID );
				if ( 69 == $user->user_status ) {
					wp_redirect( $bp->loggedin_user->domain );
					exit();
				}
			}
		}
		//non approved members can still view bp pages
		if ( $bp_moderate == 1 && $user_ID > 0 ) {
			$user = get_userdata( $user_ID );
			if ( 69 == $user->user_status ) {
				//hide friend buttons
				add_filter( 'bp_get_add_friend_button', '__return_false' );
				add_filter( 'bp_get_send_public_message_button', '__return_false' );
				add_filter( 'bp_get_send_message_button', '__return_false' );
				//hide group buttons
				add_filter( 'bp_user_can_create_groups', '__return_false' );
				add_filter( 'bp_get_group_join_button', '__return_false' );
				//hide activity comment buttons
				add_filter( 'bp_activity_can_comment_reply', '__return_false' );
				add_filter( 'bp_activity_can_comment', '__return_false' );
				add_filter( 'bp_acomment_name', '__return_false' );
				//redirect messages page back to profile (dont want blocked members contacting other members)
				add_filter( 'bp_get_options_nav_invite', '__return_false' );
				add_filter( 'bp_get_options_nav_compose', '__return_false' );
				if ( $bp->current_component == 'messages' ) {
					wp_redirect( $bp->loggedin_user->domain );
					exit();
				}
			//set global to false
			} else {
				$bp_moderate = false;
			}
		}
	}
}
add_action( 'init', 'wds_bp_registration_options_core_init' );

/**
 * Hide any bp buttons & form via css because of no filters
 */
function wds_bp_registration_options_wp_head(){
	global $bp_moderate;
	if ( $bp_moderate ) {
		?>
        <style>
			#whats-new-form,#new-topic-button,#post-topic-reply,#new-topic-post {display:none !important;}
			.activity-meta,.acomment-options,.group-button {display:none !important;}
        </style>
        <?php
	}
}
add_action( 'wp_head', 'wds_bp_registration_options_wp_head' );

/**
 * Disables activity post form
 */
function wds_bp_before_activity_post_form(){
	global $bp_moderate;
	if ( $bp_moderate ) ob_start();
}
add_action('bp_before_activity_post_form','wds_bp_before_activity_post_form', 0);

function wds_bp_after_activity_post_form(){
	global $bp_moderate;
	if ( $bp_moderate ) ob_end_clean();
}
add_action('bp_after_activity_post_form','wds_bp_after_activity_post_form', 0);

/**
 * Disables activity comment buttons/forms
 */
function wds_bp_activity_entry_content(){
	global $bp_moderate;
	if ( $bp_moderate ) ob_start();
}
add_action('bp_activity_entry_content','wds_bp_activity_entry_content', 0);

function wds_bp_before_activity_entry_comments(){
	global $bp_moderate;
	if ( $bp_moderate ) {
		ob_end_clean();
		echo '</div>';//needs this div from betweek the two hooks
	}
}
add_action('bp_before_activity_entry_comments','wds_bp_before_activity_entry_comments', 0);

/**
 * Disables forums new topic form (groups page)
 */
function wds_bp_before_group_forum_post_new(){
	global $bp_moderate;
	if ( $bp_moderate ) ob_start();
}
add_action('bp_before_group_forum_post_new','wds_bp_before_group_forum_post_new', 0);

function wds_bp_after_group_forum_post_new(){
	global $bp_moderate;
	if ( $bp_moderate ) ob_end_clean();
}
add_action('bp_after_group_forum_post_new','wds_bp_after_group_forum_post_new', 0);

/**
 * Disables forums reply form
 */
function wds_groups_forum_new_reply_before(){
	global $bp_moderate;
	if ( $bp_moderate ) ob_start();
}
add_action('groups_forum_new_reply_before','wds_groups_forum_new_reply_before', 0);

function wds_groups_forum_new_reply_after(){
	global $bp_moderate;
	if ( $bp_moderate ) ob_end_clean();
}
add_action('groups_forum_new_reply_after','wds_groups_forum_new_reply_after', 0);

/**
 * Disables forums new topic form (forums page)
 */
function wds_groups_forum_new_topic_before(){
	global $bp_moderate;
	if ( $bp_moderate ) ob_start();
}
add_action('groups_forum_new_topic_before','wds_groups_forum_new_topic_before', 0);

function wds_groups_forum_new_topic_after(){
	global $bp_moderate;
	if ( $bp_moderate ) ob_end_clean();
}
add_action('groups_forum_new_topic_after','wds_groups_forum_new_topic_after', 0);

/**
 * Hide any activity created by blocked user (if they somehow get around hidden form)
 */
function wds_bp_registration_options_bp_actions(){
	global $wpdb, $user_ID, $bp_moderate, $bp;
	if ( $bp_moderate ) {
		$sql = 'UPDATE ' . $wpdb->base_prefix . 'bp_activity SET hide_sitewide = 1 WHERE user_id = %d';
		$wpdb->query( $wpdb->prepare( $sql, $user_ID ) );
	}
}
add_action( 'bp_actions', 'wds_bp_registration_options_bp_actions', 50 );

/**
 * Show a custom message on the activation page and on users profile header.
 */
function wds_bp_registration_options_bp_after_activate_content(){
	global $bp_moderate, $user_ID, $blog_id;
	if ( is_multisite() ) {
		$blogid = $blog_id;
		switch_to_blog(1);
	}
	if ( $bp_moderate && isset( $_GET['key'] ) || $bp_moderate && $user_ID > 0 ) {
		$activate_message = stripslashes( get_option( 'bprwg_activate_message' ) );
		echo '<div id="message" class="error"><p>' . $activate_message . '</p></div>';
	}
	if ( is_multisite() ) {
		switch_to_blog( $blogid );
	}
}
add_filter( 'bp_after_activate_content', 'wds_bp_registration_options_bp_after_activate_content' );
add_filter( 'bp_before_member_header', 'wds_bp_registration_options_bp_after_activate_content' );

/**
 * Custom activation functionality
 */
function wds_bp_registration_options_bp_core_activate_account($user_id){
	global $wpdb, $bp_moderate;
	if ( $bp_moderate &&  $user_id > 0 ) {
		if ( isset( $_GET['key'] ) ) {

			$user = get_userdata( $user_id );
			$admin_email = get_bloginfo( 'admin_email' );

			//add HTML capabilities temporarily
			add_filter('wp_mail_content_type','bp_registration_options_set_content_type');

			//If their IP or email is blocked, don't proceed and exit silently.
			$blockedIPs = get_option( 'bprwg_blocked_ips' );
			$blockedemails = get_option( 'bprwg_blocked_emails' );
			if ( in_array( $_SERVER['REMOTE_ADDR'], $blockedIPs ) || in_array( $user->user_email, $blockedemails ) ) {
				$message = apply_filters( 'bprwg_banned_user_admin_email', __( 'Someone with a banned IP address or email just tried to register with your site', 'bp-registration-options' ) );
				wp_mail( $admin_email, __( 'Banned member registration attempt', 'bp-registration-options' ), $message );

				//Delete their account thus far.
				if ( is_multisite() ) {
					wpmu_delete_user( $user_id );
				}
				wp_delete_user( $user_id );
				return;
			}

			//Hide user created by new user on activation.
			$sql = 'UPDATE ' . $wpdb->base_prefix . 'users SET user_status = 69 WHERE ID = %d';
			$wpdb->query( $wpdb->prepare( $sql, $user_id ) );

			//Hide activity created by new user
			$sql = 'UPDATE ' . $wpdb->base_prefix . 'bp_activity SET hide_sitewide = 1 WHERE user_id = %d';
			$wpdb->query( $wpdb->prepare( $sql, $user_id ) );

			//save user ip address
			update_user_meta( $user_id, 'bprwg_ip_address', $_SERVER['REMOTE_ADDR'] );

			//email admin about new member request
			$user_name = $user->user_login;
			$user_email = $user->user_email;
			$message = $user_name . ' ( ' . $user_email . ' ) ' . __( 'would like to become a member of your website, to accept or reject their request please go to ', 'bp-registration-options') . admin_url( '/admin.php?page=bp_registration_options_member_requests' );

			//add our filter and provide the user name and user email for them to utilize.
			$mod_email = apply_filters( 'bprwg_new_member_request_admin_email', $message, $user_name, $user_email );
			wp_mail( $admin_email, __( 'New Member Request', 'bp-registration-options' ), $mod_email );
			remove_filter('wp_mail_content_type','bp_registration_options_set_content_type');
		}
	}
}
add_action( 'bp_core_activate_account', 'wds_bp_registration_options_bp_core_activate_account');

/**
 * Hide members, who haven't been approved yet, on the frontend listings.
 * @param  object $args arguments that BuddyPress will use to query for members
 * @return object       amended arguments with IDs to exclude.
 * @since  4.1
 */
function bp_registration_hide_pending_members( $args ) {
	global $wpdb;

	$ids = array();
	$sql = "SELECT ID FROM " . $wpdb->base_prefix . "users WHERE user_status IN (2,69)";
	$rs = $wpdb->get_results( $wpdb->prepare( $sql, '' ), ARRAY_N );
	//Grab the actual IDs
	foreach( $rs as $key => $value) {
		$ids[] = $value[0];
	}

	if ( $ids )
		$args->query_vars['exclude'] = $ids;

	return $args;
}
add_action( 'bp_pre_user_query_construct', 'bp_registration_hide_pending_members' );

/**
 * Prevent viewing of bbPress forums for non-approved members
 *
 * @since  4.2
 */
function wds_bp_registration_deny_bbpress() {
	$user = new WP_User( get_current_user_id() );
	$deny = wds_bp_registration_get_user_status_values();

	if ( bbp_is_single_user_edit() ||
	    bbp_is_single_user() ||
	    bbp_is_user_home() ||
	    bbp_is_user_home_edit()
	) {
		return;
	}

	if ( $user->ID > 0 ) {
		if ( in_array( $user->data->user_status, $deny ) && is_bbpress() ) {
			wp_redirect( bbp_get_user_profile_url( $user->ID ) );
			exit;
		}
	}

}
add_action( 'template_redirect', 'wds_bp_registration_deny_bbpress' );

/**
 * Return an array of user statuses to check for.
 *
 * @since  4.2
 *
 * @return array  array of user statuses
 */
function wds_bp_registration_get_user_status_values() {
	return array( 2, 69 );
}
