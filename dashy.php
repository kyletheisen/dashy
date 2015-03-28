<?php
/**
 * Plugin Name:       Dashy
 * Plugin URI:        https://github.com/kyletheisen/dashyy
 * Description:       Dashy gives you a search field to get to admin pages quicker. More keyboard shortcuts right inside of WordPress. Get to pages, post, plugins, faster. You can even search and get a to single post with the keyboard.
 * Version:           1.0.0
 * Author:            Kyle Theisen
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       dashy
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Add js & css
 */
add_action( 'admin_enqueue_scripts', 'dashy_admin_style' );
function dashy_admin_style() {

	// add styles and scripts
	wp_enqueue_style( 'select2_css', plugin_dir_url( __FILE__ ) . 'select2.css' );
	wp_enqueue_style( 'dashy_css', plugin_dir_url( __FILE__ ) . 'dashy.css' );
	wp_enqueue_script( 'select2_js', plugin_dir_url( __FILE__ ) . 'select2.min.js' );
	wp_enqueue_script( 'dashy_js', plugin_dir_url( __FILE__ ) . 'dashy.js', array( 'jquery' ) );
	wp_enqueue_script( 'hotkeys', plugin_dir_url( __FILE__ ) . 'jquery.hotkeys.js', array( 'jquery' ) );

	// localize dashy data
	wp_localize_script( 'dashy_js', 'dashy_menu', _dashy_menu_output() );

	//global $menu, $submenu;
	//_dashy_menu_output( $menu, $submenu );

}

/**
 * Add required dashy html to admin pages
 */
function dashy_add_html(){
	echo '<div id="dashy-box"><input type="hidden" id="dashy-sel" data-s2data="default"></div>';
}
add_action( 'in_admin_header', 'dashy_add_html' );

/**
 * Setup choices for select2 data, pulled from WP menu
 *
 * @return array
 */
function dashy_setup_admin_menu_data() {

	// get admin menus
	$dashy_menu = dashy_get_admin_menus();

	// holds select2 menu data
	$dashy_data = array();
	$uid      = 0;

	// get cpt details
	$cpt_details = get_option( 'dashy_cpt_details' );

	// loop through menu
	foreach ( $dashy_menu as $key => $item ) {

		// skip separators
		if ( substr( $item[2], 0, 9 ) == 'separator' ) {
			continue;
		}

		// add item to menu
		$dashy_data[] = array(
			'id'     => $uid,
			'type'   => 'url',
			'name'   => dashy_filter_spans( $item [0] ),
			'url'    => admin_url() . $item[2],
			'parent' => '',
			'cpt'    => ''
		);
		$uid ++;

		// check if item name is in cpt details
		$cpt_key = dashy_multi_search( $cpt_details, 'label_name', dashy_filter_spans( $item[0] ) );

		// check if we have a match
		// if so, list add "list _____(cpt)" item
		if ( $cpt_key !== FALSE ) {

			// get cpt name
			$cpt_name = $cpt_details[ $cpt_key ]['name'];

			// setup data
			$dashy_data[] = array(
				'id'     => $uid,
				'type'   => 'select',
				'var'    => $cpt_name, // this is the cpt
				'name'   => 'List ' . $cpt_details[ $cpt_key ]['label_name'],
				'parent' => $item[0],
				'cpt'    => ''
			);
			$uid ++;

			// get option for this cpt
			$cpt_posts = get_option( 'dashy_data_' . $cpt_name );

			// do we have data?
			if ( $cpt_posts && is_array( $cpt_posts ) ) {

				// sort
				asort( $cpt_posts );

				// add data
				$list_cpts[ 'dashy_' . $cpt_name ] = array_values( $cpt_posts );
			}
		}

		// menu has submenu
		if ( isset( $item['submenu'] ) ) {
			foreach ( $item['submenu'] as $sub_item ) {
				if ( $sub_item[0] === 'Add New' ) {
					$item_name = $sub_item[0] . ' ' . rtrim( dashy_filter_spans( $item[0] ), 's' );
					// TODO - get proper CPT singular label, instead of the above!
				} else {
					$item_name = $sub_item[0];
				}
				$dashy_data[] = array(
					'id'     => $uid,
					'type'   => 'url',
					'name'   => dashy_filter_spans( $item_name ),
					'url'    => admin_url() . $sub_item[2],
					'parent' => dashy_filter_spans( $item[0] ),
					'cpt'    => ''
				);
				$uid ++;
			}
		}

	}

	// append dashy_data
	$list_cpts['main_menu'] = $dashy_data;

	//return array( 'main_menu' => $dashy_data );
	return $list_cpts;
}

