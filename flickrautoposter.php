<?php
/*
Plugin Name: Flickr Auto Poster
Plugin URI: http://www.2dubs.com
Description: Automaticaly post your flickr images to wordpress by tags.
Author: Alexey Novikov
Version: 0.92
Author URI: http://www.2dubS.com
License   : http://creativecommons.org/licenses/GPL/2.0/
*/

register_activation_hook(__FILE__,'fap_activation');
register_deactivation_hook(__FILE__,'fap_deactivation');

function fap_activation() {
	$fap_path = WP_PLUGIN_DIR.'/flickrautoposter';
	add_option('fap_lastupdate', 0);
	add_option('fap_flickruserid', '');
	add_option('fap_path', $fap_path);
	wp_schedule_event(time(), 'fap_cron', 'fap_cron_hook');			
}

function fap_deactivation() {
	//delete_option('fap_lastupdate');			
	//delete_option('fap_flickrusername');		
	//delete_option('fap_flickruserid');
	//delete_option('fap_wpuserid');
	//delete_option('fap_tags');
	//delete_option('fap_cat');
	//delete_option('fap_flickrsize');
    wp_clear_scheduled_hook('fap_cron_hook');
}

add_filter('cron_schedules', 'fap_more_reccurences');
function fap_more_reccurences($recc) {
	$recc['fap_cron'] = array('interval' => 300, 'display' => 'Flickr Auto Poster Updates');
    return $recc;
}

add_action('fap_cron_hook','fap_check_flickr');
function fap_check_flickr() {
	if(get_option('fap_flickrusername') && get_option('fap_tags') && get_option('fap_wpuserid')) {
		$fap_settings['fap_path'] = get_option('fap_path');
		//$fap_settings['fap_php_flickr_cache'] = $fap_settings['fap_path'].'/cache';
		$fap_settings['fap_flickrusername'] = get_option('fap_flickrusername');
		$fap_settings['fap_flickruserid'] = get_option('fap_flickruserid');
		$fap_settings['fap_wpuserid'] = get_option('fap_wpuserid');
		$fap_settings['fap_tags'] = get_option('fap_tags');
		$fap_settings['fap_cat'] = get_option('fap_cat');
		$fap_settings['fap_flickrapikey'] = 'ee9f68c880d60587cd0244e435c5fdcb';
		$fap_settings['fap_lastupdate'] = get_option('fap_lastupdate');
		$fap_settings['fap_flickrsize'] = get_option('fap_flickrsize');
		
		require_once('lib/phpflickr/phpFlickr.php');
		$f = new phpFlickr($fap_settings['fap_flickrapikey']);
		//$f->enableCache("fs", $fap_settings['fap_php_flickr_cache']);
        
        if($fap_settings['fap_flickruserid']=='') {
			$newUserID = $f->people_findByUsername($fap_settings['fap_flickrusername']);	
            echo "new user";print_r($newUserID);
			if($newUserID == 1) return $fap_settings['fap_lastupdate'];
			$fap_settings['fap_flickruserid'] = $newUserID['nsid'];
			update_option('fap_flickruserid', $newUserID['nsid']);
		}
        
		if ($fap_settings['fap_tags']) {
		  $tags = urlencode($fap_settings['fap_tags']);
		  $tag_mode = 'any'; 
		  if (strstr($tags, '+')) {
			$tags = str_replace('+', ',', $tags);
			$tag_mode = 'all';
		  }
		  else if (strstr($tags, "%2C")) {
			$tags = str_replace('%2C', ',', $tags);
			$tag_mode = 'any';
		  }
		}
		
		$fap_update = $fap_settings['fap_lastupdate'];
		$photos = $f->photos_search(array('user_id' => $fap_settings['fap_flickruserid'],'tags' => $fap_settings['fap_tags'], 'tag_mode' => $tag_mode, 'per_page' => '100', 'min_upload_date' => $fap_settings['fap_lastupdate']));
		echo "photos";print_r($photos);
        if($photos['total']>0) {
			
			foreach ($photos['photo'] as $photo) {
			  $u = $f->urls_getUserPhotos($photo['owner']);
			  $link = $u . $photo['id'] . "/";
			  
			  $date = $f -> photos_getInfo($photo['id']);
			  $date = $date['dateuploaded'];
			  if($date <= $fap_settings['fap_lastupdate']) continue;
			  if($fap_update < $date) $fap_update = $date;
			  
			  $content['post_author']=$fap_settings['fap_wpuserid'];
			  $content['post_title']=$photo['title'];
			  $content['post_date'] = date("Y-m-d H:i:s", $date);
			  $content['post_category'][0]=$fap_settings['fap_cat'];
			  $content['post_content']='<p><a href="'.$link.'"><img src="'.$f->buildPhotoURL($photo, $fap_settings['fap_flickrsize']).'" alt="'.$photo['title'].'" /></a></p>';
			  $content['post_status'] = 'publish';
			  
			  wp_insert_post( $content );
			 }
		}
		update_option('fap_lastupdate', $fap_update);
	}
}

