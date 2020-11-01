<?php
	/**
	* coverage-mm base model. Use one table and ACF fields 
	* use "cmm_{modelName}" ACF group
	* every model is a descendant of this
	*/

	class Model {
	    /**
	     * controller aminek az adatait kezeli
	     * @var Controller
	     */
	    protected $controller;
	    /**
	     * model neve
	     * @var string
	     */
	    protected $modelName = 'model';
	    /**
	     * ACF mező objektumok
	     * @var boolean vagy array
	     */
	    protected $acfGroups = false;  // after readOrCreate run: array of  ACF groups [{id:#, params:"xxx", fields:[acfFieldObj,...]}]
	    /**
	     * ACF mezők nevei
	     * @var boolean vagy array
	     */
	    protected $acfFields = false;  // acf fieldek felsorolása
	    /**
	     * utolsó müvelet hibaszövege
	     * @var string
	     */
	    protected $lastError = '';
	    /**
	     * alap rekord (ACF mezők nélküli mindig szükséges mezők)
	     * @var boolean
	     */
	    protected $baseRecord = false; // base record, stored in cmm_{modelName} table
	    
		/**
		 * php object constructor
		 */ 
		function __construct($controller = false) {
		    $this->controller = $controller;
		    $this->baseRecord = new stdClass();
		    /*
		    $this->modelName = "modelName";  
		      
		    // define inited baseRecord
		    $this->baseRecord->id = 0;
		    // ......
		     */
		}  // construct
		
        /**
         * register post_type, create ACF group if not exists 
         */
		public function checkDatabase() {
		    // regiter new post type cmm_{modelName}
		    register_post_type( 'cmm_'.$this->modelName,
		        array(
		            'labels' => array(
		                'name' => __( $this->modelname, CMM ),
		                'singular_name' => __( $this->modelName, CMM )
		            ),
		            'public' => true,
		            'has_archive' => true,
		            'rewrite' => array('slug' => $this->modelName),
		            'show_in_rest' => true,
		            
		        )
		    );
		    $this->readOrCreateAcfFields();
		} // checkDatabase
	
		/**
		 * copy virtual record to baseRecord
		 * @param virtualRecord $record
		 * @param BaseRecord $target
		 */
		public function copy($record, &$target) {
		    foreach ($target as $fn => $fv) {
		        if (isset($record->$fn)) {
		            $target->$fn = $record->$fn;
		        }
		    }
		} // copy
		
		/**
		 * get last action errorMsg. if OK result = '';
		 * @return string
		 */
		public function getErrorMsg(): string {
		    return $this->lastError;
		} // getErrorMsg
		
		/**
		 * init virtualRecord for insert
		 * @return object
		 */
		public function init() {
		  $this->readOrCreateAcfFields();
		  $result = new stdClass();
	      foreach ($this->baseRecord as $fn => $fv) {
                $result->$fn = $fv;		      
		  }
		  foreach ($this->acfGroups as $acfGroup) {
		      foreach ($acfGroup->fields as $field) {
		          $fieldName = $field->name;
		          if (isset($field->value)) {
		              if (is_string($field->value)) {
		                  $result->$fieldName = $field->value;
		              } else if (is_numeric($field->value)) {
		                  $result->$fieldName = $field->value;
		              } else if (is_bool($field->value)) {
		                  $result->$fieldName = $field->value;
		              } else {
		                  $result->$fieldName = '';
		              }
		          } else {
		              $result->$fieldName = '';
		          }
		      }
		  }
		  return $result;
		} // init
		
		/**
		 * insert new record (base fields and AACF fields)
		 * @param virtualRecord $record (base fields and AACF fields)
		 * @return id inserted id
		 */
		public function insert(&$record): int {
		    $this->readOrCreateAcfFields();
		    global $wpdb, $cmm;
		    $result = 0;
		    // insert baseRecord
		    $rec = $this->recordToBaseArray($record);
		    unset($rec['id']);
		    $wpdb->insert($wpdb->prefix.'cmm_'.$this->modelName, $rec);
		    $this->lastError = $wpdb->last_error;
		    $result = $wpdb->insert_id;
            $record->id = $result;
            
            // insert to meta table (ACF fields)
            if ($this->lastError == '') {
                foreach ($this->acfFields as $acfField) {
                    if (($this->lastError == '') & (isset($record->$acfField))) {
                        $rec = ["parent_id" => $result,
                               "name" => $acfField,
                               "value" => $record->$acfField];
                        $wpdb->insert($wpdb->prefix.'cmm_'.$this->modelName.'meta', $rec);
                        $this->lastError = $wpdb->last_error;
                    }
                }
            }
            if ($this->lastError != '') {
                $cmm->errorExit($this->lastError);
            }
            return $result;
		} // insert
		
		/**
		 * update record (base fields and ACF fields)
		 * (if ACF fields no exist then insert)
		 * @param virtualRecord $record (base fields and AACF fields)
		 * @return bool
		 */
		public function update($record): bool {
		    $this->readOrCreateAcfFields();
		    global $wpdb, $cmm;
		    $this->lastError = '';
		    // update baseRecord
		    $rec = $this->recordToBaseArray($record);
		    $wpdb->update($wpdb->prefix.'cmm_'.$this->modelName, $rec, ["id" => $record->id]);
		    $this->lastError = $wpdb->last_error;
		    
		    // update or insert to meta table (ACF fields)
		    if ($this->lastError == '') {
		        foreach ($this->acfFields as $acfField) {
		            if (($this->lastError == '') & (isset($record->$acfField))) {
		                $rec = $wpdb->get_row($wpdb->prefix.'cmm_'.$this->modelName.'meta',
		                    ["parent_id" => $record->id, "name" => $acfField]);
		                if ($rec) {
		                    // exists; update
    		                $rec = ["parent_id" => $record->id,
    		                    "name" => $acfField,
    		                    "value" => $record->$acfField];
    		                $wpdb->update($wpdb->prefix.'cmm_'.$this->modelName.'meta', 
    		                    $rec, 
    		                    ["parent_id" => $record->id, "name" => $acfField]);
		                } else {
		                    // not exists; insert
		                    $rec = ["parent_id" => $result,
		                        "name" => $acfField,
		                        "value" => $record->$acfField];
		                    $wpdb->insert($wpdb->prefix.'cmm_'.$this->modelName.'meta', $rec);
		                }
		                $this->lastError = $wpdb->last_error;
		            }
		        }
		    }
		    if ($this->lastError != '') {
		        $cmm->errorExit($this->lastError);
		    }
		    return ($this->lastError == '');
		} // update
		
		
        /**
         * delete record from database
         * @param int $id
         * @return bool
         */
		public function delete(int $id): bool {
		    $this->readOrCreateAcfFields();
		    $this->lastError = '';
		    global $wpdb, $cmm; 
		    $wpdb->delete($wpdb->prefix.$this->modelName,["id" => $id]);
		    $this->lastError = $wpdb->last_error;
		    if ($this->lastError == '') {
		        $wpdb->delete($wpdb->prefix.$this->modelName.'meta',["parent_id" => $id]);
		        $this->lastError = $wpdb->last_error;
		    }
		    return ($this->lastError == '');
		} // delete
		
		/**
		 * read virtual record from database by id
		 * @param int $id
		 * @return virtualRecord
		 */
		public function read(int $id) {
		    global $wpdb;
		    $this->readOrCreateAcfFields();
		    $result = new stdClass();
		    $result = $wpdb->get_results('select r.* from '.$this->getSelectFrom().' r where r.id='.$id);
		    $this->lastError = $wpdb->last_error;
		    if ($this->lastError != '') {
		        $cmm->errorExit($this->lastError);
		    }
		    return $result;
		} // read
		
		/**
		 * return sql subselect or table name for select sql statements
		 * @return string
		 */
		public function getSelectFrom(): string {
		    $this->readOrCreateAcfFields();
		    $result = '';
		    global $wpdb,$wpdb;
		    if (count($this->acfFields) == 0) {
		        $result = $wpdb->prefix.'cmm_'.$this->modelName;
		    } else {
		        $result = '(select t.*,';
		        for ($i = 0; $i < count($this->acfFields); $i++) {
		            $result .= 'm'.$i.'.value '.$this->acfFields[$i];
		        }
		        $result .= ' from '.$wpdb->prefix.'cmm_'.$this.modelName.' t';
		        for ($i = 0; $i < count($this->acfFields); $i++) {
		            $result .= ' left outer join '.$wpdb->prefix.'cmm_'.$this->modelName.'meta m'.$i;
		            $result .= ' on parent_id = t.id and name="'.$this->acfFields[$i].'"';
		        }
		        $result .= ')';
		    }
		    return $result;
		} // getSelectForm
		
		/**
		 * copy virtuelRecords' base fields into baseRecord array
		 * @param virtualRecord $record
		 * @return array
		 */
		protected function recordToBaseArray($record): array {
		    $rec = [];
		    foreach ($this->baseRecord as $fn => $fv) {
		        if (isset($record->$fn)) {
		            $rec[$fn] = $record->$fn;
		        } else {
		            $rec[$fn] = $fv;
		        }
		    }
		    return $rec;
		} // recordToBaseArray
		
		
		/**
		 * read connected ACF fieldgroups if exists, create if not exists
		 */
		protected function readOrCreateAcfFields() {
		    global $wpdb, $cmm;
		    if (is_array($this->acfGroups)) {
		        // alredy loaded
		        return;
		    }
		    $this->acfGroups = [];
		    $this->acfFields = [];
		    $res = $wpdb->get_results('select * from '.$wpdb->prefix.'posts
            where post_status = "publish" and post_type = "acf-field-group" and post_content like "%cmm_'.$this->modelName.'%"');
		    if (count($res) > 0) {
    		    foreach ($res as $res1) {
    		        $acfGroup = new stdClass();
    		        $acfGroup->id = $res1->ID;
    		        $acfGroup->params = $res1->post_content;
    		        $acfGroup->fields = [];
    		        $res2 = $wpdb->get_results('select * from '.$wpdb->prefix.'posts
                    where post_parent = '.$res1->ID.' and post_type = "acf-field" and post_status = "publish"');
    		        foreach ($res2 as $res3) {
    		            $acfGroup->fields[] = get_field_object($res3->post_name);
    		            if (!in_array($res3->post_name , $this->acfFields)) {
    		                $this->acfFields[] = $res3->post_name;
    		            }
    		        }
    		        $this->acfGroups[] = $acfGroup;
    		    }
		    } else {
		        $acfGroup = new stdClass();
		        $s = 'cmm_'.$this->modelName;
		        $content = 'a:7:{s:8:"location";a:1:{i:0;a:1:{i:0;a:3:{s:5:"param";s:9:"post_type";s:8:"operator";s:2:"==";s:5:"value";s:4:"area";}}}s:8:"position";s:6:"normal";s:5:"style";s:7:"default";s:15:"label_placement";s:3:"top";s:21:"instruction_placement";s:5:"label";s:14:"hide_on_screen";s:0:"";s:11:"description";s:0:"";}';
		        $content = str_replace('"value";s:4:"area"','"value";s:'.strlen($s).':"'.$s.'"',$content);
		        $newPost = array(
		            'post_title'     => 'cmm_'.$this->modelName,
		            'post_excerpt'   => sanitize_title($this->modelName),
		            'post_name'      => 'group_' . uniqid(),
		            'post_date'      => date( 'Y-m-d H:i:s' ),
		            'comment_status' => 'closed',
		            'post_status'    => 'publish',
		            'post_content'   => $content,
		            'post_type'      => 'acf-field-group',
		        );
		        wp_insert_post($newPost);
		        $acfGroup->params  = $content;
		        $acfGroup->fields = [];
		        $this->acfGroups[] = $acfGroup;
		    }
		} // readOrCreateAcfFields
		
		
		/**
		 * get ACF groups object array
		 * @return array
		 */
		public function getAcfGroups(): array {
		    $this->readOrCreateAcfFields();
		    return $this->acfGroups;
		} // getAcfGroups
		
	} // Model class
?>