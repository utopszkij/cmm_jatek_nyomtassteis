<?php

include_once __DIR__.'/model.coverage.php';

/**
* coverage controller
* szükséges global: $cmm_session
*/
class CoverageController {
    protected $model;
    
    function __construct() {
        $this->model = new CoverageModel();
    }
    
    
    /**
     * plugin admin oldal Beállítások menüpont
     */
    public function jatek_nyomtassteis_admin() {
        if (isset($_POST['prisonIcon'])) {
            update_option('cmm_jatek_useMapIcons',$_POST['useMapIcons']);
            update_option('cmm_jatek_prisonMapIcon',$_POST['prisonIcon']);
            update_option('cmm_jatek_warMapIcon',$_POST['warIcon']);
            update_option('cmm_jatek_freeMapIcon',$_POST['freeIcon']);
        }
        $useMapIcons = get_option('cmm_jatek_useMapIcons',0);
        $prisonIcon = get_option('cmm_jatek_prisonMapIcon',31);
        $warIcon =  get_option('cmm_jatek_warMapIcon',30);
        $freeIcon =  get_option('cmm_jatek_freeMapIcon',21);
        include __DIR__.'/readme.html';
        ?>
    		<form method="post" action="#">
    			<h4>ultimate map (ums) beállítás</h4>
    			<p><input type="checkbox" name="useMapIcons" value="1"<?php if ($useMapIcons == 1) echo ' checked="checked"'; ?> />
    				Modosit Ultimate Map ikonokat</p>
    			<p>Ha be van jelölve akkor a [free] híváskor a title=településnév UMAP ikonokat módosítja a számitás eredménye alapján.
    			</p>
    			<p>Rabságban UMS ikon_id:<br />
                    <input type="text" name="prisonIcon" value="<?php echo $prisonIcon; ?>" /></p> 
    			<p>Részben szabad UMS ikon_id:<br />
                    <input type="text" name="warIcon" value="<?php echo $warIcon; ?>" /></p> 
    			<p>Szabad UMS ikon_id:<br />
                    <input type="text" name="freeIcon" value="<?php echo $freeIcon; ?>" /></p>
                <p><button type="submit">Tárol</button></p>     
    		</form>
    	<?php 
    }
    