/*
 * Get admin menus and submenus
 */
function dashy_get_admin_menus() {
	global $menu, $submenu;

	// alter our menu, not wp menu
	$dashy_menu = $menu;

	// combine submenu into menu
	foreach ( $submenu as $key => $value ) {
		// search submenu key within menu
		$menu_key = dashy_array_search( $dashy_menu, 2, $key );

		if ( $menu_key ) {
			$dashy_menu[ $menu_key ]['submenu'] = $value;
		} else {
			// cannot find menu parent
			//dashy_debug( 'cannot find parent: ' . $key );
		}
	}

	return $dashy_menu;
}

/**
 * Search multi dimensional array for value
 *
 * @param $array array multi dimensional array
 * @param $field string field to search
 * @param $value string value we are looking for
 *
 * @return bool|int|string return top array key
 */
function dashy_multi_search( $array, $field, $value ) {
	foreach ( $array as $key => $val ) {
		if ( $val[ $field ] === $value ) {
			return $key;
		}
	}

	return FALSE;
}

/*
 * Filter out span from menu names
 *
 * Remove spans from comments, plugins, updates which contain
 * the update count. Regex would work, but this seems to work.
 */
function dashy_filter_spans( $name ) {
	//$name = substr( $name, 0, strpos( $name, "<span" ) );
	if ( ! empty( $name ) && strpos( $name, '<span' ) !== FALSE ) {
		$name = strstr( $name, '<span', TRUE );
	}

	return $name;
}

/**
 * Save new CPT posts to dashy data in options table
 *
 * since we can't check for only new posts, we set some meta data.
 * meta data is post title, so we check if that changed to update dashy data
 *
 * @param int  $post_id The post ID.
 * @param object $post    The post object.
 */
function dashy_save_post( $post_id, $post ) {

	// skip for revisions
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	// skip auto saves
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// get meta data
	$dashy_title = get_post_meta( $post_id, '_dashy_title', TRUE );
	// check if meta data exists
	if ( ! $dashy_title || $dashy_title != $post->post_title ) {
		// this is a new post, or has updated title
		$dashy_title = $post->post_title;
		update_post_meta( $post_id, '_dashy_title', $dashy_title );

		// also add to dashy data in options table

		// setup data
		$dashy_data = array(
			'id'     => $post_id,
			'name'   => $post->post_title,
			'url'    => get_edit_post_link( $post_id ),
			'type'   => 'url',
			'parent' => '',
			'cpt'    => $post->post_type
		);
		
		// add post type to option data
		dashy_add_post_to_dashy_data( $dashy_data, $post->post_type );

		// get cpts
		$cpts = get_option( 'dashy_cpt_details' );

		// current CPT name
		$cpt_name = get_post_type( $post_id );
		// CPT not in options, add
		if ( !isset( $cpts[$cpt_name] ) ){
			$post_type = get_post_type_object( $cpt_name );
			$cpt_detail = array(
				'name'          => $post_type->name,
				'singular_name' => $post_type->labels->singular_name,
				'label_name'    => $post_type->labels->name
			);
			dashy_add_cpt_detail( $cpt_detail );
		}
	}

}
add_action( 'save_post', 'dashy_save_post', 10, 2 );

