<?php

/*
Plugin Name: Ninja Forms - Save Conversion Tool
Plugin URI: http://ninjaforms.com/
Description: The Save Conversion Tool is provided to convert Saved records into Form Submissions in Ninja Forms.
Version: 3.0
Author: The WP Ninjas
Author URI: http://ninjaforms.com
Text Domain: ninja-forms-save-converter
Domain Path: /lang/

Copyright 2017 WP Ninjas.
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
    exit;

class NF_SaveConverter {


    /**
     * @var NF_SaveConverter
     * @since 1.0
     */
    private static $instance;

    /**
     * Main NF_SaveConverter Instance
     *
     * Insures that only one instance of NF_SaveConverter exists in memory at any one
     * time. Also prevents needing to define globals all over the place.
     *
     * @since 1.0
     * @static
     * @staticvar array $instance
     * @return The highlander NF_SaveConverter
     */
    public static function instance() {
        if ( ! isset( self::$instance ) && ! ( self::$instance instanceof NF_SaveConverter ) ) {
            self::$instance = new NF_SaveConverter;
            self::$instance->setup_constants();
            self::$instance->includes();

            // TODO: Might need this later?
//            register_activation_hook( __FILE__, 'nf_save_converter_activation' );
            add_action( 'init', array( self::$instance, 'init' ), 5 );
            add_action( 'admin_init', array( self::$instance, 'admin_init' ), 5 );
            add_action( 'pre_get_posts', array( self::$instance, 'filter_subs' ), 5 );
            add_action( 'admin_enqueue_scripts', array( self::$instance,'nf_sc_admin_js' ), 11 );
            add_action( 'load-edit.php', array( self::$instance, 'convert_listen' ) );
        }

        return self::$instance;
    }

    /**
     * Run all of our plugin stuff on init.
     * This allows filters and actions to be used by third-party classes.
     *
     * @since 1.0
     * @return void
     */
    public function init() {
        // The settings variable will hold our plugin settings.
        self::$instance->plugin_settings = self::$instance->get_plugin_settings();
        //echo('<style type="text/css">table .type-nf_sub{ display:none; } table .nf-sub-saved{ display:table-row !important; }</style>');
    }

    /**
     * Run all of our plugin stuff on admin init.
     *
     * @since 1.0
     * @return void
     */
    public function admin_init() {
        self::$instance->update_version_number();
	    $page = add_menu_page( "Ninja Forms Save Converter" , __( 'Ninja Forms Save Conversion Tool', 'ninja-forms' ), apply_filters( 'ninja_forms_admin_parent_menu_capabilities', 'manage_options' ), "edit.php?post_type=nf_sub&convert=saves", "", "dashicons-feedback" );
    }
    
    // Function to hook into our Submissions page and only display Saves.
    public function filter_subs( $query ) {
        global $pagenow, $typenow;
        if( $pagenow == 'edit.php' && $typenow == 'nf_sub' ) {
            $args = array(
                array(
                    'key' => '_action',
                    'value' => 'save',
                    'compare' => '='
                )
            );
            if ( isset( $query->query_vars[ 'meta_query' ] ) && is_array( $query->query_vars[ 'meta_query' ] ) ) {
                $args = array_merge($args, $query->query_vars[ 'meta_query' ] );
            }
            $query->set( 'meta_query', $args );
        }
        return;
    }
    
    public function nf_sc_admin_js() {
        global $pagenow, $typenow;
        if( $pagenow == 'edit.php' && $typenow == 'nf_sub' ) {
            wp_dequeue_style( 'nf-sp-admin' );
            $nf = Ninja_Forms();
            remove_action( 'manage_posts_custom_column', array( $nf->subs_cpt, 'custom_columns' ), 10 );
            add_action( 'manage_posts_custom_column', array( $this, 'custom_columns' ), 10, 2 );
            remove_action( 'admin_footer-edit.php', array( $nf->subs_cpt, 'bulk_admin_footer' ) );
            add_action( 'admin_footer-edit.php', array( $this, 'bulk_admin_footer' ) );
        }
    }
    
    public function convert_listen() {
        
		// Bail if we aren't in the admin
		if ( ! is_admin() )
			return false;

		if ( ! isset ( $_REQUEST['form_id'] ) || empty ( $_REQUEST['form_id'] ) )
			return false;
        
        $nf = Ninja_Forms();
        global $wpdb;

		if ( isset ( $_REQUEST['convert_save'] ) && ! empty( $_REQUEST['convert_save'] ) ) {
            $sql = "UPDATE `" . $wpdb->prefix . "postmeta` SET meta_value = 'submit' WHERE post_id = " . intval( $_REQUEST['convert_save'] ) . " AND meta_key = '_action'";
            //echo($sql);
            $wpdb->query( $sql );
        }

		if ( ( isset ( $_REQUEST['action'] ) && $_REQUEST['action'] == 'convert_saves' ) || ( isset ( $_REQUEST['action2'] ) && $_REQUEST['action2'] == 'convert_saves' ) ) {
            if ( empty( $_REQUEST['post'] ) )
                return false;
            
            $sql = "UPDATE `" . $wpdb->prefix . "postmeta` SET meta_value = 'submit' WHERE post_id IN (";
            foreach($_REQUEST['post'] as $sub) {
                $sql .= intval( $sub ) . ", ";
            }
            $sql = substr( $sql, 0, ( strlen($sql) -2 ) );
            $sql .=  ") AND meta_key = '_action'";
            //echo($sql);
            $wpdb->query( $sql );
		}
        //die();
    }
    
	public function bulk_admin_footer() {
		global $post_type;

		if ( ! is_admin() )
			return false;

		if( $post_type == 'nf_sub' && isset ( $_REQUEST['post_status'] ) && $_REQUEST['post_status'] == 'all' ) {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function() {
                    jQuery('<option>').val('convert_saves').text('<?php _e('Convert to Submission')?>').appendTo("select[name='action']");
                    jQuery('<option>').val('convert_saves').text('<?php _e('Convert to Submission')?>').appendTo("select[name='action2']");
                    
//					jQuery('<option>').val('export').text('<?php _e('Export')?>').appendTo("select[name='action']");
//					jQuery('<option>').val('export').text('<?php _e('Export')?>').appendTo("select[name='action2']");
					<?php
//					if ( ( isset ( $_POST['action'] ) && $_POST['action'] == 'export' ) || ( isset ( $_POST['action2'] ) && $_POST['action2'] == 'export' ) ) {
                    if ( ( isset ( $_POST['action'] ) && $_POST['action'] == 'convert_saves' ) || ( isset ( $_POST['action2'] ) && $_POST['action2'] == 'convert_saves' ) ) {
                        ?>
                        setInterval(function(){
                            jQuery( "select[name='action'" ).val( '-1' );
                            jQuery( "select[name='action2'" ).val( '-1' );
                            jQuery( '#posts-filter' ).submit();
                        },5000);
                        <?php
					}

					?>
				});
			</script>
			<?php
		}
	}
    
    
	public function custom_columns( $column, $sub_id ) {
		if ( isset ( $_GET['form_id'] ) ) {
			$form_id = $_GET['form_id'];
            $nf = Ninja_Forms();
			if ( $column == 'id' ) {
				echo apply_filters( 'nf_sub_table_seq_num', $nf->sub( $sub_id )->get_seq_num(), $sub_id, $column );
				echo '<div class="locked-info"><span class="locked-avatar"></span> <span class="locked-text"></span></div>';
				if ( !isset ( $_GET['post_status'] ) || $_GET['post_status'] == 'all' ) {
					echo '<div class="row-actions custom-row-actions">';
					//do_action( 'nf_sub_table_before_row_actions', $sub_id, $column );
					echo '<span class="edit"><a href="post.php?post=' . $sub_id . '&action=edit&ref=' . urlencode( esc_url(  add_query_arg( array() ) ) ) . '" title="' . __( 'Edit this item', 'ninja-forms' ) . '">' . __( 'Edit', 'ninja-forms' ) . '</a> | </span> <span class="edit"><a href="' . esc_url( add_query_arg( array( 'convert_save' => $sub_id ) ) ) . '" title="' . __( 'Convert this Save to a Submission', 'ninja-forms' ) . '">' . __( 'Convert to Submission', 'ninja-forms' ) . '</a> | </span>';
                    
                    
//						<span class="edit"><a href="' . esc_url( add_query_arg( array( 'export_single' => $sub_id ) ) ) . '" title="' . __( 'Export this item', 'ninja-forms' ) . '">' . __( 'Export', 'ninja-forms' ) . '</a> | </span>';
					
                    
                    
//                    $row_actions = apply_filters( 'nf_sub_table_row_actions', array(), $sub_id, $form_id );
//					if ( ! empty( $row_actions ) ) {
//						echo implode(" | ", $row_actions);
//						echo '| ';
//					}
					echo '<span class="trash"><a class="submitdelete" title="' . __( 'Move this item to the Trash', 'ninja-forms' ) . '" href="' . get_delete_post_link( $sub_id ) . '">' . __( 'Trash', 'ninja-forms' ) . '</a> </span>';
					do_action( 'nf_sub_table_after_row_actions', $sub_id, $column );
					echo '</div>';
				} else {
					echo '<div class="row-actions custom-row-actions">';
					do_action( 'nf_sub_table_before_row_actions_trash', $sub_id, $column );
					echo '<span class="untrash"><a title="' . esc_attr( __( 'Restore this item from the Trash' ) ) . '" href="' . wp_nonce_url( sprintf( get_edit_post_link( $sub_id ) . '&amp;action=untrash', $sub_id ) , 'untrash-post_' . $sub_id ) . '">' . __( 'Restore' ) . '</a> | </span> 
					<span class="delete"><a class="submitdelete" title="' . esc_attr( __( 'Delete this item permanently' ) ) . '" href="' . get_delete_post_link( $sub_id, '', true ) . '">' . __( 'Delete Permanently' ) . '</a></span>';
					do_action( 'nf_sub_table_after_row_actions_trash', $sub_id, $column );
					echo '</div>';
				}
			} else if ( $column == 'sub_date' ) {
				$post = get_post( $sub_id );
				if ( '0000-00-00 00:00:00' == $post->post_date ) {
					$t_time = $h_time = __( 'Unpublished' );
					$time_diff = 0;
				} else {
					$t_time = get_the_time( 'Y/m/d g:i:s A' );
					$m_time = $post->post_date;
					$time = get_post_time( 'G', true, $post );

					$time_diff = time() - $time;

					if ( $time_diff > 0 && $time_diff < DAY_IN_SECONDS )
						$h_time = sprintf( __( '%s ago' ), human_time_diff( $time ) );
					else
						$h_time = mysql2date( 'Y/m/d', $m_time );
				}

				$t_time = apply_filters( 'nf_sub_title_time', $t_time );
				$h_time = apply_filters( 'nf_sub_human_time', $h_time );

				/** This filter is documented in wp-admin/includes/class-wp-posts-list-table.php */
				echo '<abbr title="' . $t_time . '">' . $h_time . '</abbr>';

				echo '<br />';
				echo apply_filters( 'nf_sub_table_status', __( 'Submitted', 'ninja-forms' ), $sub_id );

			} else if ( strpos( $column, '_field_' ) !== false ) {
				global $ninja_forms_fields;

				$field_id = str_replace( 'form_' . $form_id . '_field_', '', $column );
				//if ( apply_filters( 'nf_add_sub_value', $nf->field( $field_id )->type->add_to_sub, $field_id ) ) {
				$field = $nf->form( $form_id )->fields[ $field_id ];
				$field_type = $field['type'];
				if ( isset ( $ninja_forms_fields[ $field_type ] ) ) {
					$reg_field = $ninja_forms_fields[ $field_type ];
				} else {
					$reg_field = array();
				}

				if ( isset ( $reg_field['sub_table_value'] ) ) {
					$edit_value_function = $reg_field['sub_table_value'];
				} else {
					$edit_value_function = 'nf_field_text_sub_table_value';
				}

				$user_value = $nf->sub( $sub_id )->get_field( $field_id );

				$args['field_id'] = $field_id;
				$args['user_value'] = ninja_forms_esc_html_deep( $user_value );
				$args['field'] = $field;

				call_user_func_array( $edit_value_function, $args );
				//}
			}
		}
	}

    /**
     * Setup plugin constants
     *
     * @access private
     * @since 1.0
     * @return void
     */
    private function setup_constants() {
        
    }

    /**
     * Include our Class files
     *
     * @access private
     * @since 1.0
     * @return void
     */
    private function includes() {
        require_once( 'includes/data-controller.php' );
    }
    
    private function get_plugin_settings() {
        return '';
    }
    
    private function update_version_number() {
        
    }

} // End Class

/**
 * The main function responsible for returning The Highlander Ninja_Forms
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $nf = Ninja_Forms(); ?>
 *
 * @since 1.0
 * @return object The Highlander Ninja_Forms Instance
 */
function NF_SaveConverter() {
    return NF_SaveConverter::instance();
}

NF_SaveConverter();

/*
|--------------------------------------------------------------------------
| Uninstall Hook
|--------------------------------------------------------------------------
*/

register_uninstall_hook( __FILE__, 'nf_save_converter_uninstall' );

function nf_save_converter_uninstall() {
    
}

function nf_save_converter_activation() {
    
}
