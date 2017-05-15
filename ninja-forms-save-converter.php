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

            add_action( 'admin_init', array( self::$instance, 'admin_init' ), 5 );
            // Ensure that we only show Saves in the Submission table.
            add_action( 'pre_get_posts', array( self::$instance, 'filter_subs' ), 5 );
            // Hijack 2.9 scripts.
            add_action( 'admin_enqueue_scripts', array( self::$instance,'nf_sc_admin_js' ), 11 );
            // Listen for clicks on our conversion buttons.
            add_action( 'load-edit.php', array( self::$instance, 'convert_listen' ) );
            // Listen for AJAX calls.
            add_action( 'wp_ajax_nfsc_bulk_email', array( self::$instance, 'bulk_conversion_email' ) );
        }

        return self::$instance;
    }

    /**
     * Run all of our plugin stuff on admin init.
     *
     * @since 1.0
     * @return void
     */
    public function admin_init() {
        self::$instance->update_version_number();
	    $page = add_menu_page( "Ninja Forms Save Conversion Tool" , __( 'Ninja Forms Save Conversion Tool', 'ninja-forms' ), apply_filters( 'ninja_forms_admin_parent_menu_capabilities', 'manage_options' ), "edit.php?post_type=nf_sub&convert=saves", "", "dashicons-feedback" );
    }
    
    /**
     * Hook into our Submissions page and apply a filter to show only Saves.
     * 
     * @since 1.0
     * @param array $query The post meta query.
     * @return void
     */
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
    
    /**
     * Remove the existing NF scritps from the Submissions page and apply new ones.
     * 
     * @since 1.0
     * @return void
     */
    public function nf_sc_admin_js() {
        global $pagenow, $typenow;
        if( $pagenow == 'edit.php' && $typenow == 'nf_sub' ) {
            wp_enqueue_script( 'nfsc-jbox', plugin_dir_url( __FILE__ ) . 'assets/js/jBox.min.js', array( 'jquery' ) );
            wp_dequeue_style( 'nf-sp-admin' );
            $nf = Ninja_Forms();
            remove_action( 'manage_posts_custom_column', array( $nf->subs_cpt, 'custom_columns' ), 10 );
            add_action( 'manage_posts_custom_column', array( $this, 'custom_columns' ), 10, 2 );
            remove_action( 'admin_footer-edit.php', array( $nf->subs_cpt, 'bulk_admin_footer' ) );
            add_action( 'admin_footer-edit.php', array( $this, 'bulk_admin_footer' ) );
            add_action( 'admin_notices', array( $this, 'conversion_mode_message') );
        }
    }
    
    /**
     * Listen for clicks on our convert buttons and respond accordingly.
     * 
     * @since 1.0
     * @return false/void;
     */
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
        
        if ( isset( $_REQUEST['bulk_email'] ) && $_REQUEST['bulk_email'] ) {
            $this->bulk_conversion_email( $_REQUEST['form_id'] );
        }
        //die();
    }
    
    /**
     * Register a notice about Conversion Mode being enabled.
     * 
     * @since 1.0
     * @return void
     */
    public function conversion_mode_message() {
        
        $message = '<div class="update-nag nf-admin-notice">
                <div class="nf-notice-logo"></div>
                <p class="nf-notice-title" style="color:#ED494D;">Conversion Mode Activated!</p>
                <p class="nf-notice-body">Only Saves are being shown on your Submissions page. To restore default functionality, disable Ninja Forms - Save Conversion Tool from your Plugins Menu.</p>
                </div>';
        _e( $message );
        wp_enqueue_style( 'nf-admin-notices', NINJA_FORMS_URL .'assets/css/admin-notices.css?nf_ver=' . NF_PLUGIN_VERSION );
    }
    
    /**
     * Register our new options in the bulk actions.
     * 
     * @since 1.0
     * @return false/void
     */
	public function bulk_admin_footer() {
		global $post_type;

		if ( ! is_admin() )
			return false;
        
		if( $post_type == 'nf_sub' && isset ( $_REQUEST['post_status'] ) && $_REQUEST['post_status'] == 'all' ) {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function() {
                    // Create our Email Button.
                    jQuery('<div>').text('<?php _e('Email Users with Saves'); ?>').addClass('nfsc-bulk-email').appendTo("#wpbody-content");
                    // Add options to the bulk action list.
                    jQuery('<option>').val('convert_saves').text('<?php _e('Convert to Submissions')?>').appendTo("select[name='action']");
                    jQuery('<option>').val('convert_saves').text('<?php _e('Convert to Submissions')?>').appendTo("select[name='action2']");
                    <?php
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
                    // Setup our modal.
                    var title = 'Composing Email to Users';
                    var content = '<div><label for="nf-bulk-from">From Address:</label><input type="text" name="from" id="nf-bulk-from" /></div>' +
                        '<div><label for="nf-bulk-subject">Subject:</label><input type="text" name="subject" id="nf-bulk-subject" /></div>' +
                        '<div><label for="nf-bulk-message">Message:</label><textarea id="nf-bulk-message"></textarea></div>' +
                        '<div id="nf-bulk-error"></div>'+
                        '<div id="nf-modal-send" class="modal-button">Send</div><div id="nf-modal-cancel" class="modal-button">Cancel</div>';
                    var bulkBox = new jBox('Modal', {
                        width: 440,
                        height: 500,
                        attach: '.nfsc-bulk-email',
                        title: title,
                        content: content,
                        addClass: 'nfsc-composer',
                        closeButton: false,
                        closeOnClick: false,
                        closeOnEsc: false,
                        overlay: true,
                        onCreated: function(){
                            // Listen for clicks on our Send button.
                            document.getElementById('nf-modal-send').addEventListener('click', function(){
                                var emailFrom = document.getElementById('nf-bulk-from');
                                var errorBox = document.getElementById('nf-bulk-error');
                                // Check to make sure the from address is valid.
                                if( nfscCheckEmail( emailFrom ) ) {
                                    var data = {
                                        form_id: '<?php echo($_REQUEST['form_id']) ?>',
                                        from: emailFrom.value,
                                        subject: document.getElementById('nf-bulk-subject').value,
                                        message: document.getElementById('nf-bulk-message').value
                                    };
                                    data = JSON.stringify( data );
                                    var payload = {
                                        action: 'nfsc_bulk_email',
                                        data: data
                                    };
                                    jQuery.ajax({
                                        type: "POST",
                                        url: ajaxurl + '?action=nfsc_bulk_email',
                                        data: payload,
                                        success: function( response ){
                                            bulkBox.close();
                                        },
                                        error: function( response ){
                                            errorBox.innerHTML('Oops. Something went wrong.');
                                        }
                                    });
                                }
                                else {
                                    errorBox.innerHTML = '<?php _e('Please enter a valid email address.', 'ninja-forms') ?>';
                                    emailFrom.classList.add('nf-field-error');
                                    // Listen for changes to the from address, so we can reset our error.
                                    emailFrom.addEventListener('change', function() {
                                        emailFrom.classList.remove('nf-field-error');
                                        errorBox.innerHTML = '';
                                    });
                                }
                            });
                            // Listen for clicks on our Cancel button
                            document.getElementById('nf-modal-cancel').addEventListener('click', function(){
                               bulkBox.close(); 
                            });
                        }
                    });
				});
                function nfscCheckEmail( el ){
                    var emailReg = /^.+@.+\..+/i;
                    var val = el.value;
                    if( 'undefined' === typeof( val ) )
                        return false;
                    if( '' == val )
                        return false;
                    if( ! emailReg.test( val ) )
                        return false;
                    return true;
                }
			</script>
            <style type="text/css">
                .nfsc-composer {
                    background-color: #fff;
                    border-radius: 7px;
                }
                #jBox-overlay {
                    background-color: #000;
                    height: 100%;
                    width: 100%;
                    position: absolute;
                    top: 0;
                    left: 0;
                    opacity: 0.6 !important;
                }
                .nfsc-composer label {
                    display: block;
                    font-size: 120%;
                }
                .nfsc-composer input, .nfsc-composer textarea {
                    width: 100%;
                    margin: 10px 0px;
                }
                .nfsc-composer textarea {
                    min-height: 200px;
                }
                .nfsc-composer .jBox-title {
                    text-align: center;
                    font-size: 150%;
                    padding: 30px 0px;
                }
                .nfsc-composer .jBox-content {
                    padding: 10px 40px;
                }
                .nfsc-composer .modal-button {
                    cursor: pointer;
                    display: inline-block;
                    width: 70px;
                    height: 20px;
                    padding: 5px 0px;
                    text-align: center;
                    border-radius: 3px;
                }
                .nfsc-composer #nf-modal-send {
                    margin-left: 120px;
                    background-color: #00a0d2;
                    color: #fff;
                }
                .nfsc-composer #nf-modal-cancel {
                    margin-left: 60px;
                    background-color: #ccc;
                }
                .nfsc-composer #nf-bulk-error {
                    color: #f00;
                    font-weight: bold;
                    padding: 10px;
                    margin-bottom: 20px;
                }
                .nfsc-composer input.nf-field-error {
                    border: 1px solid #f00;
                }
                .nfsc-bulk-email {
                    border: 1px solid #ccc;
                    color: #0073aa;
                    background-color: #f7f7f7;
                    width: 150px;
                    padding: 4px 8px;
                    font-weight: 600;
                    font-size: 13px;
                    cursor: pointer;
                    text-align: center;
                    border-radius: 2px;
                }
                .nfsc-bulk-email:hover {
                    background-color: #00a0d2;
                    border-color: #008EC2;
                    color: #fff;
                }
            </style>
			<?php
		}
	}
    
    /**
     * Setup our AJAX response.
     * 
     * @since 1.0
     * @return void
     */
    public function bulk_conversion_email() {
        if( !isset($_POST['data'] ) )
            return false;
        $data = json_decode( stripslashes( $_POST['data'] ), TRUE );
//        var_dump($data);
//        die();
        $form_id = intval( $data['form_id'] );
        $subject = empty( $data['subject'] ) ? '(No subject)' : $data['subject'];
        $headers = array();
        $headers[] = 'Content-Type: text/plain';
        $headers[] = 'charset=UTF-8';
        $headers[] = 'From: ' . $data['from'];
        global $wpdb;
        $post_sql = "SELECT p.id FROM `" . $wpdb->prefix ."posts` AS p LEFT JOIN `" . $wpdb->prefix ."postmeta` as m ON p.id = m.post_id WHERE m.meta_key = '_form_id' AND m.meta_value = " . $form_id;
        $save_sql = "SELECT p.id FROM `" . $wpdb->prefix ."posts` AS p LEFT JOIN `" . $wpdb->prefix ."postmeta` as m ON p.id = m.post_id WHERE m.meta_key = '_action' AND m.meta_value = 'save' AND p.id IN(" . $post_sql . ")";
        $sql = "SELECT DISTINCT(u.user_email) FROM `" . $wpdb->prefix ."users` as u  LEFT JOIN `" . $wpdb->prefix ."posts` as p ON u.id = p.post_author WHERE p.id IN(" . $save_sql . ") AND u.user_email IS NOT NULL";
        $result = $wpdb->get_results( $sql, 'ARRAY_A' );
        $to = array();
        foreach( $result as $row ) {
            array_push( $to, $row['user_email'] );
        }
//        var_dump($to);
//        echo($subject);
//        echo($data['message']);
//        echo($from);
        $errors = array();
        try {
            $sent = wp_mail( $to, $subject, $data['message'], $headers );
        } catch ( Exception $e ){
            $sent = false;
            $errors[ 'email_sent' ] = $e->getMessage();
        }
        echo( $sent );
        die();
    }
    
    
    /**
     * Format the Submissions table with our hijacked CPT data.
     * 
     * @since 1.0
     * @param string $column The database column.
     * @param int $sub_id The submission ID.
     * @return void
     */
	public function custom_columns( $column, $sub_id ) {
		if ( isset ( $_GET['form_id'] ) ) {
			$form_id = $_GET['form_id'];
            $nf = Ninja_Forms();
			if ( $column == 'id' ) {
				echo apply_filters( 'nf_sub_table_seq_num', $nf->sub( $sub_id )->get_seq_num(), $sub_id, $column );
				echo '<div class="locked-info"><span class="locked-avatar"></span> <span class="locked-text"></span></div>';
				if ( !isset ( $_GET['post_status'] ) || $_GET['post_status'] == 'all' ) {
					echo '<div class="row-actions custom-row-actions">';
					do_action( 'nf_sub_table_before_row_actions', $sub_id, $column );
					echo '<span class="edit"><a href="post.php?post=' . $sub_id . '&action=edit&ref=' . urlencode( esc_url(  add_query_arg( array() ) ) ) . '" title="' . __( 'Edit this item', 'ninja-forms' ) . '">' . __( 'Edit', 'ninja-forms' ) . '</a> | </span> <span class="edit"><a href="' . esc_url( add_query_arg( array( 'convert_save' => $sub_id ) ) ) . '" title="' . __( 'Convert this Save to a Submission', 'ninja-forms' ) . '">' . __( 'Convert to Submission', 'ninja-forms' ) . '</a> | </span>';
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

    private function update_version_number() {
        
    }

} // End Class

/**
 * The main function responsible for returning The Highlander NF_SaveConverter
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $nfsc = NF_SaveConverter(); ?>
 *
 * @since 1.0
 * @return object The Highlander NF_SaveConverter Instance
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

//register_uninstall_hook( __FILE__, 'nf_save_converter_uninstall' );

function nf_save_converter_uninstall() {
    
}