/**
 * Add dashy data to options table
 *
 * @param $data array properly formatted dashy data
 * @param $cpt string cpt that we are udpating
 *
 * @return bool
 */
function dashy_add_post_to_dashy_data( $data, $cpt ) {

	// default data array
	$defaults = array(
		'id'     => '',
		'name'   => '',
		'url'    => '',
		'type'   => 'url',
		'parent' => '',
		'cpt'    => ''
	);

	// merge $args with $defaults
	$data = wp_parse_args( $data, $defaults );

	// make sure we have the correct data
	if ( !( isset( $data['id'] ) && $data['id'] !== '' && isset( $data['name'] ) && $data['name'] !== '' && isset( $data['url'] ) && $data['url'] !== '' ) ) {
		return FALSE;
	}

	// get option data
	$cpt_data = get_option( 'dashy_data_' . $cpt );

	// post id
	$post_id = $data['id'];

	// we have cpt data, set data
	$cpt_data[ $post_id ] = $data;

	// update and return
	return update_option( 'dashy_data_' . $cpt, $cpt_data );

}

/**
 * Remove post from dashy data
 *
 * This fires when a post is deleted
 *
 * @param $post_id string the post id
 * @return bool
 */
function dashy_delete_post( $post_id ){

	// we need the post type to know which dashy_data to remove post from
	global $post_type;

	// get dashy_data for cpt
	$cpt_data = get_option( 'dashy_data_' . $post_type );

	// make sure everything we need is exists
	if ( $cpt_data && isset( $cpt_data[$post_id] ) ){
		// remove post from data
		unset( $cpt_data[$post_id] );
	} else {
		return FALSE;
	}

	// update options
	return update_option( 'dashy_data_' . $post_type, $cpt_data );

}
add_action( 'before_delete_post', 'dashy_delete_post' );

/**
 * Find value in multidimensional array
 *
 * @param $array
 * @param $search_key
 * @param $search_value
 *
 * @return bool|int|string key where value is located
 */
function dashy_array_search( $array, $search_key, $search_value ) {
	foreach ( $array as $key => $item ) {
		if ( $item[ $search_key ] === $search_value ) {
			return $key;
		}
	}

	return FALSE;
}

/**
 * Get all CPTs and save to options table
 *
 * Runs only on plugin activation!
 */
function dashy_get_save_cpt_data() {

	// get post types
	$post_types = get_post_types( array( '_builtin' => FALSE ), 'objects' );

	foreach ( $post_types as $post_type ) {
		// save cpt data in options table
		dashy_save_cpt_posts( $post_type->name );
		$cpt_details[$post_type->name] = array(
			'name'          => $post_type->name,
			'singular_name' => $post_type->labels->singular_name,
			'label_name'    => $post_type->labels->name
		);
	}

	// since build in post types are ignored, get posts and pages separately
	dashy_save_cpt_posts( 'page' );
	dashy_save_cpt_posts( 'post' );
	// get cpt details for posts and pages
	$cpt_details['page'] = array(
		'name'          => 'page',
		'singular_name' => 'Page',
		'label_name'    => 'Pages'
	);
	$cpt_details['post'] = array(
		'name'          => 'post',
		'singular_name' => 'Post',
		'label_name'    => 'Posts'
	);

	// save cpt details to options
	add_option( 'dashy_cpt_details', $cpt_details, '', 'no' );

	// TODO - (maybe) at this point, we could (should?) save the _dashy_title post meta data
	// decided against it at this point to run fewer queries on activation

}

/**
 * Add a CPT to the CPT details option array
 *
 * @param $cpt_detail array of cpt details
 */