add_action('admin_menu', 'fap_create_menu');
function fap_create_menu() {
	add_options_page('Flickr Auto Poster Settings', 'Flickr Auto Poster', 'administrator','fap', 'fap_settings_page');
	add_action( 'admin_init', 'fap_register_settings' );
}

function fap_register_settings() {
	register_setting( 'flickr-auto-poster-settings', 'fap_flickrusername', 'fap_usernamecheck' );
	register_setting( 'flickr-auto-poster-settings', 'fap_wpuserid' );
	register_setting( 'flickr-auto-poster-settings', 'fap_tags' );
	register_setting( 'flickr-auto-poster-settings', 'fap_cat' );
	register_setting( 'flickr-auto-poster-settings', 'fap_flickrsize' );	
}

function fap_usernamecheck($input) {
	$input = trim($input);
	if($input != get_option('fap_flickrusername')) {
		update_option('fap_lastupdate', 0);
		update_option('fap_flickruserid', '');
	}
	return $input;
}

function get_user_list($selected) {
	global $wpdb;
/*
	* ID - User ID number.
	* user_login - User Login name.
	* user_nicename - User Nice name ( nice version of login name ).
	* user_email - User Email Address.
	* user_url - User Website URL.
	* user_registered - User Registration date.
*/
	$szSort = "user_nicename";
	$aUsersID = $wpdb->get_col( $wpdb->prepare("SELECT $wpdb->users.ID FROM $wpdb->users ORDER BY %s ASC", $szSort ));
	foreach ( $aUsersID as $iUserID ) {
		$user = get_userdata( $iUserID );
		if($iUserID==$selected) $sel=' selected="selected"'; else $sel='';
		echo '<option value="'.$iUserID.'"'.$sel.'>'.$user->first_name . ' ' . $user->last_name.'</option>';
	}
}

