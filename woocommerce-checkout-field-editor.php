<?php
/*
  Plugin Name: Locad Additional Fields
  Description: Additional checkout fields for specific countries
  Version: 1.0
  License: GPLv2 or later
 */

if (!defined('ABSPATH'))
    die();

global $locad_af_supported_countries;
global $wpdb;
global $locad_af_table_name;

$locad_af_table_name = $wpdb->prefix . "locad_af_should_validate_fields";
$locad_af_supported_countries = ['PH', 'ph'];

register_activation_hook(__FILE__, 'locad_af_install_db');
add_action('plugins_loaded', 'locad_af_fill_db');

add_action('woocommerce_after_checkout_billing_form', 'locad_af_custom_billing_fields');
add_action('woocommerce_after_checkout_shipping_form', 'locad_af_custom_shipping_fields');
add_action('woocommerce_checkout_update_order_meta', 'locad_af_set_my_field_value_to_meta');
add_action('woocommerce_checkout_process', 'locad_af_validate_fields', 1);

add_action('wp_enqueue_scripts', 'locad_af_load_dropdowns_js');
add_action('rest_api_init', 'locad_af_add_municipalities_endpoints');

function locad_af_add_municipalities_endpoints()
{
    register_rest_route('locad', '/(?P<country>.+)/((?P<state>.+))/((?P<type>.+))/municipalities/', array(
        'methods' => 'GET',
        'callback' => 'locad_af_get_municipalities',
        'args' => array(
            'country' => array(
                'default'           => null,
                'required'          => true,
                'validate_callback' => 'locad_af_validate_country',
            ),
            'state' => array(
                'default'           => null,
                'required'          => true,
            ),
            'type' => array(
                'default'           => null,
                'required'          => true,
                'validate_callback' => 'locad_af_validate_type',
            ),
        ),
    ));
}

function locad_af_get_municipalities($request)
{
    $country = strtoupper($request['country']);
    $state = strtoupper($request['state']);
    $type = strtoupper($request['type']);

    $municipalities = locad_af_get_municipalities_json($type, $country, $state);
    if ($municipalities) {
        return $municipalities;
    } else {
        locad_af_set_should_validate($type, false);
        return new WP_REST_Response('Not Found', 404);
    }
}

function locad_af_validate_country($country, $request)
{
    $type = strtoupper($request['type']);
    global $locad_af_supported_countries;
    if (in_array($country, $locad_af_supported_countries)) {
        return true;
    } else {
        locad_af_set_should_validate($type, false);
        return false;
    }
}

function locad_af_validate_type($type)
{
    if (in_array($type, ['shipping', 'billing'])) {
        return true;
    } else {
        locad_af_set_should_validate('shipping', false);
        locad_af_set_should_validate('billing', false);
        return false;
    }
}

function locad_af_set_my_field_value_to_meta($order_id)
{
    if ($_POST['custom_billing_municipalities']) {
        update_post_meta($order_id, 'billing_municipalities', sanitize_text_field($_POST['custom_billing_municipalities']));
    }
    if ($_POST['custom_billing_barangays']) {
        update_post_meta($order_id, 'billing_barangays', sanitize_text_field($_POST['custom_billing_barangays']));
    }
    if ($_POST['custom_billing_state']) {
        update_post_meta($order_id, 'billing_state', sanitize_text_field($_POST['custom_billing_state']));
    }

    if ($_POST['custom_shipping_municipalities']) {
        update_post_meta($order_id, 'shipping_municipalities', sanitize_text_field($_POST['custom_shipping_municipalities']));
    }
    if ($_POST['custom_shipping_barangays']) {
        update_post_meta($order_id, 'shipping_barangays', sanitize_text_field($_POST['custom_shipping_barangays']));
    }
    if ($_POST['custom_shipping_state']) {
        update_post_meta($order_id, 'shipping_state', sanitize_text_field($_POST['custom_shipping_state']));
    }
}

function locad_af_validate_fields()
{
    global $locad_af_supported_countries;
    $selected_billing_country = sanitize_text_field($_POST['billing_country']);
    $selected_shipping_country = sanitize_text_field($_POST['shipping_country']);
    $ship_to_different_address = sanitize_text_field($_POST['ship_to_different_address']);
    $custom_billing_municipalities = sanitize_text_field($_POST['custom_billing_municipalities']);
    $custom_billing_barangays = sanitize_text_field($_POST['custom_billing_barangays']);
    $custom_shipping_municipalities = sanitize_text_field($_POST['custom_shipping_municipalities']);
    $custom_shipping_barangays = sanitize_text_field($_POST['custom_shipping_barangays']);

    if (locad_af_should_validate('billing') && $selected_billing_country && in_array($selected_billing_country, $locad_af_supported_countries)) {
        if (!$custom_billing_municipalities) {
            wc_add_notice('<strong>Billing Municipality</strong> ' . __('is a required field', 'wc-field-editor') . ' ', 'error');
        } elseif (!$custom_billing_barangays) {
            wc_add_notice('<strong>Billing Barangays</strong> ' . __('is a required field', 'wc-field-editor') . ' ', 'error');
        }
    }

    if ($ship_to_different_address) {
        if (locad_af_should_validate('billing') && $selected_shipping_country && in_array($selected_shipping_country, $locad_af_supported_countries)) {
            if (!$custom_shipping_municipalities) {
                wc_add_notice('<strong>Shipping Municipality</strong> ' . __('is a required field', 'wc-field-editor') . ' ', 'error');
            } elseif (!$custom_shipping_barangays) {
                wc_add_notice('<strong>Shipping Barangays</strong> ' . __('is a required field', 'wc-field-editor') . ' ', 'error');
            }
        }
    }
}