    /**
    * rendelés elküldést megköszönő képernyő végén aktiválódik ez a rutin.
    * 'woocommerce_thankyou' horgony asktivizálja.
    * funkciókja: ums markerekben modosítja az icont -t és a description -t
    * @param int $orderId
    */
    public function jatek_nyomtassteis_thankyou(int $orderId) {
        $model = $this->model;
        // shipping city kiolvasása adatbázisból $orderId alapján
        $order = $model->readOrder( $orderId );
        if ($order) {
            $city = $order->city;
        } else {
            return;
        }
    	// place olvasása adatbázisból $city alapján
        $place = $model->readPlaceByTitle($city);
        if ($place) {
            
        	// free érték újra számolása
        	$validDate = date('Ymd');
            $res = $model->realisedTotal('quantity',
                ["city" => $city, "validDate" => $validDate]);
            if (count($res) > 0) {
                $free = 0 + $res[0]->sumQuantity + $place->add;
            } else {
                $free = $place->add;
            }
            
            // coverage számyitás, és megjelenés csinosítása
            if ($place->count > 0) {
                $coverage = round(($free/$place->count)*100);
            }  else {
                $coverage = 0;
            }
            if (($free > 0) & ($coverage < 1)) {
                $coverage = '<1';
            } else if (($free < $place->count) & ($coverage == 100)) {
                $coverage = '>99';
            } else {
                $covergae = ' '.$coverage;
            }
    
        	// beállítási paraméterek olvasása
            $useMapIcons = get_option('cmm_jatek_useMapIcons',0);
            $prisonIcon = get_option('cmm_jatek_prisonMapIcon',31);
            $warIcon =  get_option('cmm_jatek_warMapIcon',30);
            $freeIcon =  get_option('cmm_jatek_freeMapIcon',21);
            
            // marker modosítása az adatbázisban
            if ($useMapIcons == 1) {
                 if ($free == 0) {
                    $model->updateUmsMarker($city, $prisonIcon, $coverage);
                 } else if ($free < $place->count) {
                    $model->updateUmsMarker($city, $warIcon, $coverage);
                 } else {
                    $model->updateUmsMarker($city, $freeIcon, $coverage);
                 }
            }
        } // place létezik
    }
    
    
    /**
    * js betöltés. Funkciója: a sessionban lévő 'place' adat alapján a rendelés szállítási cím kitöltése.
    */
    public function jatek_nyomtassteis_js($content) {
        global $cmm_session;
        if ($cmm_session->get('cmm_place') != '') {
    			echo '
    			<script type="text/javascript">
    			jQuery(function() {
    				if (jQuery("#shipping_city")) {
    					jQuery("#shipping_city").val("'.$cmm_session->get('cmm_place').'");
    					jQuery("#shipping_state").val("'.$cmm_session->get('cmm_state').'");
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
    * currency string eltávolítása a kosárból kiolvasott stringből
    */
    protected function cmm_stripCurrency($cartTotal) {	
    	$cartTotal = strip_tags($cartTotal);
    	$cartTotal = html_entity_decode ($cartTotal);
    	$cartTotal = preg_replace('/[^0-9,]/','',$cartTotal);
    	$cartTotal = str_replace(',','.',$cartTotal);
    	return $cartTotal;
    }	
    	
    /**
     * felszabaditott családok száma a lezárt megrendelésekből és auser kosarából
     * @param string $place
     * @param string $validDate
     * @param int $add
     * @return int
     */
    protected function processFree(string $place, string $validDate, int $add): int {
        $model = $this->model;
        // lezárt rendelések feldolgozása
        $res = $model->realisedTotal('quantity',
        ["city" => $place, "validDate" => $validDate]);
        if (count($res) > 0) {
            $free = 0 + $res[0]->sumQuantity + $add;
        } else {
            $free = $add;
        }
        
        // cart (kosár) feldolgozása
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            // $product = $cart_item['data'];
            $product_id = $cart_item['product_id'];
            $quantity = $cart_item['quantity'];
            $productInfo = $model->readProduct($product_id);
            if ($productInfo) {
                if (($productInfo->valid_start <= $validDate) &
                    ($productInfo->valid_end >= $validDate)) {
                        $free = $free + ($quantity * (0 + $productInfo->package));
                    }
            }
        }
        return $free;
    }
    
    // ================ shotcode -ok a kiemelt területek oldalakra ==================================
    
    /**
    * jatek_nyomtassteis short code rendszer init KÖTELEZŐ HASZNÁLNI, EZ LEGYEN AZ ELSŐ!
    * @param array ["count" => ####, "add" => ###, "date" => "yyyy.mm.dd"] minden paraméter opcionális 
    * @return string html kód
    * - global $post  "place" post rekord
    * - free számításda és tárolása sessionba
    * - sessionba: continent, country, state, place, count, add, date, freee 
    */
    public function jatek_nyomtassteis_sc_init($atts = []):string {
    	if (is_admin()) {
    		return '';	
    	}
    	global $post;
    	global $cmm_session;
    	$model = $this->model;
    	$place = $model->readPlace($post->ID);
    	
    	if (!$place) {
    	    $place = new PlaceRecord();
    	}
    	if (!is_array($atts)) {
    	    $atts = [];
    	}
    	if (!isset($atts['date'])) {
    			$atts['date'] = date('Y.m.d');
    	}
    	if (!isset($atts['count'])) {
    			$atts['count'] = $place->count;
    	}
    	if (!isset($atts['add'])) {
    			$atts['add'] = $place->add;
    	}
    	if ($atts['count'] == '') {
    	    $atts['count'] = 0;
    	}
    	if ($atts['add'] == '') {
    	    $atts['add'] = 0;
    	}
    	$cmm_session->set('cmm_place',$post->post_title);
    	$cmm_session->set('cmm_continent',$place->continent);
    	$cmm_session->set('cmm_country',$place->country);
    	$cmm_session->set('cmm_state',$place->state);
    	$cmm_session->set('cmm_date',$atts['date']);
    	$cmm_session->set('cmm_count', $atts['count']);
    	$cmm_session->set('cmm_add', $atts['add']);
    	$place = $cmm_session->get('cmm_place');
    	$count = $cmm_session->get('cmm_count');
    	$add = 0 + $cmm_session->get('cmm_add');
    	$validDate = str_replace('.','',$cmm_session->get('cmm_date'));
    
    	// számítás
    	$free = $this->processFree($place, $validDate, $add);
    	
        // határértékek kezelése
    	if ($free < 0) {
    			$free = 0;	
    	}
    	if ($free > $cmm_session->get('cmm_count')) {
    	    $free = $cmm_session->get('cmm_count');	
    	}
    	
    	// tárolás sessionba
    	$cmm_session->set('cmm_free', $free);	
    	
    	// count és add adat tárolása adatbázisba
    	$model->savePlaceCountAdd($post->ID, (0 + $cmm_session->get('cmm_count')), (0 + $cmm_session->get('cmm_add')));
    		
    	return number_format ($cmm_session->get('cmm_count'), 0, ',', ' ');
    	
    }
    
    /*
    * sessionban lévő free megjelenítése
    * @param array []
    * @return string html kód
    */ 
    public function jatek_nyomtassteis_sc_free($atts):string {
    	if (is_admin()) {
    		return '';	
    	}
    	global $cmm_session;
    	return number_format ($cmm_session->get('cmm_free'), 0, ',', ' ');
    }
    
    /**
    * sessionban lévő count és free -ből prison számítás és megjelenítése
    * @param array []
    * @return string html kód
    */
    public function jatek_nyomtassteis_sc_prison($atts):string {
    	if (is_admin()) {
    		return '';	
    	}
    	global $cmm_session;
    	return number_format((0 + $cmm_session->get('cmm_count') - $cmm_session->get('cmm_free')),0,',',' ');
    }
    
    // =========================== shortcode -ok a kosár lista oldalakra ============================================
    
    /**
    * szükség esetén köszönő img megjelenítése
    * @param array ["min" => ####, "img" => "xxxxx", "audio" => "xxxxxx" ]
    * @return string html kód
    * hivásakor a sessionban: place, count, add, date, free
    * - ha a kosrban lévő érték >= min akkor kép megjelenítés és hang lejátszás
    */
    public function jatek_nyomtassteis_sc_thanks($atts):string {
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
    	$cartTotal = $this->cmm_stripCurrency(WC()->cart->get_total());
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
    public function jatek_nyomtassteis_sc_victory($atts):string {
    	if (is_admin()) {
    		return '';	
    	}
    	$model = $this->model;
    	global $cmm_session;
    	
    	$result = '';
    	if (!isset($atts['img'])) {
    		$atts['img'] = '';	
    	}
    	if (!isset($atts['audio'])) {
    		$atts['audio'] = '';	
    	}
    	if (($cmm_session->get('cmm_count') != '') & ($cmm_session->get('cmm_free') != '')) {
    		$count = 0 + $cmm_session->get('cmm_count');
    		$free = 0 + $cmm_session->get('cmm_free');
    		$add = 0 + $cmm_session->get('cmm_add');
    		$place = $cmm_session->get('cmm_place');
    		$validDate = str_replace('.','',$cmm_session->get('cmm_date'));
    		if ($free < $count) {
    			
    		    // újra számítás
    		    $free = $this->processFree($place, $validDate, $add);
    					
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
} // controller class
?>