function dashy_add_cpt_detail( $cpt_detail ){

	$cpt_name = $cpt_detail['name'];
	// make sure cpt detail is set - sure we could do it better but we are setting this
	if ( $cpt_detail  && isset( $cpt_name ) ){

		// get cpt details option
		$all_cpt_details = get_option( 'dashy_cpt_details' );
		if ( !isset( $all_cpt_details[$cpt_name] ) ){
			$all_cpt_details[$cpt_name] = $cpt_detail;
		}

		// finally update
		update_option( 'dashy_cpt_details', $all_cpt_details );

	}

}

register_activation_hook( __FILE__, 'dashy_get_save_cpt_data' );

/**
 * Save CPT posts into options
 *
 * Needed for quick access to post data. Used only on plugin activation.
 *
 * @param $post_type
 */
function dashy_save_cpt_posts( $post_type ) {

	// make sure post type exists
	if ( ! post_type_exists( $post_type ) ) {
		return;
	}

	// setup query args
	$args = array(
		'post_type'              => $post_type,
		'posts_per_page'         => 500, // sorry, not doing all
		'no_found_rows'          => TRUE,
		'update_post_meta_cache' => FALSE,
		'update_post_term_cache' => FALSE
	);

	// make query
	$cpt_query = new WP_Query( $args );

	if ( $cpt_query->have_posts() ):

		while ( $cpt_query->have_posts() ) : $cpt_query->the_post();

			// cpt data stored in array
			$cpt_data[ get_the_ID() ] = array(
				'id'     => get_the_ID(),
				'name'   => get_the_title(),
				'url'    => get_edit_post_link( get_the_ID() ),
				'type'   => 'url',
				'parent' => '',
				'cpt'    => $post_type
			);

		endwhile;

		// reset query & data
		wp_reset_postdata();
		wp_reset_query();

		// save cpt data
		if ( isset( $cpt_data ) ){
			add_option( 'dashy_data_' . $post_type, $cpt_data, '', 'no' );
		}

	endif;
}

/**
 * Remove CPT data saved in options table
 *
 * Runs only on deactivation of plugin
 *
 * TODO - track all CPTs used to remove previously used CPTs that are no longer registered
 */
function dashy_remove_saved_cpt_data() {

	// get post types
	$post_types = get_post_types( array( '_builtin' => FALSE ), 'names' );

	foreach ( $post_types as $post_type ) {

		// save cpt data in options table
		dashy_remove_cpt_posts( $post_type );
	}

	// since build in post types are ignored, get posts and pages separately
	dashy_remove_cpt_posts( 'page' );
	dashy_remove_cpt_posts( 'post' );

}

register_deactivation_hook( __FILE__, 'dashy_remove_saved_cpt_data' );

/**
 * Remove CPT data from options
 *
 * Used only on plugin activation.
 *
 * @param $post_type
 */
function dashy_remove_cpt_posts( $post_type ) {

	// make sure post type exists
	if ( ! post_type_exists( $post_type ) ) {
		return;
	}

	// save cpt data
	delete_option( 'dashy_data_' . $post_type );
}

/**
 * Debug function
 */
if ( ! function_exists( 'dashy_debug' ) ) {
	function dashy_debug( $message ) {
		if ( WP_DEBUG === TRUE ) {
			if ( is_array( $message ) || is_object( $message ) ) {
				error_log( print_r( $message, TRUE ) );
			} else {
				error_log( $message );
			}
		}
	}
}











/**
 * Display menu.
 *
 * @access private
 * @since 2.7.0
 *
 * @param array $menu
 * @param array $submenu
 * @param bool $submenu_as_parent
 */
