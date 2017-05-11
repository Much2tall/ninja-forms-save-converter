<?php

if ( ! defined( 'ABSPATH' ) )
    exit;

class NF_Conversion_Data_Controller {
    
    
        private $db;
    
    public function init() {
        global $wpdb;
        $this->db = $wpdb;
    }
    
    public function getFormData( $id ) {
        $sql = "SELECT p.id FROM `" . $this->db->prefix . "posts` AS p LEFT JOIN `" . $this->db->prefix .
            "postmeta` AS m ON p.id = m.post_id WHERE m.meta_key = '_form_id' AND m.meta_value = " . intval( $id );
        $result = $this->db->get_results( $sql );
        return $result;
    }
}

//$myController = new NF_Conversion_Data_Controller;
//$myController->init();
//echo('<pre>');
//var_dump( $myController->getFormData( 8 ) );
//echo('</pre>');
//die();