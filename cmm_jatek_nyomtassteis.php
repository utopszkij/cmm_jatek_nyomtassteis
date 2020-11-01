<?php
/**
* Plugin Name: cmm_jatek_nyomtassteis
* Plugin URI: http://www.github.com/utopszkij/
* Description: Kiegészítés woocommerce -hez, szabd/rab kijelzés, köszönet nagy vásárlásért, település felszabaditás jelzése. 
* Version: 1.00.00
* Author: Fogler Tibor
* Author URI: http://www.github.com/utopszkij
*
* szükséges további plugin: Advanced customer field
* szükséges ACF mezők a product -ban: 
*   package number
*   valid_start  date Ymd formátumban
*   valid_end    date Ymd formátumban 
* kiemelt terület" oldalakt kell kialakitani, ezek "title" adata egy település név. Kötelezően
* tartalmazza a [cmm_init....] shortcode hívást. Ezeken legyen lehetőség 
* a termékeket "kosárba tenni". A kosárba helyezés után a kosár lista oldal jelenjen meg.
*
* A kosárban lévő összes tételt a program a felhasználó által utoljára használt "kiemelt terület" -hez 
* tartozónak tekinti.
*
* A lezárt megrendeléseket a program szállítási címben megadott településhez tartozónak tekinti, ide a program
* alapértelmezésként felajánlja a felhasználó által  utoljára használt "kiemelt területet"-et.
*/


/* cart kezelés

// $cart conditionals (if)
WC()->cart->is_empty()
WC()->cart->needs_payment()
WC()->cart->show_shipping()
WC()->cart->needs_shipping()
WC()->cart->needs_shipping_address()
WC()->cart->display_prices_including_tax()
 
// Get $cart totals
WC()->cart->get_cart_contents_count();
WC()->cart->get_cart_subtotal();
WC()->cart->subtotal_ex_tax;
WC()->cart->subtotal;
WC()->cart->get_displayed_subtotal();
WC()->cart->get_taxes_total();
WC()->cart->get_shipping_total();
WC()->cart->get_coupons();
WC()->cart->get_coupon_discount_amount( 'coupon_code' );
WC()->cart->get_fees();
WC()->cart->get_discount_total();
WC()->cart->get_total();
WC()->cart->total;
WC()->cart->get_tax_totals();
WC()->cart->get_cart_contents_tax();
WC()->cart->get_fee_tax();
WC()->cart->get_discount_tax();
WC()->cart->get_shipping_total();
WC()->cart->get_shipping_taxes();
  
// Loop over $cart items
foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
   $product = $cart_item['data'];
   $product_id = $cart_item['product_id'];
   $quantity = $cart_item['quantity'];
   $price = WC()->cart->get_product_price( $product );
   $subtotal = WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] );
   $link = $product->get_permalink( $cart_item );
   // Anything related to $product, check $product tutorial
   $meta = wc_get_formatted_cart_item_data( $cart_item );
}
 
*/

/**
 * init plugin, load js és shortcode definiciók
 */ 
add_action('init','cmm_jatek_nyomtassteis_init');
function cmm_jatek_nyomtassteis_init(){
	 session_start();
    add_action( 'the_content', 'cmm_jatek_nyomtassteis_js');
    add_shortcode('cmm_init', 'cmm_jatek_nyomtassteis_sc_init');
    add_shortcode('cmm_free', 'cmm_jatek_nyomtassteis_sc_free');
    add_shortcode('cmm_prison', 'cmm_jatek_nyomtassteis_sc_prison');
    add_shortcode('cmm_thanks', 'cmm_jatek_nyomtassteis_sc_thanks');
    add_shortcode('cmm_victory', 'cmm_jatek_nyomtassteis_sc_victory');
    
	/**
	 * ez a plugin admin oldali fő programja,
	 * beépítve az admin oldal Beállítások menü alá
	 */
	function cmm_jatek_nyomtassteis_admin() {
		include __DIR__.'/readme.html';
	}
	add_action('admin_menu', 'cmm_jatek_nyomtassteis_plugin_create_menu');
	function cmm_jatek_nyomtassteis_plugin_create_menu() {
	    add_options_page("cmm_jatek_nyomtassteis WordPress bővítmény", "cmm_jatek_nyomtassteis bővitmény", 1,
	        "cmm_jatek_nyomtassteis", "cmm_jatek_nyomtassteis_admin");
	}    
}

