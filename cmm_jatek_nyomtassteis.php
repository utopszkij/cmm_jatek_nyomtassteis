<?php
/**
* Plugin Name: cmm_jatek_nyomtassteis
* Plugin URI: http://www.github.com/utopszkij/cmm_jatek_nyomtassteis
* Description: Kiegészítés woocommerce -hez, szabd/rab kijelzés, köszönet nagy vásárlásért, település felszabaditás jelzése. 
* Version: 1.0 ß-test
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
* 
* megrendelés tárolásakor a szállítási cím település alapján modositja az ums markers táblában a 
* title="szállítási cím települése" markerekben az "icon" -t és a "description" -t.
*/

global $cmm_session,$cmm_ctrl;
include_once __DIR__.'/class.coverage.php';
/**
 * init plugin, load js és shortcode definiciók
 */ 
add_action('init','cmm_jatek_nyomtassteis_init');
function cmm_jatek_nyomtassteis_init(){
    global $cmm_session, $cmm_ctrl;
    
    //+ AJAX backends
    if (isset($_GET['cmm_get_states'])) {
        echo json_encode( WC()->countries->get_allowed_country_states()[$_GET["cmm_get_states"]]);
        exit();
    }
    if (isset($_GET['cmm_get_countries'])) {
        echo json_encode( WC()->countries->get_allowed_countries());
        exit();
    }
    //+ AJAX backends
    
    
    $cmm_session = new WC_session_handler();
	$cmm_session->init();
	$cmm_ctrl = new CoverageController();
	
    add_action( 'the_content', 'cmm_jatek_nyomtassteis_js');
    add_shortcode('cmm_init', 'cmm_jatek_nyomtassteis_sc_init');
    add_shortcode('cmm_free', 'cmm_jatek_nyomtassteis_sc_free');
    add_shortcode('cmm_prison', 'cmm_jatek_nyomtassteis_sc_prison');
    add_shortcode('cmm_thanks', 'cmm_jatek_nyomtassteis_sc_thanks');
    add_shortcode('cmm_victory', 'cmm_jatek_nyomtassteis_sc_victory');
	add_action('woocommerce_thankyou','cmm_jatek_nyomtassteis_thankyou');
	add_action('acf/render_field/name=cmm_country','cmm_acfextends0',15);
	function cmm_acfextends0($field) {
	    global $cmm_ctrl;
	    $cmm_ctrl->countryFieldId = $field['id'];
	}
	add_action('acf/render_field/name=cmm_state','cmm_acfextends1',16);
	function cmm_acfextends1($field) {
	    global $cmm_ctrl;
	    $cmm_ctrl->stateFieldId = $field['id'];
	    $cmm_ctrl->placeFormJs();
	}
	add_action('admin_menu', 'cmm_jatek_nyomtassteis_plugin_create_menu');
	function cmm_jatek_nyomtassteis_plugin_create_menu() {
	    add_options_page("cmm_jatek_nyomtassteis WordPress bővítmény", "cmm_jatek_nyomtassteis WordPress bővítmény", 1,
	        "cmm_jatek_nyomtassteis_admin", "cmm_jatek_nyomtassteis_admin");
	} 
}

/**
 * plugin admin oldal Beállítások menüpont
 */
function cmm_jatek_nyomtassteis_admin() {
    global $cmm_ctrl;
    $cmm_ctrl->jatek_nyomtassteis_admin();
}

/**
* rendelés elküldést megköszönő képernyő végén aktiválódik ez a rutin.
* 'woocommerce_thankyou' horgony asktivizálja.
* funkciókja: ums markerekben modosítja az icont -t és a description -t
* @param int $orderId
*/
function cmm_jatek_nyomtassteis_thankyou(int $orderId) {
    global $cmm_ctrl;
    $cmm_ctrl->jatek_nyomtassteis_thankyou($orderId);
}

/**
* js betöltés. Funkciója: a sessionban lévő 'place' adat alapján a rendelés szállítási cím kitöltése.
*/
function cmm_jatek_nyomtassteis_js($content) {
    global $cmm_ctrl;
    return $cmm_ctrl->jatek_nyomtassteis_js($content);
}

// ================ shotcode -ok a kiemelt területek oldalakra ==================================

/**
* jatek_nyomtassteis short code rendszer init KÖTELEZŐ HASZNÁLNI, EZ LEGYEN AZ ELSŐ!
* @param array ["count" => ####, "add" => ###, "date" => "yyyy.mm.dd"] minden paraméter opcionális 
* @return string html kód
* - $post->post_title a place adat
* - free számításda és tárolása sessionba
* - sessionba: place, count, add, date, freee 
*/
function cmm_jatek_nyomtassteis_sc_init($atts = []):string {
    global $cmm_ctrl;
    return $cmm_ctrl->jatek_nyomtassteis_sc_init($atts);
}

/*
* sessionban lévő free megjelenítése
* @param array []
* @return string html kód
*/ 
function cmm_jatek_nyomtassteis_sc_free($atts):string {
    global $cmm_ctrl;
    return $cmm_ctrl->jatek_nyomtassteis_sc_free($atts);
}

/**
* sessionban lévő count és free -ből prison számítás és megjelenítése
* @param array []
* @return string html kód
*/
function cmm_jatek_nyomtassteis_sc_prison($atts):string {
    global $cmm_ctrl;
    return $cmm_ctrl->jatek_nyomtassteis_sc_prison($atts);
}

// =========================== shortcode -ok a kosár lista oldalakra ============================================

/**
* szükség esetén köszönő img megjelenítése
* @param array ["min" => ####, "img" => "xxxxx", "audio" => "xxxxxx" ]
* @return string html kód
* hivásakor a sessionban: place, count, add, date, free
* - ha a kosrban lévő érték >= min akkor kép megjelenítés és hang lejátszás
*/
function cmm_jatek_nyomtassteis_sc_thanks($atts):string {
    global $cmm_ctrl;
    return $cmm_ctrl->jatek_nyomtassteis_sc_thanks($atts);
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
    global $cmm_ctrl;
    return $cmm_ctrl->jatek_nyomtassteis_sc_victory($atts);
}

?>