function locad_af_custom_billing_fields($checkout)
{
    woocommerce_form_field('custom_billing_municipalities', [
        'type'  => 'select',
        'clear' => true,
        'class' => array('custom_billing_municipalities-select form-row form-row-wide address-field validate-required select2-container--default'),
        'input_class' => array('select2-selection--single select2-selection__rendered'),
        'options' => array('' => ''),
        'required' => true,
        'placeholder' => 'Municipality',
        'label' => __('Municipality'),
    ], $checkout->get_value('custom_billing_municipalities'));

    woocommerce_form_field('custom_billing_barangays', [
        'type'  => 'select',
        'clear' => true,
        'class' => array('custom_billing_barangays-select form-row form-row-wide address-field validate-required select2-container--default'),
        'input_class' => array('select2-selection--single select2-selection__rendered'),
        'options' => array('' => ''),
        'required' => true,
        'placeholder' => 'Barangays',
        'label' => __('Barangays'),
    ], $checkout->get_value('custom_billing_barangays'));
}

function locad_af_custom_shipping_fields($checkout)
{
    woocommerce_form_field('custom_shipping_municipalities', [
        'type'  => 'select',
        'clear' => true,
        'class' => array('custom_shipping_municipalities-select form-row form-row-wide address-field validate-required select2-container--default'),
        'input_class' => array('select2-selection--single select2-selection__rendered'),
        'options' => array('' => ''),
        'required' => true,
        'placeholder' => 'Municipality',
        'label' => __('Municipality'),
    ], $checkout->get_value('custom_shipping_municipalities'));

    woocommerce_form_field('custom_shipping_barangays', [
        'type'  => 'select',
        'clear' => true,
        'class' => array('custom_shipping_barangays-select form-row form-row-wide address-field validate-required select2-container--default'),
        'input_class' => array('select2-selection--single select2-selection__rendered'),
        'options' => array('' => ''),
        'required' => true,
        'placeholder' => 'Barangays',
        'label' => __('Barangays'),
    ], $checkout->get_value('custom_shipping_barangays'));
}

function locad_af_load_dropdowns_js()
{
    if (!(is_checkout() && !is_wc_endpoint_url())) return;

    wp_enqueue_script('script_dropdowns', plugins_url('/js/dropdowns.js', __FILE__), array('jquery'));
    wp_localize_script('script_dropdowns', 'SITE_URL', get_site_url());
}

function locad_af_get_municipalities_json($type, $country, $state)
{
    set_error_handler(function ($err_severity, $err_msg, $err_file, $err_line) {
        throw new ErrorException($err_msg, 0, $err_severity, $err_file, $err_line);
    }, E_WARNING);
    locad_af_set_should_validate($type, false);
    try {
        $json = file_get_contents('https://locad-public.s3.amazonaws.com/woocommerce/address/' . $country . '/prov_' . $state . '.json');
        if ($json) {
            locad_af_set_should_validate($type, true);
            return $json;
        } else {
            return null;
        }
    } catch (Exception $e) {
        return null;
    }
}

function locad_af_install_db()
{
    global $wpdb;
    global $locad_af_table_name;

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $locad_af_table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		type text NOT NULL,
		should_validate boolean DEFAULT false NOT NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function locad_af_fill_db()
{
    global $wpdb;
    global $locad_af_table_name;

    $billing_row = $wpdb->get_row("SELECT * FROM " . $locad_af_table_name . " WHERE type = 'billing'");
    $shipping_row = $wpdb->get_row("SELECT * FROM " . $locad_af_table_name . " WHERE type = 'shipping'");

    if ($wpdb->get_var("SHOW TABLES LIKE '$locad_af_table_name'") != $locad_af_table_name) {
        //table does not exist
        locad_af_install_db();
    }
    if (!$billing_row) {
        $wpdb->insert(
            $locad_af_table_name,
            array(
                'type' => 'billing',
                'should_validate' => false,
            )
        );
    }

    if (!$shipping_row) {
        $wpdb->insert(
            $locad_af_table_name,
            array(
                'type' => 'shipping',
                'should_validate' => false,
            )
        );
    }
}

function locad_af_set_should_validate($type, $value)
{
    global $wpdb;
    global $locad_af_table_name;


    $wpdb->update(
        $locad_af_table_name,
        array('should_validate' => $value),
        array('type' => $type),
    );
}

function locad_af_should_validate($type)
{
    global $wpdb;
    global $locad_af_table_name;
    $result = $wpdb->get_row("SELECT * FROM " . $locad_af_table_name . " WHERE type = '" . $type . "'");
    return $result->should_validate;
}