function fap_settings_page() {
 if (!current_user_can('manage_options'))  {
    wp_die( __('You do not have sufficient permissions to access this page.') );
  }
?>
	<script type="text/javascript" language="javascript">
		
		function checkFields() {
			var userName = jQuery('#fap_flickrusername').val();
			if(userName == "") {
				jQuery('#message').html("<p><strong>You must enter a valid flickr user name</strong></p>").fadeIn('slow');
				jQuery('#fap_flickrusername').animate( { backgroundColor: '#FFCCCC' }, 'slow').focus();
				return false;
			}
			
			var wpUserName = jQuery('#fap_wpuserid').val();
			if(wpUserName == "") {
				jQuery('#message').html("<p><strong>You must choose a user</strong></p>").fadeIn('slow');
				jQuery('#fap_wpuserid').animate( { backgroundColor: '#FFCCCC' }, 'slow').focus();
				return false;
			}
			
			var tags = jQuery('#fap_tags').val();
			if(tags == "") {
				jQuery('#message').html("<p><strong>Enter at least one tag</strong></p>").fadeIn('slow');
				jQuery('#fap_tags').animate( { backgroundColor: '#FFCCCC' }, 'slow').focus();
				return false;
			}
			
			var flickrsize = jQuery('#fap_flickrsize').val();
			if(flickrsize == 0) {
				jQuery('#message').html("<p><strong>Choose size of imported images</strong></p>").fadeIn('slow');
				jQuery('#fap_flickrsize').animate( { backgroundColor: '#FFCCCC' }, 'slow').focus();
				return false;
			}
			
			return true;
		}
		
	</script>
<div class="wrap">
<link rel="stylesheet" href="<?php echo WP_PLUGIN_URL;?>/flickrautoposter/style.css" type="text/css" media="screen" />
<h2>Flickr Auto Poster</h2>
<div class="updated" id="message" ></div>
<form method="post" action="options.php">
    <?php settings_fields( 'flickr-auto-poster-settings' ); ?>
    <table class="form-table">
        <tr valign="top">
		    <th scope="row">Flickr user name</th>
			<td class="fields"><input type="text" id="fap_flickrusername" name="fap_flickrusername" value="<?php echo get_option('fap_flickrusername'); ?>" /></td>
			<td></td>
		</tr>
		<tr valign="top">
		    <th scope="row">WP user </th>
			<td><select name="fap_wpuserid" id="fap_wpuserid"> 
					<option value="">Select user</option>
					<?php get_user_list(get_option('fap_wpuserid'));?>
				</select>
			</td>
			<td></td>
		</tr>
        <tr valign="top">
	        <th scope="row">Tags to import</th>
			<td><input type="text" name="fap_tags" id="fap_tags" value="<?php echo get_option('fap_tags'); ?>" /></td>
			<td>Separate tags with '+' for importing photos including all tags, and separate with ',' for importing any tag.</td>
		</tr>
		<tr valign="top">
	        <th scope="row">Category for posts</th>
			<td><?php wp_dropdown_categories('show_count=0&hide_empty=0&hierarchical=false&name=fap_cat&selected='.get_option('fap_cat')); ?></td>
		</tr>
		<?php $flsz = get_option('fap_flickrsize');?>
		<tr valign="top">
		 <th scope="row">Size of picture:</th>
		 <td><select name="fap_flickrsize" id="fap_flickrsize" > 
		 		<option value = "0" >Select image size</option>
		 		<option value="square"<?php if($flsz=='square') echo 'selected="selected"';?>>Square (75 x 75)</option>
		 		<option value="thumbnail"<?php if($flsz=='thumbnail') echo 'selected="selected"';?>>Thumbnail 100</option>
		 		<option value="small"<?php if($flsz=='small') echo 'selected="selected"';?>>Small 240</option>
		 		<option value="medium"  <?php if($flsz=='medium') echo 'selected="selected"';?>>Medium 500</option>
		 		<option value="medium640"<?php if($flsz=='medium640') echo 'selected="selected"';?>>Medium 640</option>
		 		<option value="large"<?php if($flsz=='large') echo 'selected="selected"';?>>Large 1024</option>
		 	<!--	<option value="original"<?php if($flsz=='original') echo 'selected="selected"';?>>Original</option> -->
		    </select>
		 </td>
		 <td>not all images has large size</td>
		</tr>
    </table>
    <p class="submit">
    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" onClick="return checkFields();" />
    </p>
</form>
<div class="donate">
	<p>Help support continued development of <br />Flickr Auto Poster and other plugins.</p>
	<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
		<input type="hidden" name="cmd" value="_s-xclick">
		<input type="hidden" name="hosted_button_id" value="CM8JJP9VVU44E">
		<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
		<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
	</form>
</div>
</div>
<?php } 
?>