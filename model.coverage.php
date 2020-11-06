<?php 
/**
 * Coverage object data model
 * Az adatok a wp, woo stilusában nagyrészt a 
 * posts, postmeta ,woocommerce_order_items, woocommerce_order_itemmeta rekordokban vannak
 * subselectekkel virtuális táblákká konvertáljuk őket.
 * szükséges global $wpdb
 */
// virtuális rekordok
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

class PlaceRecord {
    public $id = 0;
    public $title = '';
    public $parent = 0;
    public $count = 0;
    public $add = 0;
    public $continent = '';
    public $country = '';
    public $state = '';
}

class ProductRecord {
    public $id = 0;
    public $title = '';
    public $price = 0;
    public $package = '';
    public $valid_start = '';
    public $valid_end = '';
    public $unit = '';
}

/**
 * total és lefedettség számítás adatmodell 
 * @author utopszkij
 */
class CoverageModel {
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

    
    //+ univerzális model rutinok
    
    /**
     * ellenörzi meg van-e a szükséges ACF group, ha nincs létrehozza
     * @param string $postName
     * @return int
     */
    protected function acfGroupCheck(string $postType):int {
        global $wpdb;
        $res = $wpdb->get_results('select * from '.$wpdb->prefix.'posts
            where post_status = "publish" and post_type = "acf-field-group" and
            post_content like "%'.$postType.'%"');
        if (!$res) {
            $acfGroup = new stdClass();
            $content = 'a:7:{s:8:"location";a:1:{i:0;a:1:{i:0;a:3:{s:5:"param";s:9:"post_type";s:8:"operator";s:2:"==";s:5:"value";s:4:"area";}}}s:8:"position";s:6:"normal";s:5:"style";s:7:"default";s:15:"label_placement";s:3:"top";s:21:"instruction_placement";s:5:"label";s:14:"hide_on_screen";s:0:"";s:11:"description";s:0:"";}';
            $content = str_replace('"value";s:4:"area"','"value";s:'.strlen($postType).':"'.$postType.'"',$content);
            $newPost = array(
                'post_title'     => $postType,
                'post_excerpt'   => sanitize_title($postType),
                'post_name'      => 'group_' . uniqid(),
                'post_date'      => date( 'Y-m-d H:i:s' ),
                'comment_status' => 'closed',
                'post_status'    => 'publish',
                'post_content'   => $content,
                'post_type'      => 'acf-field-group',
            );
            $result = wp_insert_post($newPost);
        } else {
            $result = $res[0]->ID;
        }
        return $result;
    }
    
    /**
     * ellenörzi meg van-e az acfField? ha nincs létrehozza
     * @param int $groupId
     * @param string $fieldName
     * @param string $label
     * @param string $content
     */
    protected function acfFieldCheck(int $groupId, string $fieldName, string $label, string $content)  {
        global $wpdb;
        $res = $wpdb->get_results('select * from '.$wpdb->prefix.'posts
            where post_status = "publish" and post_type = "acf-field" and
            post_parent = '.$groupId.' and post_excerpt = "'.$fieldName.'"');
        if (!$res) {
            $newPost = array(
                'post_parent'    => $groupId,
                'post_title'     => $label,
                'post_excerpt'   => sanitize_title($fieldName),
                'post_name'      => 'field_' . uniqid(),
                'post_date'      => date( 'Y-m-d H:i:s' ),
                'comment_status' => 'closed',
                'post_status'    => 'publish',
                'post_content'   => $content,
                'post_type'      => 'acf-field'
            );
            wp_insert_post($newPost);
        }
        return;
    }
    
    /**
     * mysql secure data
     * @param string $s
     * @return string
     */
    public function escape(string $s) {
        if (is_numeric($s)) {
            $result = $s;
        } else {
            $result = '"'.addslashes($s).'"';
        }
        return $result;
    }
    
    /**
     * univerzális (virtuális)rekord olvasás adatbázisból egy kulcsmező alapján
     * @param string $tableName
     * @param string $keyName
     * @param unknown $keyValue
     * @return record object vagy false
     */
    public function readBy(string $tableName, string $keyName, $keyValue) {
        global $wpdb;
        $res = $wpdb->get_results('select r.* from '.$tableName.' r
        where r.`'.$keyName.'`='.$this->escape($keyValue));
        if (!$res) {
            $result = false;
        } else  if (count($res) > 0) {
            $result = $res[0];
        } else {
            $result = false;
        }
        return $result;
    }
    
    //- univerzális model rutinok
    
    
    function __construct($controller = false) {
        $this->modelName = 'coverage';
        global $wpdb;
        $this->dbCheck();
        
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
        
        $this->placeTable = '(select p.ID id, p.post_title title, p.post_parent parent,
         pm0.meta_value count, pm1.meta_value `add`, 
         pm2.meta_value continent, pm3.meta_value country, pm4.meta_value state,
         pm5.meta_value pol_regio1, pm6.meta_value pol_regio2
         from '.$wpdb->prefix.'posts p
         left outer join '.$wpdb->prefix.'postmeta pm0 on pm0.post_id=p.ID and pm0.meta_key="cmm_count"
         left outer join '.$wpdb->prefix.'postmeta pm1 on pm1.post_id=p.ID and pm1.meta_key="cmm_add"
         left outer join '.$wpdb->prefix.'postmeta pm2 on pm2.post_id=p.ID and pm2.meta_key="cmm_continent"
         left outer join '.$wpdb->prefix.'postmeta pm3 on pm3.post_id=p.ID and pm3.meta_key="cmm_country"
         left outer join '.$wpdb->prefix.'postmeta pm4 on pm4.post_id=p.ID and pm4.meta_key="cmm_state"
         left outer join '.$wpdb->prefix.'postmeta pm5 on pm5.post_id=p.ID and pm5.meta_key="cmm_pol_regio1"
         left outer join '.$wpdb->prefix.'postmeta pm6 on pm6.post_id=p.ID and pm6.meta_key="cmm_pol_regio2"
         where p.post_type = "cmm_place"
        )';
        
        $this->productTable = '(select p.ID id,
        p.post_title title,
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
        where post_type="product")';
        
        // ezek csak a várható esetleges késöbbi fejlesztéshez kellenek majd
        $this->projectTable = '';
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
     * ellenörzi, hogy megvannak-e szükséges post tipusok, 
     * ACF gruppok és mezők, 
     * ha nincsenek kreálja őket.
     */
    protected function dbCheck() {
        // cmm_place
        register_post_type( 'cmm_place',
            array(
                'labels' => array(
                    'name' => 'Place',
                    'singular_name' => 'Place'
                ),
                'public' => true,
                'has_archive' => true,
                'rewrite' => array('slug' => 'place'),
                'show_in_rest' => true,
                
            )
        );
        $acfPlaceId = $this->acfGroupCheck('cmm_place');
        $this->acfFieldCheck($acfPlaceId, 'cmm_continent', 'Kontinens',
            'a:13:{s:4:"type";s:6:"select";s:12:"instructions";s:0:"";s:8:"required";i:1;s:17:"conditional_logic";i:0;s:7:"wrapper";a:3:{s:5:"width";s:0:"";s:5:"class";s:0:"";s:2:"id";s:0:"";}s:7:"choices";a:1:{s:2:"EU";s:6:"Europa";}s:13:"default_value";s:2:"EU";s:10:"allow_null";i:0;s:8:"multiple";i:0;s:2:"ui";i:0;s:13:"return_format";s:5:"value";s:4:"ajax";i:0;s:11:"placeholder";s:0:"";}');
        $this->acfFieldCheck($acfPlaceId, 'cmm_country', 'Ország',
            'a:13:{s:4:"type";s:6:"select";s:12:"instructions";s:0:"";s:8:"required";i:1;s:17:"conditional_logic";i:0;s:7:"wrapper";a:3:{s:5:"width";s:0:"";s:5:"class";s:0:"";s:2:"id";s:0:"";}s:7:"choices";a:1:{s:2:"HU";s:7:"Hungary";}s:13:"default_value";s:2:"HU";s:10:"allow_null";i:0;s:8:"multiple";i:0;s:2:"ui";i:0;s:13:"return_format";s:5:"value";s:4:"ajax";i:0;s:11:"placeholder";s:0:"";}');
        $this->acfFieldCheck($acfPlaceId, 'cmm_state', 'Megye',
            'a:13:{s:4:"type";s:6:"select";s:12:"instructions";s:0:"";s:8:"required";i:1;s:17:"conditional_logic";i:0;s:7:"wrapper";a:3:{s:5:"width";s:0:"";s:5:"class";s:0:"";s:2:"id";s:0:"";}s:7:"choices";a:20:{s:2:"BK";s:12:"Bács-Kiskun";s:2:"BE";s:7:"Békés";s:2:"BA";s:7:"Baranya";s:2:"BZ";s:22:"Borsod-Abaúj-Zemplén";s:2:"BU";s:8:"Budapest";s:2:"CS";s:9:"Csongrád";s:2:"FE";s:6:"Fejér";s:2:"GS";s:18:"Győr-Moson-Sopron";s:2:"HB";s:12:"Hajdú-Bihar";s:2:"HE";s:5:"Heves";s:2:"JN";s:21:"Jász-Nagykun-Szolnok";s:2:"KO";s:18:"Komárom-Esztergom";s:2:"NO";s:8:"Nógrád";s:2:"PE";s:4:"Pest";s:2:"SO";s:6:"Somogy";s:2:"SZ";s:23:"Szabolcs-Szatmár-Bereg";s:2:"TO";s:5:"Tolna";s:2:"VA";s:3:"Vas";s:2:"VE";s:9:"Veszprém";s:2:"ZA";s:4:"Zala";}s:13:"default_value";s:2:"BU";s:10:"allow_null";i:0;s:8:"multiple";i:0;s:2:"ui";i:0;s:13:"return_format";s:5:"value";s:4:"ajax";i:0;s:11:"placeholder";s:0:"";}');

        // product
        $acfProductId = $this->acfGroupCheck('product');
        $this->acfFieldCheck($acfProductId, 'package', 'Csomag (példány)',
            'a:12:{s:4:"type";s:6:"number";s:12:"instructions";s:0:"";s:8:"required";i:1;s:17:"conditional_logic";i:0;s:7:"wrapper";a:3:{s:5:"width";s:0:"";s:5:"class";s:0:"";s:2:"id";s:0:"";}s:13:"default_value";s:0:"";s:11:"placeholder";s:0:"";s:7:"prepend";s:0:"";s:6:"append";s:0:"";s:3:"min";s:0:"";s:3:"max";s:0:"";s:4:"step";s:0:"";}');
        $this->acfFieldCheck($acfProductId, 'valid_end', 'Érvényesség vége',
            'a:8:{s:4:"type";s:11:"date_picker";s:12:"instructions";s:0:"";s:8:"required";i:0;s:17:"conditional_logic";i:0;s:7:"wrapper";a:3:{s:5:"width";s:0:"";s:5:"class";s:0:"";s:2:"id";s:0:"";}s:14:"display_format";s:5:"Y.m.d";s:13:"return_format";s:5:"Y.m.d";s:9:"first_day";i:1;}');
        $this->acfFieldCheck($acfProductId, 'valid_start', 'Érvényesség kezdete',
            'a:8:{s:4:"type";s:11:"date_picker";s:12:"instructions";s:0:"";s:8:"required";i:0;s:17:"conditional_logic";i:0;s:7:"wrapper";a:3:{s:5:"width";s:0:"";s:5:"class";s:0:"";s:2:"id";s:0:"";}s:14:"display_format";s:5:"Y.m.d";s:13:"return_format";s:5:"Y.m.d";s:9:"first_day";i:1;}');
        $this->acfFieldCheck($acfProductId, 'cmm_count', 'Családok száma',
            'a:12:{s:4:"type";s:6:"number";s:12:"instructions";s:0:"";s:8:"required";i:0;s:17:"conditional_logic";i:0;s:7:"wrapper";a:3:{s:5:"width";s:0:"";s:5:"class";s:0:"";s:2:"id";s:0:"";}s:13:"default_value";s:0:"";s:11:"placeholder";s:0:"";s:7:"prepend";s:0:"";s:6:"append";s:0:"";s:3:"min";s:0:"";s:3:"max";s:0:"";s:4:"step";s:0:"";}');
        $this->acfFieldCheck($acfProductId, 'cmm_add', 'Szabad scsaládok szám kezdő értéke',
            'a:12:{s:4:"type";s:6:"number";s:12:"instructions";s:0:"";s:8:"required";i:0;s:17:"conditional_logic";i:0;s:7:"wrapper";a:3:{s:5:"width";s:0:"";s:5:"class";s:0:"";s:2:"id";s:0:"";}s:13:"default_value";s:0:"";s:11:"placeholder";s:0:"";s:7:"prepend";s:0:"";s:6:"append";s:0:"";s:3:"min";s:0:"";s:3:"max";s:0:"";s:4:"step";s:0:"";}');
    }
    
    
    /**
     * megrendelés rekord olvasása az adatbázisból
     * @param int $orderId
     * @return OrderRecord vagy false
     */
    public function readOrder(int $orderId) {
        return $this->readBy($this->orderTable,'order_id', $orderId);
    }
    
    /**
     * place rekord olvasása az adatbázisból
     * @param int $id
     * @return PlaceRecord vagy false
     */
    public function readPlace(int $id) {
        return $this->readBy($this->placeTable,'id', $id);
    }
    
    /**
     * place rekord olvasása az adatbázisból név alapján
     * @param string $title
     * @return PleceRecord vagy false
     */
    public function readPlaceByTitle(string $title) {
        return $this->readBy($this->placeTable,'title', $title);
    }
    
    /**
     * egy product rekord olvasása adatbázisból
     * @param int product id
     * @retrun ProductRecord vagy false
     */
    public function readProduct(int $id) {
        return $this->readBy($this->productTable,'id', $id);
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
     * amortStart és amortEnd filterek alapján figyelembe veeendő product_id lista
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
            $sqlWhere = 'o.status = "'.$statusFilter.'"';
        } else {
            $sqlWhere = '1';
        }
        foreach ($filter as $fn => $fv) {
            if ($fn == 'orderDateStart') {
                $sqlWhere .= ' and `order_date` >= '.$this->escape($fv);
            } else if ($fn == 'orderDateEnd') {
                $sqlWhere .= ' and `order_date` <= '.$this->escape($fv);
            } else if ($fn == 'validDate') {
                $sqlWhere .= ' and valid_start <= '.$this->escape($fv).' and valid_end >= '.$this->escape($fv);
            } else {
                $w = explode(',',$fv);
                for ($i=0; $i<count($w); $i++) {
                    $w[$i] = $this->escape($w[$i]);
                }
                $fv = implode(',',$w);
                $sqlWhere .= ' and `'.$fn.'` in ('.mb_strtolower($fv).')';
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
     * count és add adat tárolása adatbázisba  (postmeta adat)
     * @param int $id   place.id
     * @param int $count
     * @param int $add
     * @return bool
     */
    public function savePlaceCountAdd(int $id, int $count, int $add):bool {
        global $wpdb;
        $old = $wpdb->get_results('select pl.*
        from '.$this->placeTable.' pl
        where pl.id='.$this->escape($id).'
        ');
        if (!$old) {
            return true;
        }
        if ($old[0]->count != '') {
            $result = $wpdb->query('update '.$wpdb->prefix.'postmeta
            set meta_value='.$this->escape($count).'
            where post_id='.$this->escape($id).' and meta_key="cmm_count"');
        } else {
            $result = $wpdb->query('insert into '.$wpdb->prefix.'postmeta (post_id, meta_key, meta_value)
           values ('.$this->escape($id).', "cmm_count", '.$this->escape($count).')
           ');
        }
        if ($old[0]->add != '') {
            $result = $wpdb->query('update '.$wpdb->prefix.'postmeta
            set meta_value='.$this->escape($add).'
            where post_id='.$this->escape($id).' and meta_key="cmm_add"');
        } else {
            $result = $wpdb->query('insert into '.$wpdb->prefix.'postmeta (post_id, meta_key, meta_value)
           values ('.$this->escape($id).', "cmm_add", '.$this->escape($add).')
           ');
        }
        return $result;
    }
    
    /**
     * UMS marker icon módosítása
     * @param string $title
     * @param int $icon
     * @param string $coverage
     */
    public function updateUmsMarker(string $title, int $icon, string $coverage) {
        global $wpdb;
        $res = $wpdb->get_results('select * 
        from '.$wpdb->prefix.'ums_markers 
        where title='.$this->escape(mb_strtolower($title)));
        if (count($res) > 0) {
            $description = $res[0]->description;
            $description = preg_replace('#<coverage.+</coverage>#',
                '',
                $description);
            $description .= '<coverage style="display:block; text-align:center">Szabad '.$coverage.'%</coverage>';
            $sql = 'update '.$wpdb->prefix.'ums_markers 
            set icon='.$icon.', description="'.str_replace('"','\"',$description).'" 
            where title='.$this->escape(mb_strtolower($title));
            $wpdb->query($sql);   
        }
    }
} // class
?>