/**
* js betöltés. Funkciója: a sessionban lévő 'place' adat alapján a rendelés szállítási cím kitöltése.
*/
function cmm_jatek_nyomtassteis_js($content) {
	 	if (isset($_SESSION['place'])) {
			echo '
			<script type="text/javascript">
			jQuery(function() {
				if (jQuery("#shipping_city")) {
					jQuery("#shipping_city").val("'.$_SESSION['place'].'");
					jQuery("#shipping_address_1").val("Futrinka u. 1");
					jQuery("#shipping_postcode").val("0000");
					jQuery("#shipping_last_name").val("Mézga");
					jQuery("#shipping_first_name").val("család");
				}		
			})		
			</script>		
			';
		}	 
		return $content;
}

/**
* currency string eltávolítása érték stringből
*/
function cmm_stripCurrency($cartTotal) {	
	$cartTotal = strip_tags($cartTotal);
	$cartTotal = str_replace(' ','',$cartTotal);
	$cartTotal = str_replace('&nbsp;','',$cartTotal);
	$cartTotal = str_replace('&#70;','',$cartTotal);
	$cartTotal = str_replace('&#116;','',$cartTotal);
	$cartTotal = str_replace('Ft','',$cartTotal);
	$cartTotal = str_replace('$','',$cartTotal);
	$cartTotal = str_replace('€','',$cartTotal);
	$cartTotal = trim($cartTotal);
	$cartTotal = 0 + str_replace(',','.',$cartTotal);
	return $cartTotal;
}	
	


// ================ shotcode -ok a kiemelt területek oldalra ==================================

/**
* jatek_nyomtassteis short code rendszer init KÖTELEZŐ HASZNÁLNI, EZ LEGYEN AZ ELSŐ!
* @param array ["count" => ####, "add" => ###, "date" => "yyyy.mm.dd"]  
* @return string html kód
* - $post->post_title a place adat
* - free számításda és tárolása sessionba
* - sessionba: place, count, add, date, freee 
*/
function cmm_jatek_nyomtassteis_sc_init($atts):string {
	if (is_admin()) {
		return '';	
	}
	include_once __DIR__.'/model.coverage.php';	
	global $post;
	$model = new CoverageModel(false);
	if (!isset($atts['date'])) {
			$atts['date'] = date('Y.m.d');
	}
	if (!isset($atts['count'])) {
			$atts['count'] = 0;
	}
	if (!isset($atts['add'])) {
			$atts['add'] = 0;
	}
	$_SESSION['place'] = $post->post_title;
	$_SESSION['date'] = $atts['date'];
	$_SESSION['count'] = $atts['count'];
	$_SESSION['add'] = $atts['add'];

	// lezárt rendelések feldolgozása
   $place = $_SESSION['place'];
   $validDate = str_replace('.','',$_SESSION['date']);	
	$res = $model->realisedTotal('quantity',
		["city" => $place, "validDate" => $validDate]);
	if (count($res) > 0) {
		$free = 0 + $res[0]->sumQuantity + $_SESSION['add'];
	} else {
		$free = 	$_SESSION['add'];
	}	

	// cart (kosár) feldolgozása
	foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
	   // $product = $cart_item['data'];
	   $product_id = $cart_item['product_id'];
	   $quantity = $cart_item['quantity'];
		$productInfo = $model->read($product_id); 
		if ($productInfo) {
			if (($productInfo->valid_start <= $validDate) &
			    ($productInfo->valid_end >= $validDate)) {
			    $free = $free + $quantity * $productInfo->package;	
	      } 
      }
	}		

   // határértékek kezelése
	if ($free < 0) {
			$free = 0;	
	}
	if ($free > $_SESSION['count']) {
			$free = $_SESSION['count'];	
	}
	
	$_SESSION['free'] = $free;	
	// return 'init '.json_encode($_SESSION);
	return $_SESSION['count'];
}

