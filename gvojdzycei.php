<?php/*Plugin Name: Currency converterDescription:Version: 1.1Author: Andrew MelnikVersion 1 author: Tarik A. */if (!defined('ABSPATH')) {	exit; // Exit if accessed directly}function gvojdzycei_country_code(){	if (class_exists('WC_Geolocation')) {		$user_location  = WC_Geolocation::geolocate_ip();		$country_code        = ! empty( $user_location['country'] ) ? $user_location['country'] : 'US';		return $country_code;	}	return 'US';}function gvojdzycei_register_activation_hook() {		$country_currency_mapping=array();	if (($handle = fopen(plugin_dir_path( __FILE__ ) .'country-code-to-currency-code-mapping.csv', 'r')) !== FALSE) {	    while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {	        $country_currency_mapping[$row[0]]=$row[1];	    }	    fclose($handle);	}      update_option( 'gvojdzycei_country_currency_mapping',$country_currency_mapping);   update_option( 'gvojdzycei_currencies',array('USD','EUR'));       if (! wp_next_scheduled ( 'gvojdzycei_daily_event' )) {		wp_schedule_event( '1262304000', 'daily', 'gvojdzycei_daily_event');      }	}register_activation_hook( __FILE__, 'gvojdzycei_register_activation_hook' );function gvojdzycei_register_deactivation_hook() {	wp_clear_scheduled_hook('gvojdzycei_daily_event');}register_deactivation_hook(__FILE__, 'gvojdzycei_register_deactivation_hook');function gvojdzycei_daily_event_callback() {	gvojdzycei_yahoo_finance();}add_action('gvojdzycei_daily_event', 'gvojdzycei_daily_event_callback');function gvojdzycei_get_currency_by_country($code){	$country_currency_mapping=get_option( 'gvojdzycei_country_currency_mapping');	if(isset($country_currency_mapping[$code])){		return $country_currency_mapping[$code];	}	return 'USD';}function gvojdzycei_wp_enqueue_scripts() {	wp_enqueue_script( 'gvojdzycei-ddslick', plugin_dir_url( __FILE__ ).'jquery.ddslick.min.js',array('jquery'));	wp_enqueue_script( 'gvojdzycei-js-query', plugin_dir_url( __FILE__ ).'jquery.query-object.js',array('jquery'));		wp_enqueue_style( 'gvojdzycei-flags', plugin_dir_url( __FILE__ ).'flags.css');}add_action( 'wp_enqueue_scripts', 'gvojdzycei_wp_enqueue_scripts' );function gvojdzycei_template_redirect(){		$currency=null;		if(isset($_GET['currency'])){		$currency=sanitize_text_field($_GET['currency']);		setcookie('gvojdzycei_currency',$currency , time() + 60 * 60 * 24 * 30, '/');	}		if(empty($currency)){		if(isset($_COOKIE['gvojdzycei_currency'])){			$currency=sanitize_text_field($_COOKIE['gvojdzycei_currency']);		}	}		if($currency){		if (session_status() == PHP_SESSION_NONE) {			session_start();		}		$_SESSION['gvojdzycei_currency']=$currency;	}		WC_Form_Handler::update_cart_action();}add_action('template_redirect','gvojdzycei_template_redirect');function gvojdzycei_render(){	if(is_checkout()){		$checkout_currency=get_option('gvojdzycei_checkout_currency','ANY');		if($checkout_currency!='ANY'){			return false;		}	}		$currencies=get_option( 'gvojdzycei_currencies',array('USD','EUR'));	$current_currency = get_woocommerce_currency();		$flags=get_option( 'gvojdzycei_currency_flags','no');	$flags_url = plugin_dir_url( __FILE__ ).'flags/';	?>		<div class="gvojdzycei">		<select class="gvojdzycei-html-select" name="gvojdzycei_currencies"  >			<?php			foreach ( $currencies as $currency ) {								echo '<option '.selected($current_currency,$currency);				if($flags!='no'){					//echo ' data-imagesrc="'.$flags_url.strtolower($currency).'.png" '; 					//echo 'description="flag-'.$currency.'"'; 				}				echo ' value="' . $currency. '" ' . '>' . $currency . ' '.get_woocommerce_currency_symbol($currency).'</option>';			}			?>		</select>	</div>		<?php}add_action( 'wp_head', 'gvojdzycei_render' );function gvojdzycei_wp_footer(){	?> 	<script>    	jQuery(function(){			var gvojdzycei_counter=0;	    	jQuery('.gvojdzycei-html-select').ddslick({			    onSelected: function(data){					if(gvojdzycei_counter>0){						console.log(data.selectedData.value);						window.location.search = jQuery.query.set("currency", data.selectedData.value);			    	}			    	gvojdzycei_counter++;			    }   			});			jQuery( '.gvojdzycei .dd-option-value' ).each(function(){				var flags_value = jQuery(this).val();				if(jQuery(this).parent().hasClass('dd-option-selected')){					jQuery(this).closest('.dd-container').find('.dd-selected').addClass('flag-' + flags_value); 				}				jQuery(this).closest('.dd-option').addClass('flag-' + flags_value);			});			});    </script>	<?php}add_action( 'wp_footer', 'gvojdzycei_wp_footer' );function gvojdzycei_woocommerce_currency($default_currency){		if(is_admin()){		return $default_currency;	}		if(is_checkout()){		$checkout_currency=get_option('gvojdzycei_checkout_currency','ANY');		if($checkout_currency!='ANY'){			return $checkout_currency;		}	}		$currencies=get_option( 'gvojdzycei_currencies');	$currency = (isset($_SESSION['gvojdzycei_currency'])) ? $_SESSION['gvojdzycei_currency'] : null;	if(empty($currency)){		if(isset($_COOKIE['gvojdzycei_currency'])){			$currency=sanitize_text_field($_COOKIE['gvojdzycei_currency']);		}	}		if(empty($currency)){		$user_id = get_current_user_id();		if($user_id){			$currency = get_user_meta( $user_id, 'gvojdzycei_currency', true );		}	}		if(empty($currency) ){		$code=gvojdzycei_country_code();		if($code){				$currency=gvojdzycei_get_currency_by_country($code);		}	}		if(in_array($currency,$currencies)){		return $currency;	}		return $default_currency;}add_filter('woocommerce_currency','gvojdzycei_woocommerce_currency');function gvojdzycei_yahoo_finance(){	$admin_email = get_option( 'admin_email' );	//wp_mail( $admin_email, 'Currency Converter', 'Update started at : '.date('Y-m-d H:i:s'));		$xml = simplexml_load_file("https://finance.yahoo.com/webservice/v1/symbols/allcurrencies/quote");	$resources=array();	foreach($xml->resources->resource  as $resource) 	{		$name=(string)$resource->field[0];		if (strpos($name, 'USD/') !== false) {			$name=str_replace('USD/','',$name);			$resources[$name]=(string)$resource->field[1];		}	}	update_option('gvojdzycei_resources',$resources);	update_option('gvojdzycei_resources_time',date('Y-m-d H:i:s'));		//wp_mail( $admin_email, 'Currency Converter', 'Update finished at : '.date('Y-m-d H:i:s'));	return $resources;}function gvojdzycei_twaicejoop_prices($price, $product){	/* 	if(is_admin() && !$custom_currency){		return $price  . ' ' . $currency; 	} */		$price=floatval($price);		$currency = get_woocommerce_currency();		if($currency!='USD'){				$resources=get_option('gvojdzycei_resources');		if(!$resources){			$resources=gvojdzycei_yahoo_finance();		}				$price=$price*$resources[$currency]; 				$rate=get_option('gvojdzycei_currency_converter_rate'); 		if($rate){			$price = $price + ($price*$rate/100);		}	}	return apply_filters( 'gvojdzycei_price_format', $price ) . ' ' . $currency;	}add_filter('twaicejoop_prices', 'gvojdzycei_twaicejoop_prices', 10, 2); function gvojdzycei_woocommerce_converted_prices($price, $product){	wc_delete_product_transients( $product->id );		return gvojdzycei_twaicejoop_prices($price, $product );	}function gvojdzycei_woocommerce_converted_html($price, $product){	$currency = get_woocommerce_currency();	if($user_currency!='USD'){			return $price . ' ' . $currency; 	} else {		return $price;	}	}function gvojdzycei_woocommerce_converted_price($price, $args){	if(is_checkout()){		$currency = get_woocommerce_currency();			$price = substr($price, 0, -7);		return $price . ' ' . $currency . '</span>'; 	} else {		return $price;	}	}function gvojdzycei_woocommerce_converted_var_prices($price, $product){		wc_delete_product_transients( $product->id );	return gvojdzycei_twaicejoop_prices($price, $product );	}// Filter for regular price in single product, cart, shop. Works together with next filteradd_filter('woocommerce_get_regular_price','gvojdzycei_woocommerce_converted_prices',10,2);// Filter for sale price in single productadd_filter('woocommerce_get_sale_price','gvojdzycei_woocommerce_converted_prices',10,2);// Filter for current product price and current variation price in single product, shop, cart, mini-cartadd_filter('woocommerce_get_price','gvojdzycei_woocommerce_converted_prices',10,2);  // Add currencies for pricesadd_filter('woocommerce_get_regular_price_html','gvojdzycei_woocommerce_converted_html',10,2);add_filter('woocommerce_get_sale_price_html','gvojdzycei_woocommerce_converted_html',10,2);add_filter('woocommerce_get_price_html','gvojdzycei_woocommerce_converted_html',10,2);  // Show USD on checkout pricesadd_filter('wc_price','gvojdzycei_woocommerce_converted_price',10,2);  // Filters to clear cached price for variable pricesadd_filter('woocommerce_variation_prices_price','gvojdzycei_woocommerce_converted_var_prices',10,2); add_filter('woocommerce_variation_prices_regular_price','gvojdzycei_woocommerce_converted_var_prices',10,2);add_filter('woocommerce_variation_prices_sale_price','gvojdzycei_woocommerce_converted_var_prices',10,2); // Filters to edit cart and checkout pricesadd_filter('woocommerce_cart_total', 'gvojdzycei_cart_total', 10, 2);add_filter('woocommerce_cart_item_price', 'gvojdzycei_cart_item_price', 10, 2);add_filter('woocommerce_cart_item_subtotal', 'gvojdzycei_cart_item_price', 10, 2);add_filter('woocommerce_cart_subtotal', 'gvojdzycei_cart_item_subtotal', 10, 3);add_filter('woocommerce_cart_shipping_method_full_label', 'gvojdzycei_shipping_method_full_label', 10, 2);// Filters item shipping price on checkoutadd_filter( 'iqxzvqhmye_shipping', 'gvojdzycei_woocommerce_get_item_data', 10, 3); function gvojdzycei_cart_item_price($product_price, $cart_item){	$user_currency = gvojdzycei_get_user_currency();	if(is_checkout()){				$cart_price = $cart_item['line_total'] / $cart_item['quantity'];				if($user_currency!='USD'){						$resources=get_option('gvojdzycei_resources');			if(!$resources){				$resources=gvojdzycei_yahoo_finance();			}			$cart_price=$cart_price*$resources[$user_currency];						$rate=get_option('gvojdzycei_currency_converter_rate');			if($rate){				$cart_price = $cart_price + ($cart_price*$rate/100);			}			$cart_price = apply_filters( 'gvojdzycei_price_format', $cart_price ) * $cart_item['quantity'];			  		} else {			return $product_price;		}		if ( $user_currency )		{			$product_price.= '<div class="woocs_cart_item_price">(Approx. ' . get_woocommerce_currency_symbol($user_currency) . number_format($cart_price, 2) . ' ' . $user_currency . ')</div>';		}		return $product_price;	} else {		return $product_price . ' ' . $user_currency;	}}function gvojdzycei_cart_item_subtotal($cart_subtotal, $compound, $woo){	$user_currency = gvojdzycei_get_user_currency();		global $woocommerce;				$resources=get_option('gvojdzycei_resources');		if(!$resources){			$resources=gvojdzycei_yahoo_finance();		}				$subtotal = $current = 0;				$items = $woocommerce->cart->get_cart(); 		foreach($items as $item => $values) { 					$id = $values["product_id"];			$var_id = $values["variation_id"];			$total_price = $values["line_total"];			$quantity = $values["quantity"];			$price = $total_price / $quantity;					if($user_currency!='USD'){				$price=$price*$resources[$user_currency];				$rate=get_option('gvojdzycei_currency_converter_rate'); 				if($rate){					$price = $price + ($price*$rate/100);				}				$price=apply_filters( 'gvojdzycei_price_format', $price );			}									$subtotal += $price * $quantity;					}		if ( $user_currency!='USD' && is_checkout() ) 		{			return $cart_subtotal.= '<div class="woocs_cart_item_price">(Approx. ' . get_woocommerce_currency_symbol($user_currency) . number_format($subtotal, 2) . ' ' . $user_currency . ')</div>';		}		if ( !is_checkout() ) {			return $cart_subtotal . ' ' . $user_currency;		}				return $cart_subtotal;}function gvojdzycei_cart_total($product_price){	$user_currency = gvojdzycei_get_user_currency();	if(is_checkout()){		global $woocommerce;				$resources=get_option('gvojdzycei_resources');		if(!$resources){			$resources=gvojdzycei_yahoo_finance();		}				$total = $current = 0;				$items = $woocommerce->cart->get_cart(); 		foreach($items as $item => $values) { 					$id = $values["product_id"];			$var_id = $values["variation_id"];			$total_price = $values["line_total"];			$quantity = $values["quantity"];			$price = $total_price / $quantity;			$shipping = iqxzvqhmye_prices($id, false, $var_id, true);			$shipping_price = $shipping["delivery_cost"];					if($user_currency!='USD'){				$price=$price*$resources[$user_currency];				$shipping_price=$shipping_price*$resources[$user_currency];				$rate=get_option('gvojdzycei_currency_converter_rate'); 				if($rate){					$price = $price + ($price*$rate/100);					$shipping_price = $shipping_price + ($shipping_price*$rate/100);				}				$price=apply_filters( 'gvojdzycei_price_format', $price );				$shipping_price=apply_filters( 'gvojdzycei_price_format', $shipping_price );			}									$total += $price * $quantity + $shipping_price * $quantity;					}				if ( $user_currency!='USD' )		{			$product_price.= '<div class="woocs_cart_item_price">(Approx ' . get_woocommerce_currency_symbol($user_currency) . number_format($total, 2) . ' ' . $user_currency . ')</div>';		}		return $product_price;	} else {		return $product_price . ' ' . $user_currency;	}}function gvojdzycei_shipping_method_full_label($label, $method){		$user_currency = gvojdzycei_get_user_currency();	if ( $user_currency!='USD' ){		global $woocommerce;				$resources=get_option('gvojdzycei_resources');		if(!$resources){			$resources=gvojdzycei_yahoo_finance();		}				$total = $current = 0;				$items = $woocommerce->cart->get_cart(); 		foreach($items as $item => $values) { 					$id = $values["product_id"];			$var_id = $values["variation_id"];			$total_price = iqxzvqhmye_prices($id, false, $var_id, true);			$quantity = $values["quantity"];						$price = $total_price["delivery_cost"]; 			//return var_dump($price); 					if($user_currency!='USD'){				$price=$price*$resources[$user_currency];				$rate=get_option('gvojdzycei_currency_converter_rate'); 				if($rate){					$price = $price + ($price*$rate/100);				}				$price=apply_filters( 'gvojdzycei_price_format', $price );			}									$total += $price * $quantity;					}			$label.= '<div class="woocs_cart_item_price">(Approx. ' . get_woocommerce_currency_symbol($user_currency) . number_format($total, 2) . ' ' . $user_currency . ')</div>'; 	}		return $label;}function gvojdzycei_woocommerce_get_item_data($item_data, $rate_cost, $quantity) {	$user_currency = gvojdzycei_get_user_currency(); 	if(is_checkout()){		if($rate_cost > 0 && $user_currency!='USD') {						$resources=get_option('gvojdzycei_resources');			if(!$resources){				$resources=gvojdzycei_yahoo_finance();			}			$price=$rate_cost*$resources[$user_currency]/$quantity;						$rate=get_option('gvojdzycei_currency_converter_rate');			if($rate){				$price = $price + ($price*$rate/100);			}			$price = apply_filters( 'gvojdzycei_price_format', $price ); 		} else {			return $item_data;		}		$item_data[] = array(				'key'=>'Approx.',				'value'=>  get_woocommerce_currency_symbol($user_currency) . number_format( $price * $quantity, 2) . ' ' . $user_currency		);	}	return $item_data;}function gvojdzycei_extract_unit($string, $start, $end) {	$pos = stripos($string, $start); 	$str = substr($string, $pos); 	$str_two = substr($str, strlen($start)); 	$second_pos = stripos($str_two, $end); 	$str_three = substr($str_two, 0, $second_pos); 	$unit = trim($str_three);		return $unit;}function gvojdzycei_get_user_currency(){			$user_currency = (isset($_SESSION['gvojdzycei_currency'])) ? $_SESSION['gvojdzycei_currency'] : null;		if(empty($user_currency)){			if(isset($_COOKIE['gvojdzycei_currency'])){				$user_currency=sanitize_text_field($_COOKIE['gvojdzycei_currency']);			}		}				if(empty($user_currency)){			$user_id = get_current_user_id();			if($user_id){				$user_currency = get_user_meta( $user_id, 'gvojdzycei_currency', true ); 			}		}				if(empty($user_currency) ){			$code=gvojdzycei_country_code();			if($code){					$user_currency=gvojdzycei_get_currency_by_country($code);			}		}				return $user_currency;}function gvojdzycei_set_price_format( $price ) {	$format=get_option('gvojdzycei_price_format'); 		if ($price == 0) {		return $price;	}		switch ($format) {		case 0:			return $price;		case 1:			return ceil( $price );		case 2:			return ceil( $price ) - 0.01;		case 3:			return ceil( $price ) - 0.05;	}	}add_filter('gvojdzycei_price_format', 'gvojdzycei_set_price_format', 10); function gvojdzycei_woocommerce_edit_account_form() {	$user_id = get_current_user_id();	if ( !$user_id )	return;	$currencies=get_option( 'gvojdzycei_currencies');	$selected_currency = get_user_meta( $user_id, 'gvojdzycei_currency', true );	?>	<fieldset>		<legend><?php _e( 'Currency', 'woocommerce' ); ?></legend>		<p class="form-row form-row-wide">			<select name="gvojdzycei_currency_input" style="width:100%;" >				<option value=""><?php _e( 'Choose a currency&hellip;', 'woocommerce' ); ?></option>				<?php				foreach ( $currencies as $currency ) {					echo '<option '.selected($selected_currency,$currency).' value="' . $currency. '" ' . '>' . esc_html( $currency . ' (' . get_woocommerce_currency_symbol($currency) . ')' ) . '</option>';				}				?>			</select>		</p>	</fieldset>	<?php} add_action( 'woocommerce_edit_account_form', 'gvojdzycei_woocommerce_edit_account_form' );function gvojdzycei_woocommerce_save_account_details( $user_id ) {	if(isset($_POST[ 'gvojdzycei_currency_input' ])){		update_user_meta( $user_id, 'gvojdzycei_currency', htmlentities( $_POST[ 'gvojdzycei_currency_input' ] ) );	}}add_action( 'woocommerce_save_account_details', 'gvojdzycei_woocommerce_save_account_details' );add_filter( 'woocommerce_general_settings', 'gvojdzycei_woocommerce_general_settings' );function gvojdzycei_woocommerce_general_settings( $settings ) {  $updated_settings = array();  foreach ( $settings as $section ) {    if ( isset( $section['id'] ) && 'general_options' == $section['id'] && isset( $section['type'] ) && 'sectionend' == $section['type'] ) {			  $currencies=get_woocommerce_currencies();      $updated_settings[] = array(        'title'     => 'Supported Currencies',        'id'       => 'gvojdzycei_currencies',        'type'     => 'multiselect',        'css'      => 'min-width:350px;min-height: 250px;',	    'options' => get_woocommerce_currencies(),      );	  	  $time = get_option('gvojdzycei_resources_time');	  $rates = get_option('gvojdzycei_resources'); 	  ksort($rates);	  $formatted_rates = array();	  $formatted_rates[] = 'Last update time: ' . $time;	  foreach ($rates as $curr => $rate){		$formatted_rates[] = $curr . ' - ' . $rate;	  }	  	  $updated_settings[] = array(        'title'     => 'Currencies Rates',        'id'       => 'gvojdzycei_currencies_rates',        'type'     => 'multiselect',        'css'      => 'min-width:350px;min-height: 250px;',	    'options' => $formatted_rates,      );	  	  $price_formats = array( 'Cents', 'Full', 'Point', 'Half-point'); 		  $updated_settings[] = array(        'title'     => 'Price format',        'id'       => 'gvojdzycei_price_format',        'type'     => 'select',	    'options' => $price_formats,      );      $updated_settings[] = array(        'title'     => 'Currency Converter Rate',        'desc'     => 'This rate % will be added to converted prices.',        'id'       => 'gvojdzycei_currency_converter_rate',        'type'     => 'number',	    'default' => 0,      );	        $updated_settings[] = array(        'title'     => 'Currency Flags',        'desc'     => 'Flags can be found here : '.plugin_dir_path( __FILE__ ).'flags/',        'id'       => 'gvojdzycei_currency_flags',        'type'     => 'checkbox',	    'default' => 'no',      );	  	  $currencies['ANY']='All Currencies';      $updated_settings[] = array(        'title'     => 'Checkout Currency',        'id'       => 'gvojdzycei_checkout_currency',		'type'     => 'select',	    'options' => $currencies,	    'default' => 'ANY',      );    }    $updated_settings[] = $section;  }  return $updated_settings;}add_action( 'woocommerce_currencies', 'gvojdzycei_new_currency_order');function gvojdzycei_new_currency_order(){	return array(		'USD' => __( 'US Dollars', 'woocommerce' ),		'GBP' => __( 'Pounds Sterling', 'woocommerce' ), 		'EUR' => __( 'Euros', 'woocommerce' ),			'AED' => __( 'United Arab Emirates Dirham', 'woocommerce' ), 		'ARS' => __( 'Argentine Peso', 'woocommerce' ),		'AUD' => __( 'Australian Dollars', 'woocommerce' ),		'BDT' => __( 'Bangladeshi Taka', 'woocommerce' ), 		'BGN' => __( 'Bulgarian Lev', 'woocommerce' ),		'BRL' => __( 'Brazilian Real', 'woocommerce' ),		'CAD' => __( 'Canadian Dollars', 'woocommerce' ),		'CHF' => __( 'Swiss Franc', 'woocommerce' ),		'CLP' => __( 'Chilean Peso', 'woocommerce' ),		'CNY' => __( 'Chinese Yuan', 'woocommerce' ),		'COP' => __( 'Colombian Peso', 'woocommerce' ),		'CZK' => __( 'Czech Koruna', 'woocommerce' ),		'DKK' => __( 'Danish Krone', 'woocommerce' ),		'DOP' => __( 'Dominican Peso', 'woocommerce' ),		'EGP' => __( 'Egyptian Pound', 'woocommerce' ),		'GBP' => __( 'Pounds Sterling', 'woocommerce' ),		'EUR' => __( 'Euros', 'woocommerce' ),			'HKD' => __( 'Hong Kong Dollar', 'woocommerce' ),		'HRK' => __( 'Croatia kuna', 'woocommerce' ),		'HUF' => __( 'Hungarian Forint', 'woocommerce' ),		'IDR' => __( 'Indonesia Rupiah', 'woocommerce' ),		'ILS' => __( 'Israeli Shekel', 'woocommerce' ),		'INR' => __( 'Indian Rupee', 'woocommerce' ),		'ISK' => __( 'Icelandic krona', 'woocommerce' ),		'JPY' => __( 'Japanese Yen', 'woocommerce' ),		'KES' => __( 'Kenyan shilling', 'woocommerce' ),		'KRW' => __( 'South Korean Won', 'woocommerce' ),		'LAK' => __( 'Lao Kip', 'woocommerce' ),		'MXN' => __( 'Mexican Peso', 'woocommerce' ),		'MYR' => __( 'Malaysian Ringgits', 'woocommerce' ),		'NGN' => __( 'Nigerian Naira', 'woocommerce' ),		'NOK' => __( 'Norwegian Krone', 'woocommerce' ),		'NPR' => __( 'Nepali Rupee', 'woocommerce' ),		'NZD' => __( 'New Zealand Dollar', 'woocommerce' ),		'PHP' => __( 'Philippine Pesos', 'woocommerce' ),		'PKR' => __( 'Pakistani Rupee', 'woocommerce' ),		'PLN' => __( 'Polish Zloty', 'woocommerce' ),		'PYG' => __( 'Paraguayan Guaraní', 'woocommerce' ),		'RON' => __( 'Romanian Leu', 'woocommerce' ),		'RUB' => __( 'Russian Ruble', 'woocommerce' ),		'SAR' => __( 'Saudi Riyal', 'woocommerce' ),		'SEK' => __( 'Swedish Krona', 'woocommerce' ),		'SGD' => __( 'Singapore Dollar', 'woocommerce' ),		'THB' => __( 'Thai Baht', 'woocommerce' ),		'TRY' => __( 'Turkish Lira', 'woocommerce' ),		'TWD' => __( 'Taiwan New Dollars', 'woocommerce' ),		'UAH' => __( 'Ukrainian Hryvnia', 'woocommerce' ),		'USD' => __( 'US Dollars', 'woocommerce' ), 		'VND' => __( 'Vietnamese Dong', 'woocommerce' ),		'ZAR' => __( 'South African rand', 'woocommerce' ),	);}