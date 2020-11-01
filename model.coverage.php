<?php 
/**
 * Coverage object data model
 *  
 * szükség van hozzá SQL functionokra is!
 * 
 */
include_once __DIR__.'/model.php';
// baseRecord
class OrderRecord {
    public $order_id = 0;
    public $title = '';
    public $name = '';
    public $status = '';
    public $customer_id = 0;
    public $order_date = '';
    public $continent = 'Europe';   // shipping address
    public $country = 'Hungary'; // shipping address
    public $state = ''; // shipping address
    public $city = ''; // shipping address
    public $subcity= ''; // shipping address
    public $address_1; // shipping address
    public $address_2; // shipping address
} // class

class OrderItemRecord {
    public $oder_id = 0;
    public $oder_item_id = 0;
    public $quantiy = 0;
    public $unit = '';
    public $price = 0;
    public $currency = '';
    public $project_id = 0;
    public $distributor_id = 0;
    public $agent_id = 0;
    public $package = 1;
    public $valis_start = '';
    public $valid_end = '';
    public $place = '';
    public $place_id = 0;
}

/**
 * total és lefedettség számítás 
 * @author utopszkij
 */
class CoverageModel extends Model {
    private $orderTable = '';
    private $orderItemTable = '';
    private $projectTable = '';
    private $productTable = '';
    private $distributorTable = '';
    private $agentTable = '';
    private $projectCategoryTable = '';
    private $productCategoryTable = '';
    private $distributorCategoryTable = '';
    private $agentCategoryTable = '';
    private $orderCategoryTable = '';
    private $placeCategoryTable = '';

    function __construct($controller = false) {
        parent::__construct($controller);
        $this->modelName = 'coverage';
        global $wpdb;
        // az adatok POSTS és WOOCOMMERCE stilusban vannak több táblában tárolva,
        // strukturált elérésükhöz SQL subselecteket használunk
        $this->orderTable = '(
           select p.ID order_id, p.post_title title, p.post_name name, p.post_status status,
           m0.meta_value customer_id,
           if (m1.meta_value > "",m1.meta_value, date_format(p.post_date,"%Y-%m-%d")) order_date,
           "" continent,
           if(m7.meta_value <> "", m7.meta_value, m2.meta_value) country,
           if(m8.meta_value <> "", m8.meta_value, m3.meta_value) state,
           if(m9.meta_value <> "", m9.meta_value, m4.meta_value) city,
           "" subcity,
           if(m10.meta_value <> "", m10.meta_value, m5.meta_value) address_1,
           if(m11.meta_value <> "", m11.meta_value, m6.meta_value) address_2
           from '.$wpdb->prefix.'posts p
           left outer join '.$wpdb->prefix.'postmeta m0 on m0.post_id = p.ID and m0.meta_key = "_customer_user"
           left outer join '.$wpdb->prefix.'postmeta m1 on m1.post_id = p.ID and m1.meta_key = "_paid_date"
           left outer join '.$wpdb->prefix.'postmeta m2 on m2.post_id = p.ID and m2.meta_key = "_billing_country"
           left outer join '.$wpdb->prefix.'postmeta m3 on m3.post_id = p.ID and m3.meta_key = "_billing_state"
           left outer join '.$wpdb->prefix.'postmeta m4 on m4.post_id = p.ID and m4.meta_key = "_billing_city"
           left outer join '.$wpdb->prefix.'postmeta m5 on m5.post_id = p.ID and m5.meta_key = "_billing_address_1"
           left outer join '.$wpdb->prefix.'postmeta m6 on m6.post_id = p.ID and m6.meta_key = "_billing_address_2"
           left outer join '.$wpdb->prefix.'postmeta m7 on m7.post_id = p.ID and m7.meta_key = "_shipping_country"
           left outer join '.$wpdb->prefix.'postmeta m8 on m8.post_id = p.ID and m8.meta_key = "_shipping_state"
           left outer join '.$wpdb->prefix.'postmeta m9 on m9.post_id = p.ID and m9.meta_key = "_shipping_city"
           left outer join '.$wpdb->prefix.'postmeta m10 on m10.post_id = p.ID and m10.meta_key = "_shipping_address_1"
           left outer join '.$wpdb->prefix.'postmeta m11 on m11.post_id = p.ID and m11.meta_key = "_shipping_address_2"
           left outer join '.$wpdb->prefix.'postmeta m12 on m12.post_id = p.ID and m12.meta_key = "_place_id"
           where p.post_type="shop_order"
           )';
        