/*
* sessionban lévő free megjelenítése
* @param array []
* @return string html kód
*/ 
function cmm_jatek_nyomtassteis_sc_free($atts):string {
	if (is_admin()) {
		return '';	
	}
	return $_SESSION['free'];
}

/**
* sessionban lévő count és free -ből prison számítás és megjelenítése
* @param array []
* @return string html kód
*/
function cmm_jatek_nyomtassteis_sc_prison($atts):string {
	if (is_admin()) {
		return '';	
	}
	return (0 + $_SESSION['count'] - $_SESSION['free']);
}

// =========================== shortcode -ok a kosár lista oldalra ============================================

/**
* szükség esetén köszönő img megjelenítése
* @param array ["min" => ####, "img" => "xxxxx", "audio" => "xxxxxx" ]
* @return string html kód
* hivásakor a sessionban: place, count, add, date, free
* - ha a kosrban lévő érték >= min akkor kép megjelenítés és hang lejátszás
*/
function cmm_jatek_nyomtassteis_sc_thanks($atts):string {
	if (is_admin()) {
		return '';	
	}
	$result = '';
	if (!isset($atts['img'])) {
		$atts['img'] = '';	
	}
	if (!isset($atts['audio'])) {
		$atts['audio'] = '';	
	}
	if (!isset($atts['min'])) {
		$atts['min'] = '100000';	
	}
	$cartTotal = cmm_stripCurrency(WC()->cart->get_total());
	if ($cartTotal >= (0 + $atts['min'])) {
		$result .= '<div style="display:none"><iframe name="ifrmThanks" src="'.$atts['audio'].'"></iframe></div>';
		if ($atts['img'] != '') {
			$result .= '<img class="thanks" src="'.$atts['img'].'" />'; 	
		}
	}
	return $result;
}

/**
* ha most sikerült felszabadítani a települést akkor kép megjelenítés
* @param array ["min" => ####, "img" => "xxxxx", "audio" => "xxxxxx" ]
* @return string html kód
* hivásakor a sessionban: place, count, add, date, free
* - ha a sessionban lévő free < count akkor
*   -- ujra számolja a free értéket, tárolja sessionba
*   -- ha free >= count akkor kép megjelenités
* - sessionból newPlace törlése
*/
function cmm_jatek_nyomtassteis_sc_victory($atts):string {
	if (is_admin()) {
		return '';	
	}
	include_once __DIR__.'/model.coverage.php';	
	$model = new CoverageModel(false);
	
	$result = '';
	if (!isset($atts['img'])) {
		$atts['img'] = '';	
	}
	if (!isset($atts['audio'])) {
		$atts['audio'] = '';	
	}
	if ((isset($_SESSION['count']) & (isset($_SESSION['free'])))) {
		$count = 0 + $_SESSION['count'];
		$free = 0 + $_SESSION['free'];
		if ($free < $count) {
			
			// lezárt rendelések feldolgozása
			$place = $_SESSION['place'];
			$validDate = str_replace('.','',$_SESSION['date']);	
			$res = $model->realisedTotal('quantity',
					["city" => $place, "validDate" => $validDate]);
			if (count($res) > 0) {
					$free = 0 + $res[0]->sumQuantity + $_SESSION['add'];
			} else {
					$free = 	$_SESSION['add'];
			}	
			
			// cart (kosár) feldolgozása
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				   // $product = $cart_item['data'];
				   $product_id = $cart_item['product_id'];
				   $quantity = $cart_item['quantity'];
					$productInfo = $model->read($product_id); 
					if ($productInfo) {
						if (($productInfo->valid_start <= $validDate) &
						    ($productInfo->valid_end >= $validDate)) {
						    $free = $free + $quantity * $productInfo->package;	
				      } 
			      }
			}	
					
			if ($free >= $count) {
				// a kosár tartalmával együtt most felszabadul
				$result .= '<div style="display:none"><iframe name="ifrmVictory" src="'.$atts['audio'].'"></iframe></div>';
				if ($atts['img'] != '') {
					$result .= '<img class="victory" src="'.$atts['img'].'" />'; 	
				}
			}
		} // eddig nem volt szabad ez a település
	}
	return $result;
}

?>