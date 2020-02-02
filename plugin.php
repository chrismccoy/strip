<?php
/**
 * Plugin Name: Strip Lightbox
 * Plugin URI: http://github.com/chrismccoy/strip
 * Description: Use this plugin to implement the strip lightbox
 * Version: 2.0
 * Author: Chris McCoy
 * Author URI: http://github.com/chrismccoy

 * @copyright 2020
 * @author Chris McCoy
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @package Strip_Lightbox
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Initiate Strip Lightbox Class on plugins_loaded
 *
 * @since 1.0
 */

if ( !function_exists( 'strip_lightbox' ) ) {

	function strip_lightbox() {
		$strip_lightbox = new Strip_Lightbox();
	}

	add_action( 'plugins_loaded', 'strip_lightbox' );
}

/**
 * Strip Lightbox Class for scripts, styles, images, and video embeds
 *
 * @since 1.0
 */

if( !class_exists( 'Strip_Lightbox' ) ) {

	class Strip_Lightbox {

		/**
 		* Hook into hooks for Register styles, scripts, images, and video embeds
 		*
 		* @since 1.0
 		*/

		function __construct() {
			add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'styles' ) );
			add_filter( 'post_gallery', array( $this, 'gallery'), 10, 2 );
			add_filter( 'media_send_to_editor', array( $this, 'media_filter'), 20, 2);
			add_action( 'admin_menu', array( $this, 'strip_add_admin_menu' ));
			add_action( 'admin_init', array( $this, 'strip_settings_init' ));
			add_filter( 'oembed_dataparse', array($this, 'oembed_services'), 10, 3);
			add_action( 'init', array( $this, 'embeds' ));
		}

		/**
		 * add options page 
		 *
		 * @since 1.0
		 */

		function strip_add_admin_menu() { 
			add_options_page( 'Strip Lightbox', 'Strip Lightbox', 'manage_options', 'strip_lightbox', array($this, 'strip_lightbox_options_page' ));
		}

		/**
		 * adding settings section and fields
		 *
		 * @since 1.0
		 */

		function strip_settings_init() { 

			register_setting( 'strip_plugin_page', 'strip_settings' );

			if( false == get_option( 'strip_settings' ) ) { 

				$defaults = array(
					'strip_select_field' => 'left'
				);

				add_option( 'strip_settings', $defaults );
			}

			add_settings_section(
				'strip_plugin_page_section', 
				null,
				null,
				'strip_plugin_page'
			);

			add_settings_field( 
				'strip_select_field', 
				__( 'Position of the Lightbox', 'strip' ), 
				array($this, 'strip_select_field_render'), 
				'strip_plugin_page', 
				'strip_plugin_page_section' 
			);


		}

		/**
		 * render select field
		 *
		 * @since 1.0
		 */

		function strip_select_field_render() { 

			$options = get_option( 'strip_settings' );
			?>
			<select name='strip_settings[strip_select_field]'>
				<option value='left' <?php selected( $options['strip_select_field'], 'left' ); ?>>Left</option>
				<option value='right' <?php selected( $options['strip_select_field'], 'right' ); ?>>Right</option>
				<option value='top' <?php selected( $options['strip_select_field'], 'top' ); ?>>Top</option>
				<option value='bottom' <?php selected( $options['strip_select_field'], 'bottom' ); ?>>Bottom</option>
			</select>
		<?php
		}

		/**
		 * settings options page form
		 *
		 * @since 1.0
		 */

		function strip_lightbox_options_page() { 

			?>
			<form action='options.php' method='post'>

				<h2>Strip Lightbox</h2>

				<?php
				settings_fields( 'strip_plugin_page' );
				do_settings_sections( 'strip_plugin_page' );
				submit_button();
				?>
			</form>
			<?php
		}

		/**
		 * enqueue strip lightbox javascript
		 *
		 * @since 1.0
		 */

		function scripts() {
			//wp_enqueue_script('strip_js', plugins_url('js/strip.min.js', __FILE__), array( 'jquery' ), '1.0', true);
			wp_enqueue_script('strip_js', 'https://cdn.jsdelivr.net/gh/staaky/strip/dist/js/strip.pkgd.min.js', array( 'jquery' ), '1.0', true);
        	}

		/**
		 * enqueue strip lightbox styles
		 *
		 * @since 1.0
		 */

		function styles() {
			//wp_enqueue_style( 'strip_css', plugins_url( 'css/strip.css', __FILE__ ), false, '1.0', 'screen' );
			wp_enqueue_style( 'strip_css', 'https://cdn.jsdelivr.net/gh/staaky/strip/dist/css/strip.css', false, '1.0', 'screen' );

			if ( @file_exists( get_stylesheet_directory() . '/strip_custom.css' ) )
				$css_file = get_stylesheet_directory_uri() . '/strip_custom.css';
			elseif ( @file_exists( get_template_directory() . '/strip_custom.css' ) )
				$css_file = get_template_directory_uri() . '/strip_custom.css';
			else
				$css_file = plugins_url( 'css/custom.css', __FILE__ );

			wp_enqueue_style( 'strip_custom_css', $css_file, false, '1.0', 'screen' );
		}

		/**
		 * register oembed for image urls for the lightbox
		 *
		 * @since 1.0
		 */

		function embeds() {
			wp_embed_register_handler( 'detect_lightbox', '#^https?://.+\.(jpe?g|gif|png)$#i', array( $this, 'wp_embed_register_handler') , 10, 3);
		}

        	/**
         	* convert image urls to oembed with strip markup
         	*
         	* @since 1.0
         	*/

		function wp_embed_register_handler( $matches, $attr, $url, $rawattr ) {
			global $post;

			$position_option = get_option( 'strip_settings' );
			$position = "'" . $position_option['strip_select_field'] . "'";
    	       		$embed = sprintf('<a href="%s" class="strip thumbnail" data-strip-group="gallery-%s" data-strip-options="side: %s"><img src="%s" width="%d" height="%d" /></a>', $url, $post->ID, $position, $url, get_option('thumbnail_size_w'), get_option('thumbnail_size_h'));
			$embed = apply_filters( 'oembed_detect_lightbox', $embed, $matches, $attr, $url, $rawattr );

    			return apply_filters( 'oembed_result', $embed, $url);
		}

        	/**
         	* filter instagram, flickr, cloudup, imgur, youtube, and vimeo for lightbox
         	*
         	* @since 1.0
         	*/

		function oembed_services($html, $data, $url) {

			$position_option = get_option( 'strip_settings' );
			$position = "'" . $position_option['strip_select_field'] . "'";

        		if ($data->provider_name == 'Instagram') {
                		$html = sprintf('<a href="%s" class="strip thumbnail" data-strip-options="side: %s"><img src="%s" width="%d" height="%d" /></a>', esc_url($data->thumbnail_url), $position, esc_url($data->thumbnail_url), get_option('thumbnail_size_w'), get_option('thumbnail_size_h'));
			}

        		if ($data->provider_name == 'Flickr') {
				$url = str_replace('_z.jpg','_b.jpg', $data->url);
                		$html = sprintf('<a href="%s" class="strip thumbnail" data-strip-options="side: %s"><img src="%s" width="%d" height="%d" /></a>', esc_url($url), $position, esc_url($url), get_option('thumbnail_size_w'), get_option('thumbnail_size_h'));
			}

        		if ($data->provider_name == 'Cloudup') {
                		$html = sprintf('<a href="%s" class="strip thumbnail" data-strip-options="side: %s"><img src="%s" width="%d" height="%d" /></a>', esc_url(preg_replace('#(\d+)[Xx](\d+)#', '3000x3000', $data->url)), $position, esc_url($data->url),  get_option('thumbnail_size_w'), get_option('thumbnail_size_h'));
			}

      			if ($data->provider_name == 'Imgur') {
                		$html = sprintf('<a href="%s" class="strip thumbnail" data-strip-options="side: %s"><img src="%s" width="%d" height="%d" /></a>', esc_url($url), $position, esc_url($url), get_option('thumbnail_size_w'), get_option('thumbnail_size_h'));
			}

      			if ($data->provider_name == 'YouTube' || $data->provider_name == 'Vimeo') {
                        	$screenshot = wp_get_attachment_url( get_post_thumbnail_id($post_ID) ) ? wp_get_attachment_url( get_post_thumbnail_id($post_ID) ) : 'http://fakeimg.pl/439x230/282828/eae0d0/?text=Click%20to%20Play!';
                		$html = sprintf('<a href="%s" class="strip" data-strip-options="side: %s"><img src="%s" /></a>', esc_url($url), $position, $screenshot);
			}

			return $html;
		}

        	/**
         	* add strip data attributes to images inserted into post
         	*
         	* @since 1.0
         	*/

		function media_filter($html, $attachment_id) {

			$position_option = get_option( 'strip_settings' );
			$position = "'" . $position_option['strip_select_field'] . "'";

    			$attachment = get_post($attachment_id);

			$types = array('image/jpeg', 'image/gif', 'image/png');

			if(in_array($attachment->post_mime_type, $types) ) {
				$srcset = (wp_get_attachment_image_srcset( $attachment_id, 'thumbnail')) ? 'srcset="' . wp_get_attachment_image_srcset( $attachment_id, 'thumbnail') . '" ' : '';
				$sizes = (wp_get_attachment_image_sizes( $attachment_id, 'thumbnail')) ? 'sizes="' . wp_get_attachment_image_sizes( $attachment_id, 'thumbnail') . '"' : '';
				$strip_attr = sprintf('class="strip thumbnail" data-strip-group="gallery-%s" data-strip-options="side: %s"', $attachment->post_parent, $position);
    				$html = '<a href="'. wp_get_attachment_url($attachment_id) .'" '. $strip_attr .'"><img src="'. wp_get_attachment_thumb_url($attachment_id) .'" '. $srcset . $sizes .'></a>';
			}

			return $html;
		}

        	/**
         	* modified gallery output for strip lightbox
         	*
         	* @since 1.0
         	*/

		function gallery( $content, $attr ) {
    			global $instance, $post;

    			$instance++;

    			if ( isset( $attr['orderby'] ) ) {
        			$attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
        			if ( ! $attr['orderby'] )
            				unset( $attr['orderby'] );
    			}

    			extract( shortcode_atts( array(
        			'order'      =>  'ASC',
        			'orderby'    =>  'menu_order ID',
        			'id'         =>  $post->ID,
        			'itemtag'    =>  'figure',
        			'icontag'    =>  'div',
        			'captiontag' =>  'figcaption',
        			'columns'    =>   3,
        			'size'       =>   'thumbnail',
        			'include'    =>   '',
        			'exclude'    =>   ''
    			), $attr ) );

    			$id = intval( $id );

    			if ( 'RAND' == $order ) {
        			$orderby = 'none';
    			}

    			if ( $include ) {
        
        			$include = preg_replace( '/[^0-9,]+/', '', $include );
        
        			$_attachments = get_posts( array(
            				'include'        => $include,
            				'post_status'    => 'inherit',
            				'post_type'      => 'attachment',
            				'post_mime_type' => 'image',
            				'order'          => $order,
            				'orderby'        => $orderby
        			) );

        			$attachments = array();
        
        			foreach ( $_attachments as $key => $val ) {
            				$attachments[$val->ID] = $_attachments[$key];
        			}

    				} elseif ( $exclude ) {
        
        				$exclude = preg_replace( '/[^0-9,]+/', '', $exclude );
        
        				$attachments = get_children( array(
            					'post_parent'    => $id,
            					'exclude'        => $exclude,
            					'post_status'    => 'inherit',
            					'post_type'      => 'attachment',
            					'post_mime_type' => 'image',
            					'order'          => $order,
            					'orderby'        => $orderby
        				) );

    				} else {

        				$attachments = get_children( array(
            					'post_parent'    => $id,
            					'post_status'    => 'inherit',
            					'post_type'      => 'attachment',
            					'post_mime_type' => 'image',
            					'order'          => $order,
            					'orderby'        => $orderby
        				) );

    				}

    				if ( empty( $attachments ) ) {
        				return;
    				}

    				if ( is_feed() ) {
        				$output = "\n";
        				foreach ( $attachments as $att_id => $attachment )
            					$output .= wp_get_attachment_link( $att_id, $size, true ) . "\n";
        				return $output;
    				}

    				$output = "\n" . '<div class="strip_gallery">' . "\n";

				$position_option = get_option( 'strip_settings' );
				$position = "'" . $position_option['strip_select_field'] . "'";

    				foreach ( $attachments as $id => $attachment ) {
					$srcset = (wp_get_attachment_image_srcset( $id, 'thumbnail')) ? 'srcset="' . wp_get_attachment_image_srcset( $id, 'thumbnail') . '" ' : '';
					$sizes = (wp_get_attachment_image_sizes( $id, 'thumbnail')) ? 'sizes="' . wp_get_attachment_image_sizes( $id, 'thumbnail') . '"' : '';
					$caption = ($attachment->post_excerpt) ? $attachment->post_excerpt : $post->post_title;
					$strip_attr = sprintf('class="strip thumbnail" data-strip-group="gallery-%s" data-strip-caption="%s" data-strip-options="side: %s"', $post->ID, $caption, $position);
        				$output .= '<a href="'. wp_get_attachment_url($id) .'" '. $strip_attr. '"><img src="'. wp_get_attachment_thumb_url($id) .'" class="strip thumbnail" '. $srcset  . $sizes .'></a>' . "\n";
    				}

    				$output .= "</div>" . "\n";

    			return $output;
		}
   	}
}
