<?php
/**
 Plugin Name: Mass delete taxonomies
 Plugin URI: https://www.mijnpress.nl
 Description: Deletes all tags, handy tool if you want to start over with a quick clean blog.
 Version: 4.1.0
 Author: Ramon Fincken
 Author URI: https://www.mijnpress.nl
 */

function mp_plugin_mass_delete_tags_init() {
	global $current_user;
	
	$selected_tax = '';
   	// Settings
   	$limit = 50;
   	$timeout = 4; // For refresh
	
	
	$args = array(
	    'public'   => true,
	    '_builtin' => true,
	);
	$operator = 'or'; // 'and' or 'or'
	$all_taxonomies = get_taxonomies( $args, 'names', $operator );
	
	$tax_options = [];
	foreach( $all_taxonomies as $tax ) {
	    $all_tags = (array) get_terms( $tax, 'get=all' );
	    
	    $tax_options[$tax] = count( $all_tags );
	    // Store retrieved taxonomy type ONLY if it completely matches the user's input
	    if( 
	        (isset( $_POST[ 'plugin_tag_tax' ] ) && $_POST[ 'plugin_tag_tax' ] == $tax) 
	        || 
	        (isset( $_GET[ 'plugin_tag_tax' ] ) && $_GET[ 'plugin_tag_tax' ] == $tax)
	        ) {
	        $selected_tax = $tax;
	    }
	}
	
	// Get items
	if( $selected_tax ) {    	
    	$all_tags = (array) get_terms( $selected_tax, 'get=all' );
    
    	// Hash based on userid, userlevel and ip
    	$ip = preg_replace( '/[^0-9a-fA-F:., ]/', '',$_SERVER['REMOTE_ADDR'] );
    	$hash = md5( $current_user->ID.$current_user->user_level.$ip.$selected_tax );
    	$url  = 'plugins.php?page=plugin_mass_delete_tags&hash='.$hash.'&plugin_tag_tax='.$selected_tax;
	}
	
	$all_done =  false;
	if( $selected_tax && count( $all_tags ) > 0 ) {
		$validated = false;
		if( isset( $_POST['plugin_tag_action'] ) && isset( $_POST['plugin_tag_validate'] ) && $_POST['plugin_tag_validate'] == 'yes' ) {
			check_admin_referer( 'mass-delete' );
			
			$validated = true;
		}

		if( isset( $_GET['hash'] ) && $_GET['hash'] == $hash ) { // used from url
			$validated = true;
		}
			
		if( $validated ) {
		    $tags = (array) get_terms( $selected_tax, 'get=all&number='.$limit );
			$i = 0;
			echo 'Deleted ids: ';
			foreach( $tags as $tag ) {
			    wp_delete_term( $tag->term_id, $selected_tax );
				echo $tag->term_id.', ';
				$i++;
			}

			echo '<br/><br/>Deleted '.$i.' tags in this page load. Please stand by if the page needs refreshing<br/>';

			if( $i >= $limit ) {
				echo '<br/><br/><meta http-equiv="refresh" content="'.$timeout.';url='.$url.'" />';
				echo '<strong><u>Not done yet</u>!</strong><br/><a href="'.$url.'">Refreshing page! Is this taking more then '.(4*$timeout). ' seconds, please click here</a>';

				die();
			} else {
			    echo '<br/>Removed all items of '.$selected_tax;
				$all_done =  true;
			}
		}

	}



	if( $all_tags && !$all_done ) {
		?>

<h4>By clicking the button you will delete ALL items</h4>

<form action="plugins.php?page=plugin_mass_delete_tags" method="post">
<?php
wp_nonce_field( 'mass-delete' );
?>

    <?php 
    foreach( $tax_options as $tax_name => $tax_count ) {
        echo '<input type="radio" name="plugin_tag_tax" id="plugin_tag_tax_'.$tax_name.'" ';
        if( $selected_tax && $selected_tax == $tax_name ) {
            echo 'checked="checked" ';
        }
        echo 'value="'.$tax_name.'" /><label for="plugin_tag_tax_'.$tax_name.'">&nbsp;'.$tax_name.' ( '.$tax_count.' ) </label><br/>';
    }
    ?>
    
    <br /><br />
    <input type="radio" name="plugin_tag_validate" id="plugin_tag_validate_no"
    	value="no" checked="checked" /><label for="plugin_tag_validate_no">&nbsp;NO!</label><br/>
    
    <input type="radio" name="plugin_tag_validate"
    	id="plugin_tag_validate_yes" value="yes" /><label
    	for="plugin_tag_validate_yes">&nbsp;Yes, delete all items (select me to proceed)</label><br />
    
    <br /><br />
    Note: Staggered delete of (<?php echo $limit; ?>) items at a time. Page will auto refresh untill all items are deleted. <br />
    <input type="submit" name="plugin_tag_action" value="<?php _e("Delete items") ?>" onclick="javascript:return(confirm('<?php _e("Are you sure you want to delete these items? There is NO undo!")?>'))" />

</form>

		<?php
	} else { // /$all_tags && !$all_done
		echo '<p>' . __('No items are in use at the moment.') . '</p>';
	}
}


function mp_plugin_mass_delete_tags_menu() {
    add_submenu_page( 'plugins.php', "Delete all tags", 'Delete all taxonomy items', 'manage_options', 'plugin_mass_delete_tags', 'mp_plugin_mass_delete_tags_init' );
}

// Admin menu items
add_action( 'admin_menu', 'mp_plugin_mass_delete_tags_menu' );
