<?php if ( ! defined( 'ABSPATH' ) ) exit;

function nf_get_settings(){
  $instance = NF_SaveConverter();
  if ( ! empty ( $instance ) && ! empty ( $instance->plugin_settings ) ) {
	$settings = NF_SaveConverter()->plugin_settings;
  } else {
  	$settings = NF_SaveConverter()->get_plugin_settings();
  }

  return $settings;
} // nf_get_settings