function _dashy_menu_output() {
	//global $self, $typenow;
	global $menu, $submenu;
	$submenu_as_parent = true;

	$uid = 0;
	$dashy_data = false;

	// get cpt details
	$cpt_details = get_option( 'dashy_cpt_details' );

	// 0 = menu_title, 1 = capability, 2 = menu_slug, 3 = page_title, 4 = classes, 5 = hookname, 6 = icon_url
	foreach ( $menu as $key => $item ) {

		// skip separators
		if ( $item[4] === 'wp-menu-separator' ){
			continue;
		}

		$admin_is_parent = false;
		//$class = array();
		$href = '';

		$submenu_items = false;
		if ( ! empty( $submenu[$item[2]] ) ) {
			//$class[] = 'wp-has-submenu';
			$submenu_items = $submenu[$item[2]];
		}

		//if ( ( $parent_file && $item[2] == $parent_file ) || ( empty($typenow) && $self == $item[2] ) ) {
		//	$class[] = ! empty( $submenu_items ) ? 'wp-has-current-submenu wp-menu-open' : 'current';
		//} else {
		//	$class[] = 'wp-not-current-submenu';
		//}

		//if ( ! empty( $item[4] ) )
		//	$class[] = esc_attr( $item[4] );

		//$class = $class ? ' class="' . join( ' ', $class ) . '"' : '';
		//$id = ! empty( $item[5] ) ? ' id="' . preg_replace( '|[^a-zA-Z0-9_:.]|', '-', $item[5] ) . '"' : '';

		$title = wptexturize( $item[0] );

		//echo "\n\t<li$class>";

		if ( $submenu_as_parent && ! empty( $submenu_items ) ) {
			if ( is_array( $submenu_items ) ){
				$submenu_items = array_values( $submenu_items );  // Re-index.
			}
			$menu_hook = get_plugin_page_hook( $submenu_items[0][2], $item[2] );
			$menu_file = $submenu_items[0][2];
			if ( false !== ( $pos = strpos( $menu_file, '?' ) ) )
				$menu_file = substr( $menu_file, 0, $pos );
			if ( ! empty( $menu_hook ) || ( ( 'index.php' != $submenu_items[0][2] ) && file_exists( WP_PLUGIN_DIR . "/$menu_file" ) && ! file_exists( ABSPATH . "/wp-admin/$menu_file" ) ) ) {
				$admin_is_parent = true;
				//echo "<a href='admin.php?page={$submenu_items[0][2]}'$class><div class='wp-menu-image'></div><div class='wp-menu-name'>$title</div></a>";
				$href = "admin.php?page={$submenu_items[0][2]}";
			} else {
				//echo "\n\t<a href='{$submenu_items[0][2]}'$class><div class='wp-menu-image'></div><div class='wp-menu-name'>$title</div></a>";
				$href = "{$submenu_items[0][2]}";
			}
		} elseif ( ! empty( $item[2] ) && current_user_can( $item[1] ) ) {
			$menu_hook = get_plugin_page_hook( $item[2], 'admin.php' );
			$menu_file = $item[2];
			if ( false !== ( $pos = strpos( $menu_file, '?' ) ) )
				$menu_file = substr( $menu_file, 0, $pos );
			if ( ! empty( $menu_hook ) || ( ( 'index.php' != $item[2] ) && file_exists( WP_PLUGIN_DIR . "/$menu_file" ) && ! file_exists( ABSPATH . "/wp-admin/$menu_file" ) ) ) {
				$admin_is_parent = true;
				//echo "\n\t<a href='admin.php?page={$item[2]}'$class><div class='wp-menu-image'></div><div class='wp-menu-name'>{$item[0]}</div></a>";
				$href = "admin.php?page={$item[2]}";
			} else {
				//echo "\n\t<a href='{$item[2]}'$class><div class='wp-menu-image'></div><div class='wp-menu-name'>{$item[0]}</div></a>";
				$href = "{$item[2]}";
			}
		}

		// add item to menu
		$dashy_data[] = array(
			'id'     => $uid,
			'type'   => 'url',
			'name'   => dashy_filter_spans( $title ),
			'url'    => $href,
			'parent' => '',
			'cpt'    => ''
		);
		$uid++;

		// check if item name is in cpt details
		$cpt_key = dashy_multi_search( $cpt_details, 'label_name', dashy_filter_spans( $item[0] ) );

		// check if we have a match
		// if so, list add "list _____(cpt)" item
		if ( $cpt_key !== FALSE ) {

			// get cpt name
			$cpt_name = $cpt_details[ $cpt_key ]['name'];

			// setup data
			$dashy_data[] = array(
				'id'     => $uid,
				'type'   => 'select',
				'var'    => $cpt_name, // this is the cpt
				'name'   => 'List ' . $cpt_details[ $cpt_key ]['label_name'],
				'parent' => dashy_filter_spans( $item[0] ),
				'cpt'    => ''
			);
			$uid ++;

			// get option for this cpt
			$cpt_posts = get_option( 'dashy_data_' . $cpt_name );

			// do we have data?
			if ( $cpt_posts && is_array( $cpt_posts ) ) {

				// sort
				asort( $cpt_posts );

				// add data
				$list_cpts[ 'dashy_' . $cpt_name ] = array_values( $cpt_posts );
			}
		}

		if ( ! empty( $submenu_items ) ) {
			//echo "\n\t<ul class='wp-submenu wp-submenu-wrap'>";
			//echo "<li class='wp-submenu-head'>{$item[0]}</li>";


			// 0 = menu_title, 1 = capability, 2 = menu_slug, 3 = page_title, 4 = classes
			foreach ( $submenu_items as $sub_key => $sub_item ) {
				if ( ! current_user_can( $sub_item[1] ) )
					continue;

				//$class = array();

				$menu_file = $item[2];

				if ( false !== ( $pos = strpos( $menu_file, '?' ) ) )
					$menu_file = substr( $menu_file, 0, $pos );

				// Handle current for post_type=post|page|foo pages, which won't match $self.
				//$self_type = ! empty( $typenow ) ? $self . '?post_type=' . $typenow : 'nothing';

				$menu_hook = get_plugin_page_hook($sub_item[2], $item[2]);
				$sub_file = $sub_item[2];
				if ( false !== ( $pos = strpos( $sub_file, '?' ) ) )
					$sub_file = substr($sub_file, 0, $pos);

				$title = wptexturize($sub_item[0]);

				if ( ! empty( $menu_hook ) || ( ( 'index.php' != $sub_item[2] ) && file_exists( WP_PLUGIN_DIR . "/$sub_file" ) && ! file_exists( ABSPATH . "/wp-admin/$sub_file" ) ) ) {
					// If admin.php is the current page or if the parent exists as a file in the plugins or admin dir
					if ( ( ! $admin_is_parent && file_exists( WP_PLUGIN_DIR . "/$menu_file" ) && ! is_dir( WP_PLUGIN_DIR . "/{$item[2]}" ) ) || file_exists( $menu_file ) )
						$sub_item_url = add_query_arg( array( 'page' => $sub_item[2] ), $item[2] );
					else
						$sub_item_url = add_query_arg( array( 'page' => $sub_item[2] ), 'admin.php' );

					$sub_item_url = esc_url( $sub_item_url );
					//echo "<li$class><a href='$sub_item_url'$class>$title</a></li>";
					$href = "$sub_item_url";
				} else {
					//echo "<li$class><a href='{$sub_item[2]}'$class>$title</a></li>";
					$href = "{$sub_item[2]}";
				}

				// add item to menu
				$dashy_data[] = array(
					'id'     => $uid,
					'type'   => 'url',
					'name'   => dashy_filter_spans( $title ),
					'url'    => $href,
					'parent' => '',
					'cpt'    => ''
				);
				$uid++;

			}
			//echo "</ul>";
		}
		//echo "</li>";
	}

	//echo '<li id="collapse-menu" class="hide-if-no-js"><div id="collapse-button"><div></div></div>';
	//echo '<span>' . esc_html__( 'Collapse menu' ) . '</span>';
	//echo '</li>';

	//dashy_debug( $dashy_data );
	//return $dashy_data;

	// append dashy_data
	$list_cpts['main_menu'] = $dashy_data;

	//return array( 'main_menu' => $dashy_data );
	return $list_cpts;
}