        // elképzelhető, hogy a nyomtassteis.hu -nál a product_id alapján kell majd az
        // amort_start és amort_end értékeket beállítani
        $this->orderItemTable = '(
         select oi.order_id, oi.order_item_id,
        	m0.meta_value product_id,
        	m1.meta_value quantity,
         pm0.meta_value unit,
        	m2.meta_value price,
         pm1.meta_value currency,
        	m3.meta_value project_id,
        	m4.meta_value distributor_id,
        	m5.meta_value agens_id,
         if (pm2.meta_value <> null,pm2.meta_value,1) package,
         pm3.meta_value valid_start,
         pm4.meta_value valid_end,
         pm5.meta_value place
        	from '.$wpdb->prefix.'woocommerce_order_items oi
        	left outer join '.$wpdb->prefix.'woocommerce_order_itemmeta m0 on m0.order_item_id=oi.order_item_id and m0.meta_key="_product_id"
         left outer join '.$wpdb->prefix.'postmeta pm0 on pm0.post_id = m0.meta_value and pm0.meta_key = "_sku"
        	left outer join '.$wpdb->prefix.'woocommerce_order_itemmeta m1 on m1.order_item_id=oi.order_item_id and m1.meta_key="_qty"
        	left outer join '.$wpdb->prefix.'woocommerce_order_itemmeta m2 on m2.order_item_id=oi.order_item_id and m2.meta_key="_line_subtotal"
        	left outer join '.$wpdb->prefix.'woocommerce_order_itemmeta m3 on m3.order_item_id=oi.order_item_id and m3.meta_key="_project_id"
        	left outer join '.$wpdb->prefix.'woocommerce_order_itemmeta m4 on m4.order_item_id=oi.order_item_id and m3.meta_key="_distributor_id"
        	left outer join '.$wpdb->prefix.'woocommerce_order_itemmeta m5 on m5.order_item_id=oi.order_item_id and m3.meta_key="_agens_id"
        	left outer join '.$wpdb->prefix.'postmeta pm1 on pm1.post_id=oi.order_id and pm1.meta_key="_order_currency"
         left outer join '.$wpdb->prefix.'postmeta pm2 on pm2.post_id = m0.meta_value and pm2.meta_key = "package"
         left outer join '.$wpdb->prefix.'postmeta pm3 on pm3.post_id = m0.meta_value and pm3.meta_key = "valid_start"
         left outer join '.$wpdb->prefix.'postmeta pm4 on pm4.post_id = m0.meta_value and pm4.meta_key = "valid_end"
         left outer join '.$wpdb->prefix.'postmeta pm5 on pm5.post_id = oi.order_item_id and pm5.meta_key = "place"
        	where oi.order_item_type="line_item"
           )';
        $this->projectTable = '';
        $this->productTable = ''.$wpdb->prefix.'posts';
        $this->distributorTable = '';
        $this->agentTable = '';
        $this->projectCategoryTable = '';
        $this->productCategoryTable = '';
        $this->distributorCategoryTable = '';
        $this->agentCategoryTable = '';
        $this->orderCategoryTable = '';
        $this->placeCategoryTable = '';
    }
    
     /**
     * details feldolgozó 'year', 'month', 'day' értéelmezése 
     * @param string $details
     * @param string $sqlDetails
     * @param string $sqlSelect
     */
    protected function detailsPreProcess(string $details, string &$sqlDetails, string &$sqlSelect) {
        if ($details == "year") {
                $sqlDetails = "substr(order_date,0,4)";
                $sqlSelect = $sqlDetals.' year';
        } else if ($details == "mounth") {
                $sqlDetails = "substr(order_date,5,2)";
                $sqlSelect = $sqlDetals.' mounth';
        } else if ($details == "day") {
                $sqlDetails = "substr(order_date,8,2)";
                $sqlSelect = $sqlDetals.' day';
        }
    } // detailsProcess
    
     
    /**
     * join sql összeállítása
     * @param unknown $details
     * @param unknown $filter
     */
    protected function buildJoin(string $details, array $filter, string &$sqlJoins) {
        if (($details == "placeCategory") | (isset($filter["placeCategory"]))) {
            $sqlJoins .= "\nLEFT OUTER JOIN ".$this->placeCategoryTable." AS plc
                ON plc.place_id = o.place_id";
            if ((isset($filter["placeCategory"])) & ($filter["placeCategory"] != "")) {
                $sqlJoins .= " AND plc.category_id = ".$filter["placeCategory_id"];
            }
        }
        if (($details == "productCategory") | (isset($filter["productCategory"]))) {
            $sqlJoins .= "\nLEFT OUTER JOIN ".$this->productCategoryTable." AS prdc
                ON prdc.product_id = o.product_id";
            if ((isset($filter["productCategory"])) & ($filter["productCategory"] != "")) {
                $sqlJoins .= " AND prdc.category_id = ".$filter["productCategory_id"];
            }
        }
        if (($details == "projectCategory") | (isset($filter["projectCategory"]))) {
            $sqlJoins .= "\nLEFT OUTER JOIN ".$this->projectCategoryTable." AS prjc
                ON prjc.project_id = o.project_id";
            if ((isset($filter["projectCategory"])) & ($filter["projectCategory"] != "")) {
                $sqlJoins .= " AND prjc.category_id = ".$filter["projectCategory_id"];
            }
        }
        if (($details == "distributorCategory") | (isset($filter["distributorCategory"]))) {
            $sqlJoins .= "\nLEFT OUTER JOIN ".$this->distributorCategoryTable." AS dc
                ON dc.distributor_id = o.distributor_id";
            if ((isset($filter["distributorCategory"])) & ($filter["distributorCategory"] != "")) {
                $sqlJoins .= " AND dc.category_id = ".$filter["distributorCategory_id"];
            }
        }
        if (($details == "agensCategory") | (isset($filter["agensCategory"]))) {
            $sqlJoins .= "\nLEFT OUTER JOIN ".$this->agensCategoryTable." AS ac
                ON ac.agens_id = o.agens_id";
            if ((isset($filter["agensCategory"])) & ($filter["agensCategory"] != "")) {
                $sqlJoins .= " AND ac.category_id = ".$filter["agensCategory_id"];
            }
        }
        if (($details == "customerCategory") | (isset($filter["customerCategory"]))) {
            $sqlJoins .= "\nLEFT OUTER JOIN ".$this->customerCategoryTable." AS cc
                ON cc.customer_id = o.customer_id";
            if ((isset($filter["customerCategory"])) & ($filter["customerCategory"] != "")) {
                $sqlJoins .= " AND cc.category_id = ".$filter["customerCategory_id"];
            }
        }
    } // buildJoin
    
     /**
     * amortStart és amortEnd filterek alapján figyelembe veeendő producz_id lista
     * @param array $filter
     * @return string szám,szám....
     */
    protected function amortFilter(array $filter):string {
       return ''; 
    }
    
    /**
     * where sql összeállítása
     * @param array $filter
     * @param string $sqlWhere
     */
    protected function buildWhere(array $filter, string &$sqlWhere) {
        if (isset($filter['status'])) {
            $statusFilter = $filter['status'];
        } else {
            $statusFilter = 'wc_completted';
        }
        $sqlWhere = 'o.status = "'.$statusFilter.'"';
        
        //TEST 
        $sqlWhere = '1';
        
        
        foreach ($filter as $fn => $fv) {
            if ($fn == 'orderDateStart') {
                $sqlWhere .= ' and `order_date` >= "'.$fv.'"';
            } else if ($fn == 'orderDateEnd') {
                $sqlWhere .= ' and `order_date` <= "'.$fv.'"';
            } else if ($fn == 'validDate') {
                $sqlWhere .= ' and valid_start <= "'.$fv.'" and valid_end >= "'.$fv.'"';
            } else {
                $w = explode(',',$fv);
                for ($i=0; $i<count($w); $i++) {
                    $w[$i] = '"'.$w[$i].'"';
                }
                $fv = implode(',',$w);
                $sqlWhere .= ' and `'.$fn.'` in ('.$fv.')';
            }
        }
    } // buildWhere;
    
    /**
     * realizált total lekérése
     * @param string $target 'price' | 'quantity'
     * @param array $filter  ["filterName" => "value",....]
     *   Lehetésegs elemei:   validStart (yyyyhhnn)  termék érvényesség
     *                        validtEnd   (yyyyhhnn)
     *                        orderDateStart  (yyyy-hh-nn)  megrendelés dátum
     *                        orderDateEnd    (yyyy-hh-nn)
     *                        status ('' | 'wc_completted')
     *                        continent string vagy string lista  - szállítási cím
     *                        country string vagy string lista
     *                        region string vagy string lista
     *                        state string vagy string lista
     *                        city string vagy string lista
     *                        place string vagy string lista  - jatek.nyomtassteis telepulés
     *                        subcity string vagy string lista
     *                        address_1 string vagy string lista
     *                        address_2 string vagy string lista
     *                        placeCategory szám vagy számok listája
     *                        product_id szám szám vagy számok listája
     *                        productCategory szám vagy számok listája
     *                        project_id szám vagy számok listája
     *                        projectCategory  szám vagy számok listája
     *                        distributor_id  szám vagy számok listája
     *                        distributorCategory  szám vagy számok listája
     *                        agens_id  szám vagy számok listája
     *                        agensCategory szám vagy számok listája
     *                        customer_id  szám vagy számok listája
     *                        customerCategory szám vagy számok listája
     * @param string $details ""|"year"|"day"|"month"|"satus"|"place"|"placeÍcategory"|.....customerCategory
     * @return array of object 
     *   [{unit, sumQuantity},...] vagy
     *   [{currency, sumPrice},.....] vagy
     *   [{detail, unit, sumQuantity},...] vagy
     *   [{detail, currency, sumPrice},.....]
     */
    public function realisedTotal(string $target, array $filter=[], string $details="") {
        global $wpdb;
        $sqlDetails = $details;
        $sqlSelect = $details;
        $sqlWhere = '';
        $sqlJoins = "";
        $this->detailsPreProcess($details, $sqlDetails, $sqlSelect);
        $this->buildJoin($details, $filter, $sqlJoins);
        if ($sqlSelect != '') {
            $sqlSelect .= ',';
        }
        if ($sqlDetails != '') {
            $sqlDetails .= ',';
        }
        if ($target == 'quantity') {
            $sqlSelect .= 'unit, sum(quantity * package) sumQuantity';
            $sqlGroup = 'group by '.$sqlDetails.'unit';
        } else {
            $sqlSelect .= 'currency, sum(price) sumPrice';
            $sqlGroup = 'group by '.$sqlDetails.'currency';
        }
        $this->buildWhere($filter, $sqlWhere);
        $sql = 'select '.$sqlSelect.'
            from '.$this->orderItemTable.' op 
            left outer join '.$this->orderTable.' o  on o.order_id=op.order_id '.$sqlJoins.'
            where '.$sqlWhere.'
            '.$sqlGroup;
        $result = $wpdb->get_results($sql);
        return $result;
    } // realisedTotal
    

	 /**
	 * egy product rekord olvasása adatbázisból
	 * @param int product id
	 * @retrun object {id, title, price, package, valid_start, valid_end, unit}
	 */    
    public function read(int $id) {
        global $wpdb;
        $sql = 'select p.ID,
    	p.post_title,
    	m0.meta_value price,
    	m1.meta_value package, 
    	m2.meta_value valid_start, 
    	m3.meta_value valid_end,
    	m4.meta_value unit
    	from '.$wpdb->prefix.'posts p
    	left outer join '.$wpdb->prefix.'postmeta m0 on m0.post_id = p.ID and m0.meta_key = "_price"
    	left outer join '.$wpdb->prefix.'postmeta m1 on m1.post_id = p.ID and m1.meta_key = "package"
    	left outer join '.$wpdb->prefix.'postmeta m2 on m2.post_id = p.ID and m2.meta_key = "valid_start"
    	left outer join '.$wpdb->prefix.'postmeta m3 on m3.post_id = p.ID and m3.meta_key = "valid_end"
    	left outer join '.$wpdb->prefix.'postmeta m4 on m4.post_id = p.ID and m4.meta_key = "_sku"
    	where p.ID = '.$id.'
    	';
    	$res = $wpdb->get_results($sql);
		if (count($res) > 0) {
			$result = $res[0];
		} else {
			$result = false;
		}    	
		return $result;    	
    }
    
    /**
     * UMS marker icon módosítása
     * @param string $title
     * @param int $icon
     */
    public function updateUmsMarker(string $title, int $icon) {
        global $wpdb;
        $sql = 'update '.$wpdb->prefix.'ums_markers set icon='.$icon.' where title="'.$title.'"';
        $wpdb->query($sql);        
    }
} // class
?>