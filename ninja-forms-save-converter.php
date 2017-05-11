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
