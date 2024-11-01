<?php
/*
 * Plugin Name:    Remove tabs and fields from WooCommerce
 * Description:    Remove extra tabs and fields from WooCommerce (i.e. Shipping, SKU, Addresses and more...), go to WooCommerce > Settings > Remove Tabs & Fields
 * Text Domain:    wc-remove-tabs-and-fields
 * Domain Path:    /languages
 * Version:        1.72
 * WordPress URI:  https://wordpress.org/plugins/wc-remove-tabs-and-fields/
 * Plugin URI:     https://puvox.software/software/wordpress-plugins/?plugin=wc-remove-tabs-and-fields
 * Contributors:   puvoxsoftware,ttodua
 * Author:         Puvox.software
 * Author URI:     https://puvox.software/
 * Donate Link:    https://paypal.me/Puvox
 * License:        GPL-3.0
 * License URI:    https://www.gnu.org/licenses/gpl-3.0.html
 
 * @copyright:     Puvox.software
*/


namespace RemoveTabsAndFieldsFromWooCommerce
{
	if (!defined('ABSPATH')) exit;
	require_once( __DIR__."/library.php" );
	require_once( __DIR__."/library_wp.php" );
	
	class PluginClass extends \Puvox\wp_plugin
	{
  
	  public function declare_settings()
	  {
		  $this->initial_static_options	= 
		  [
			  'has_pro_version'        => 0, 
			  'show_opts'              => 'submodule', 
			  'custom_opts_page'=>'admin.php?page=wc-settings&tab=rtf_wc',//admin_url()
			  'show_rating_message'    => true, 
			  'show_donation_footer'   => true, 
			  'show_donation_popup'    => true, 
			  'menu_pages'             => [
				  'first' =>[
					  'title'           => 'Remove WooCommerce fields', 
					  'default_managed' => 'singlesite',            // network | singlesite
					  'required_role'   => 'install_plugins',
					  'level'           => 'submodule', 
					  'page_title'      => 'Remove tabs and fields from WooCommerce',
					  'tabs'            => [],
				  ],
			  ]
		  ];
		
		$this->initial_user_options	= 
		[
			// ###### for "others"
			'apply_to_admins'	=>false,
			//
			'force_virtual'		=>true,
			'force_downloadable'	=>true,
			'download_limit'	=>0,
			'download_expiry'	=>7,
			//
			'hide_checkboxes'	=>true,
			'hide_sale_price'	=>false,
			'hide_topbar_chooser'						=>false,
			'hide_left_tabs_column'						=>false,
			'hide_attributes_left_tab'					=>false,
			'hide_description_from_variable_product'	=>false,
			'hide_picture_from_variable_product'		=>false,
			'hide_stockstatus_from_variable_product'	=>false,
			'hide_defaultvalues_from_variable_product'	=>false,
			'auto_expand_variable_product'				=>false,
			'hide_expand_close_all'						=>false,
		]; 
	}

	public function __construct_my()
	{ 
		$this->sName	= 'rtf_wc';
		$this->optName	= 'rtf_wc__wc_settingstab';
		
		// add our "tab" in WC Settings Tabs array
        add_filter( 'woocommerce_settings_tabs_array',			[$this, 'add_settings_tab'  ], 50 );

        // create new WC hooks for our "tab"
        add_action( 'woocommerce_settings_'.$this->sName,		[$this, 'settings_tab'      ]   );   // add_action( 'woocommerce_settings_tabs_' ...  is deprecated
        add_action( 'woocommerce_update_options_'.$this->sName,	[$this, 'update_settings'   ]   );

		// remove fields from product editor page
		add_filter('woocommerce_product_data_tabs',	[$this, 'remove_product_data_tabs'], 95);
		//remove product types (i.e. variable, simple, grouped, etc...)
		add_filter( 'product_type_selector', 		[$this, 'remove_product_types'] , 95);
		//remove virtual & downloadable
		add_filter( 'product_type_options', 		[$this, 'autocheck_virtual_downloadable'] , 95);

		// from front-end
		add_filter('woocommerce_checkout_fields',	[$this, 'remove_woo_checkout_fields'], 95); 

		// remove fields from checkeout & product front-end
		add_filter('woocommerce_product_tabs',		[$this, 'remove_product_tabs'], 95);  
		//
		add_filter('init',							[$this, 'remove_some_extra'], 95);

		//returns false for filters
        $this->check_if_enabled();

		//$this->load_pro();
		//scripts
		add_action( 'woocommerce_settings_' . $this->sName, [$this, 'output_scripts_in_admin'], 444 ); 
		add_action( 'woocommerce_settings_' . $this->sName, [$this, 'output_options_cust'], 444 ); 
		
		
		//
		add_action('init', [$this, 'execFuncs_']);
	}

	// ============================================================================================================== //
	// ============================================================================================================== //
	
	
	
	private function skipDisabling(){
		return current_user_can('manage_options');
	}
	
	
	
	//
	public $items_in_product_data_tabs		= ['general'=>'', 'inventory'=>'', 'shipping'=>'', 'linked_product'=>'', 'attribute'=>'', 'variations'=>'', 'advanced'=>'', 'marketplace-suggestions'=>''];
	public $items_in_product_frontend_tabs	= ['description'=>'', 'additional_information'=>'', 'reviews'=>'' ];
	//there are two different filters for these
	public $fields_in_product_data_tabs		= ['sku'=>'', 'dimensions'=>'', 'weight'=>''];
	public $fields_filter_in_products		= ['tax'=>'', 'shipping'=>''];
	//
	public $types_in_product_data_tabs		= ['simple' => 'Simple product', 'grouped' => 'Grouped product', 'external' => 'External/Affiliate product', 'variable' => 'Variable product'];
	public $types_in_product_type			= ['virtual'=>'', 'downloadable'=>''];
	
	//
	public $fields_in_product_checkeout= [
		'billing' => [
			'billing_first_name',
			'billing_last_name',
			'billing_company',
			'billing_country',
			'billing_address_1', 
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_phone',
			'billing_email',
		],
		'shipping' => [
			'shipping_first_name',
			'shipping_last_name',
			'shipping_company',
			'shipping_country',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_city',
			'shipping_state',
			'shipping_postcode'
		],
		'order' => ['order_comments']
	];
	//
	private $some_extra= [ 
		'sold_individually' => 'Sold individually'
	];
	
	public function remove_product_data_tabs ($tabs) 
	{
		// if (!current_user_can('wc-inventory')) {  // replace role ID with your own   //return $tabs; 

		foreach($tabs as $tab=>$tab_data)
		{
			foreach($this->items_in_product_data_tabs as $key=>$value)
			{
				$opt=get_option($this->optName.'_1_'.$key);
				if ($key==$tab)
				{
					if ($opt=='-1')
					{
						unset($tabs[$tab]);
					}
				}
			}
		}

		return $tabs;
	}

    public function check_if_enabled() {
        foreach($this->fields_filter_in_products as $key=>$value){
			$opt=get_option($this->optName.'_1_'.$key);
            if($opt=='-1')
            {
            	add_filter( 'wc_'.$key.'_enabled',    '__return_false');  // '__return_false'
            }
		}
		
        foreach($this->fields_in_product_data_tabs as $key=>$value){
			$opt=get_option($this->optName.'_2_'.$key);
            if($opt=='-1')
            {
               add_filter( 'wc_product_'.$key.'_enabled',    '__return_false');  // '__return_false'
            }
		} 
	}


	public function remove_product_types( $types )
	{
        foreach($this->types_in_product_data_tabs as $key=>$value){
			$opt=get_option($this->optName.'_3_'.$key);
            if($opt=='-1')
            {
				unset($types[$key]);
            }
        }
		return $types;
	}
	
	public function autocheck_virtual_downloadable($arr)
	{
		foreach($this->types_in_product_type as $key=>$value){
			$opt=get_option($this->optName.'_3_'.$key);
			if($opt=='-1')
			{
				unset($arr[$key]);
			}
		}
		return $arr;
	}

	
	
	public function remove_woo_checkout_fields( $fields ) {

		foreach($this->fields_in_product_checkeout as $key=>$value)
		{
			foreach($value as $key1=>$value1)
			{
				$opt=get_option($this->optName.'_5_'.$value1);
				if($opt=='-1')
				{
					unset($fields[$key][$value1]);
				}
			}
		}

		return $fields;
	}


	public function remove_product_tabs ($tabs) 
	{ 
        foreach($this->items_in_product_frontend_tabs as $key=>$value){
			$opt=get_option($this->optName.'_4_'.$key);
            if($opt=='-1')
            {
				unset($tabs[$key]);
            }
        }
		return $tabs;
	}

	public function remove_some_extra( ) 
	{
		foreach($this->some_extra as $key=>$value)
		{
			$opt=get_option($this->optName.'_2_'.$key);
			if($opt=='-1')
			{
				if($key=='sold_individually')
				{
					add_action('woocommerce_product_options_sold_individually', function(){ ?><script>jQuery(function(){  jQuery("._sold_individually_field").css("display","none"); }); </script><?php } );
				}
			}
		} 
	}

 
 

	public function HintDescriptionPart($image)
	{
		return '<div class="'.$this->sName.'_eachblock">Typically, WooCommerce has such output: <br/><img src="'.$this->helpers->baseURL.'/assets/'.$image.'.png" /><br/> So, you can customize them</div>';
	}
	
	
	// back-end - Options page

    public function add_settings_tab( $settings_tabs ) {
		$settings_tabs[$this->sName] = __( "Tabs & Fields", 'wc-remove-tabs-and-fields' );
        return $settings_tabs;
    }

    
    public function settings_tab() {
        woocommerce_admin_fields( $this->get_settings() );
    }

    public function update_settings() {
		 woocommerce_update_options( $this->get_settings() );
    }

	public function get_settings() 
	{
		$settings=[];


		//  tab1
		$settings[] = [
			'name'      => __(  'Remove Tabs from product edit page', 'wc-remove-tabs-and-fields' ),
			'type'      => 'title',
			'desc'      => $this->HintDescriptionPart('tabs_on_editor'),
			'id'        => $this->optName.'_1'
		];

		foreach($this->items_in_product_data_tabs as $key=>$value)
		{
			$settings[] = 
			[
				'name' => __( 'Show '.strtoupper($key), 'wc-remove-tabs-and-fields' ),
				'type'    => 'select',

				'options' => [
					'1'       => __( 'Yes', 'woocommerce' ),
					'-1'        => __( 'No', 'woocommerce' ),
				],
				'desc' => __( "", 'wc-remove-tabs-and-fields' ),
				'id'   => $this->optName.'_1_'.$key 
			];
		}
		$settings[] = [
			'type' => 'sectionend',
			'id' => $this->optName.'_1'
		];
				


		//  tab2
		$settings[] = [
			'name'      => __(  'Remove fields from product edit page (from different tabs)', 'wc-remove-tabs-and-fields' ),
			'type'      => 'title',
			'desc'      => '',
			'id'        => $this->optName.'_2'
		];
		
		foreach($this->fields_in_product_data_tabs as $key=>$value)
		{
			$settings[] = 
			[
				'name' => __( 'Show '.strtoupper($key), 'wc-remove-tabs-and-fields' ),
				'type'    => 'select',

				'options' => [
					'1'       => __( 'Yes', 'woocommerce' ),
					'-1'        => __( 'No', 'woocommerce' ),
				],
				'desc' => __( 
						(
							($key=="sku" ? "This is under <b>Inventory</b> tab; If disabled, then there will show up simple dropdown for stock in & out (If <b>Inventory</b> is hidden, ignore this at all)." : 
							($key=="dimensions" || $key=="weight" ? "This is under <b>Shipping</b> tab (If <b>Shipping</b> is hidden, ignore this at all)." : 
							""))
						), 
					
					'wc-remove-tabs-and-fields' 
					),
				'id'   => $this->optName.'_2_'.$key 
			];
		}

		foreach($this->fields_filter_in_products as $key=>$value)
		{
			$settings[] = 
			[
				'name' => __( 'Show '.strtoupper($key), 'wc-remove-tabs-and-fields' ),
				'type'    => 'select',

				'options' => [
					'1'       => __( 'Yes', 'woocommerce' ),
					'-1'        => __( 'No', 'woocommerce' ),
				],
				'desc' => __( 
						(
							($key=="tax" ? 'This is under <b>General</b> tab, and is only shown, if you have checked "Enable taxes" checkbox in "Woocommerce &gt; General" settings page.' : 
							($key=="htyjyjyujyukyuk" ? "This is under <b>Shipping</b> tab (If <b>Shipping</b> is hidden, ignore this at all)." : 
							""))
						), 
					
					'wc-remove-tabs-and-fields' 
					),
				'id'   => $this->optName.'_2_'.$key 
			];
		} 
		



		foreach($this->some_extra as $key=>$value)
		{
			$settings[] = 
			[
				'name' => __( 'Show '.strtoupper($key), 'wc-remove-tabs-and-fields' ),
				'type'    => 'select',

				'options' => [
					'1'       => __( 'Yes', 'woocommerce' ),
					'-1'        => __( 'No', 'woocommerce' ),
				],
				'desc' => __( 
						(
							($key=="sold_individually" ? "This is under <b>Inventory</b> tab (If <b>Inventory</b> is hidden, ignore this at all)." : 
							($key=="xxxxxxxxxxxxxxx" ? "This is under <b>Shipping</b> tab (If <b>Shipping</b> is hidden, ignore this at all)." : 
							""))
						), 
					
					'wc-remove-tabs-and-fields' 
					),
				'id'   => $this->optName.'_2_'.$key 
			];
		}
		
		$settings[] = [
			'type' => 'sectionend',
			'id' => $this->optName.'_2'
		];



		//  tab3
		$settings[] = [
			'name'      => __(  'Remove specific Types from "Product Type" dropdown', 'wc-remove-tabs-and-fields' ),
			'type'      => 'title',
			'desc'      => $this->HintDescriptionPart('product_types'),
			'id'        => $this->optName.'_3'
		];
		
		foreach($this->types_in_product_data_tabs as $key=>$value)
		{
			$settings[] = 
			[
				'name' => __( 'Show '.strtoupper($key), 'wc-remove-tabs-and-fields' ),
				'type'    => 'select',

				'options' => [
					'1'       => __( 'Yes', 'woocommerce' ),
					'-1'        => __( 'No', 'woocommerce' ),
				],
				'desc' => __( "", 'wc-remove-tabs-and-fields' ),
				'id'   => $this->optName.'_3_'.$key 
			];
		}


		//$this->HintDescriptionPart('virtual-downloadable')
		foreach($this->types_in_product_type as $key=>$value)
		{
			$settings[] = 
			[
				'name' => __( 'Show '.strtoupper($key), 'wc-remove-tabs-and-fields' ),
				'type'    => 'select',

				'options' => [
					'1'       => __( 'Yes', 'woocommerce' ),
					'-1'        => __( 'No', 'woocommerce' ),
				],
				'desc' => __( 
						(
							"This is shown along the above Product-Types ."
						), 
					'wc-remove-tabs-and-fields' 
					),
				'id'   => $this->optName.'_3_'.$key 
			];
		}


		$settings[] = [
			'type' => 'sectionend',
			'id' => $this->optName.'_3'
		];
				



		//  tab4
		$settings[] = [
			'name'      => __(  'Remove tabs from Product front-End pages', 'wc-remove-tabs-and-fields' ),
			'type'      => 'title',
			'desc'      => '',
			'id'        => $this->optName.'_4'
		];
		
		foreach($this->items_in_product_frontend_tabs as $key=>$value)
		{
			$settings[] = 
			[
				'name' => __( 'Show '.strtoupper($key), 'wc-remove-tabs-and-fields' ),
				'type'    => 'select',

				'options' => [
					'1'       => __( 'Yes', 'woocommerce' ),
					'-1'        => __( 'No', 'woocommerce' ),
				],
				'desc' => __( "", 'wc-remove-tabs-and-fields' ),
				'id'   => $this->optName.'_4_'.$key 
			];
		}

		$settings[] = [
			'type' => 'sectionend',
			'id' => $this->optName.'_4'
		];
			



		//  tab5
		$settings[] = [
			'name'      => __(  'Remove fields from Product Checkout pages', 'wc-remove-tabs-and-fields' ),
			'type'      => 'title',
			'desc'      => $this->HintDescriptionPart('fields_on_checkout'),
			'id'        => $this->optName.'_5'
		];
		
		foreach($this->fields_in_product_checkeout as $key=>$value)
		{
			foreach($value as $key1=>$value1)
			{
				$settings[] = 
				[
					'name' => __( 'Show '.strtoupper($value1), 'wc-remove-tabs-and-fields' ),
					'type'    => 'select',

					'options' => [
						'1'       => __( 'Yes', 'woocommerce' ),
						'-1'      => __( 'No', 'woocommerce' ),
					],
					'desc' => __( "", 'wc-remove-tabs-and-fields' ),
					'id'   => $this->optName.'_5_'.$value1    //no need for :  $key.'-'.$value1 
				];
			}
		}

		$settings[] = [
			'type' => 'sectionend',
			'id' => $this->optName.'_5'
		];
 
		
		return apply_filters( $this->optName.'_wc_setttings_filter', $settings );
    }




	// other funcs
	public function execFuncs_()
	{
		if (!$this->opts['apply_to_admins'] && $this->skipDisabling()) return;
		
		// #############  Virtual & Downloadable ############# //
		
		//# for SIMPLE PRODUCT #
		if($this->opts['force_virtual'])
		{
			//selector default (when first time and when modification):
			add_filter('product_type_options', function($array){$array['virtual']['default']='yes'; return $array; } );
			add_filter('woocommerce_is_virtual', function($is_virtual, $object){ return true; }, 10, 2 );
			//hide option visually
			if($this->opts['hide_checkboxes']) 
				add_action('woocommerce_product_write_panel_tabs', function(){?><style>#woocommerce-product-data label[for="_virtual"]{display:none!important;}</style><?php } );
			
			//backend save:
			add_action('woocommerce_admin_process_product_object', function($product){ $product->update_meta_data('_virtual', true);  } );
		}
		if($this->opts['force_downloadable'])
		{
			//selector default (when first time and when modification):
			add_filter('product_type_options', function($array){$array['downloadable']['default']='yes'; return $array; } );
			add_filter('woocommerce_is_downloadable', function($is_virtual, $object){ return true; }, 10, 2 );
			//hide option visually
			if($this->opts['hide_checkboxes']) 
				add_action('woocommerce_product_write_panel_tabs', function(){?><style>#woocommerce-product-data label[for="_downloadable"]{display:none!important;}</style><?php } );
			
			//backend save:
			add_action('woocommerce_admin_process_product_object', function($product){ $product->update_meta_data('_downloadable', true);  } );
		}
		
		
		//JS way  ( el.click(); ) doesn't always work well. so, use PHP query to set values
		
		//# for VARIABLE product #
		if($this->opts['force_virtual'])
		{
			//selector default
			add_action( 'woocommerce_variation_options', function ($loop_idx, $variation_data, $variation) 	{ ?> <script>window.setTimeout( function(){ var el = document.querySelector('#variable_product_options input[name="variable_is_virtual[<?php echo $loop_idx; ?>]"]');  <?php if($this->opts['hide_checkboxes']) { ?> el.parentNode.style.display="none"; <?php } ?> }, 300); </script> <?php }, 10, 3 ); 
			add_filter('woocommerce_product_object_query',	function( $results, $args){
				foreach($results as $result_WC_Product_Variation)
				{
					$reflectionProperty = new \ReflectionProperty(get_class($result_WC_Product_Variation), 'data');
					$reflectionProperty->setAccessible(true);
					$val = $reflectionProperty->getValue($result_WC_Product_Variation);
					$val['virtual']		=true;
					$reflectionProperty->setValue($result_WC_Product_Variation, $val);
				}
				return $results;
			},10,2 );
				
				
			//backend save
			add_action( 'check_ajax_referer', function($action, $result) {  if ($action=='save-variations') {  if (empty($_POST['variable_post_id'])) {   for($i=0; $i<max( array_keys( wp_unslash( $_POST['variable_post_id'] ) ) )+1; $i++)  $_POST['variable_is_virtual'][(int)$i]=true;  }  }  }, 10, 2 ); 
			
		}
		if($this->opts['force_downloadable'])
		{
			//selector default
			add_action( 'woocommerce_variation_options', function ($loop_idx, $variation_data, $variation) 	{ ?> <script>window.setTimeout(  function(){ var el = document.querySelector('#variable_product_options input[name="variable_is_downloadable[<?php echo $loop_idx; ?>]"]');  <?php if($this->opts['hide_checkboxes']) { ?>el.parentNode.style.display="none"; <?php } ?> }, 300);</script> <?php }, 10, 3 ); 
			add_filter('woocommerce_product_object_query',	function( $results, $args){
				foreach($results as $result_WC_Product_Variation)
				{
					$reflectionProperty = new \ReflectionProperty(get_class($result_WC_Product_Variation), 'data');
					$reflectionProperty->setAccessible(true);
					$val = $reflectionProperty->getValue($result_WC_Product_Variation);
					$val['downloadable']=true;
					$reflectionProperty->setValue($result_WC_Product_Variation, $val);
				}
				return $results;
			},10,2 );
			
			//backend save
			add_action( 'check_ajax_referer', function($action, $result) {  if ($action=='save-variations') {  if (empty($_POST['variable_post_id'])) {   for($i=0; $i<max( array_keys( wp_unslash( $_POST['variable_post_id'] ) ) )+1; $i++)  $_POST['variable_is_downloadable'][(int)$i]=true;  }  }  }, 10, 2 );
		}
		
		// ###################################

				/*
				TODO if needed without JavaScript (no native approach found yet, due to ignorance of hooks by Woo): 
				//somewhere in the hell used, for getting variation
				//add_filter('woocommerce_available_variation', function($array){ $array['is_virtual']=true; $array['is_downloadable']=true; }, 10, 2);
				
				//each product is passed through either WC_Product_Simple or WC_Product_Variable class
				//add_filter('woocommerce_product_object_query',  function($results, $args ){}, 10, 2);
				*/
				/*
				add_filter( 'woocommerce_product_class', function ($res){ $func; return $res; } );
				//called after creating class, before saving. 
				add_action( 'woocommerce_admin_process_variation_object', function( $variation, $i ){ $variation->update_meta_data('_virtual', true); } , 40, 2);
				*/



		// ##### Download Expiry & limit #####
		// Simple product
		add_action( 'woocommerce_product_options_downloads', function (){
			?>
			<script>
				jQuery("#woocommerce-product-data #_download_limit").val(<?php echo $this->opts['download_limit'];?>);
				jQuery("#woocommerce-product-data #_download_expiry").val(<?php echo $this->opts['download_expiry'];?>);
			</script>
			<?php if($this->opts['hide_checkboxes']) { ?> <style> #woocommerce-product-data ._download_limit_field, #woocommerce-product-data ._download_expiry_field { display:none; }</style><?php } ?>
			<?php
		});
		
		// Variable product
		add_action( 'woocommerce_variation_options', function ($loop_idx, $variation_data, $variation) { ?> <script> 
		(function(){ 
			var el = document.querySelector('input[name="variable_download_limit[<?php echo $loop_idx; ?>]"]'); el.value="<?php echo $this->opts['download_limit'];?>"; <?php if($this->opts['hide_checkboxes']) { ?>el.parentNode.style.display="none"; <?php } ?>  
			var el = document.querySelector('input[name="variable_download_expiry[<?php echo $loop_idx; ?>]"]'); el.value="<?php echo $this->opts['download_expiry'];?>"; <?php if($this->opts['hide_checkboxes']) { ?>el.parentNode.style.display="none"; <?php } ?> 
		})(); 
		</script> <?php }, 10, 3 ); 
		// ###################################
		
		
		
		// ##### Sale price #####
		if($this->opts['hide_sale_price'])
		{
			// Simple product
			add_action( 'woocommerce_product_options_pricing', function (){ ?> <style>#woocommerce-product-data ._sale_price_field {display:none;}</style><?php } );
			
			// Variable product
			add_action( 'woocommerce_variation_options', function ($loop_idx){ ?> <style>#woocommerce-product-data .variable_sale_price<?php echo $loop_idx; ?>_field {display:none;}</style><?php } ); 
		}
		
		// ##### horizontal first main topbar #####
		if($this->opts['hide_topbar_chooser'])
		{
			add_action( 'woocommerce_product_write_panel_tabs', function (){ ?><style>#woocommerce-product-data > h2:first-child{display:none!important;}</style><?php } ); 
		}
		
		// ##### Left tab column #####
		if($this->opts['hide_left_tabs_column'])
		{
			add_action( 'woocommerce_product_write_panel_tabs', function (){ ?><style>#woocommerce-product-data ul.wc-tabs{display:none!important;}</style><?php } ); 
		}
	
		// ##### "Attributes" Left tab #####
		if($this->opts['hide_attributes_left_tab'])
		{
			add_action( 'woocommerce_product_write_panel_tabs', function (){ ?><style>#woocommerce-product-data .product_data_tabs .attribute_options{display:none!important;}</style><?php } ); 
		}
	
	
	
		// ********  variable products  ********* //
		// ##### "default values from" #####
		if($this->opts['hide_defaultvalues_from_variable_product'])
		{
			// Variable product
			add_action( 'woocommerce_variable_product_before_variations', function ($loop_idx){ ?> <style>#woocommerce-product-data .toolbar-variations-defaults {display:none!important;}</style><?php } ); 
		}
		
		// ##### "picture" #####
		if($this->opts['hide_picture_from_variable_product'])
		{
			// Variable product
			add_action( 'woocommerce_variation_options', function ($loop_idx){ ?> <style>#woocommerce-product-data .woocommerce_variable_attributes p.upload_image {display:none;}</style><?php } ); 
		}
		
		// ##### "Stock-status" #####
		if($this->opts['hide_stockstatus_from_variable_product'])
		{
			// Variable product
			add_action( 'woocommerce_variation_options', function ($loop_idx){ ?> <style>#woocommerce-product-data .variable_stock_status<?php echo $loop_idx; ?>_field {display:none;}</style><?php } ); 
		}
		
		// ##### "Description" #####
		if($this->opts['hide_description_from_variable_product'])
		{
			// Variable product
			add_action( 'woocommerce_variation_options', function ($loop_idx){ ?> <style>#woocommerce-product-data .variable_description<?php echo $loop_idx; ?>_field {display:none;}</style><?php } ); 
		}
		
		
		// ##### auto-expand #####
		if($this->opts['auto_expand_variable_product'])
		{
			// Variable product
			add_action( 'woocommerce_variation_options', function ($loop_idx){ ?> <script id="wvo_script1_<?php echo $loop_idx;?>">jQuery('#wvo_script1_<?php echo $loop_idx;?>').closest(".woocommerce_variations.wc-metaboxes").find("h3").first().click();</script><?php } ); 
		} 
		
		// ##### expand/close all #####
		if($this->opts['hide_expand_close_all'])
		{
			// Variable product
			add_action( 'woocommerce_variable_product_before_variations', function (){ ?><style>#woocommerce-product-data #variable_product_options .variations-pagenav{display:none!important;}</style><?php } ); 
		}
	}
	
    public function output_scripts_in_admin() {
		?>
		<script>
			jQuery(document).ready(function() {
				set_binder();
			});

			function set_binder()
			{
				jQuery("select[id*='<?php echo $this->optName; ?>']").each(function() {
					jQuery(this).on("change", change_colors_handler);
					change_colors(this);
				}); 
			}

			function change_colors_handler(event)
			{
				change_colors(event.target, true);
			}

			function change_colors(el, onchange_trigger)
			{
				var onchange_trigger= onchange_trigger || false;
				var thisEl = jQuery(el);
				var disabled = thisEl.val()=="-1";
				var color= disabled ? "#f7b2b2" : "#b2f7b2";
				thisEl.css("background", color);
				if( onchange_trigger && thisEl.attr("id").indexOf("billing_email") >-1  && disabled)
					alert("It might be better to have BILLING_EMAIL enabled, but as you wish...");
			}
		</script>
 
		<script>
			//add class
			jQuery(document).ready(function() {
				jQuery('.woocommerce > form#mainform').addClass("<?php echo $this->sName;?>");
				pro_field ( jQuery("#<?php echo $this->optName; ?>_5-description").next() );
			}); 
		</script>
		<style>
		.<?php echo $this->sName;?>_eachblock{display:flex; justify-content:center; flex-direction:column; align-items:center; }
		form.<?php echo $this->sName;?> h2{display:flex; justify-content:center; }
		</style>


		<script>window.onload=function () {
			//jQuery(".woocommerce #mainform").wrapInner("<?php echo $this->myplugin_class;?>");
		};
		</script>
		<?php
    }



	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	public function output_options_cust()
	{ 

		//if form updated
		if( isset($_POST["_wpnonce"]) && check_admin_referer('woocommerce-settings') ) 
		{ 
			$this->opts['apply_to_admins']						= !empty($_POST[ $this->plugin_slug ]['apply_to_admins']);
			
			$this->opts['force_virtual']						= !empty($_POST[ $this->plugin_slug ]['force_virtual']) ; 
			$this->opts['force_downloadable']					= !empty($_POST[ $this->plugin_slug ]['force_downloadable']);  
			$this->opts['download_limit']						= sanitize_key($_POST[ $this->plugin_slug ]['download_limit']); 
			$this->opts['download_expiry']						= sanitize_key($_POST[ $this->plugin_slug ]['download_expiry']); 
			$this->opts['hide_checkboxes']						= !empty($_POST[ $this->plugin_slug ]['hide_checkboxes']) ; 
			$this->opts['hide_sale_price']						= !empty($_POST[ $this->plugin_slug ]['hide_sale_price']);
			$this->opts['hide_left_tabs_column']				= !empty($_POST[ $this->plugin_slug ]['hide_left_tabs_column']);
			$this->opts['hide_topbar_chooser']					= !empty($_POST[ $this->plugin_slug ]['hide_topbar_chooser']);
			$this->opts['hide_attributes_left_tab']				= !empty($_POST[ $this->plugin_slug ]['hide_attributes_left_tab']);
			$this->opts['hide_defaultvalues_from_variable_product']= !empty($_POST[ $this->plugin_slug ]['hide_defaultvalues_from_variable_product']);
			$this->opts['hide_picture_from_variable_product']	 = !empty($_POST[ $this->plugin_slug ]['hide_picture_from_variable_product']);
			$this->opts['hide_stockstatus_from_variable_product']= !empty($_POST[ $this->plugin_slug ]['hide_stockstatus_from_variable_product']);
			$this->opts['hide_description_from_variable_product']= !empty($_POST[ $this->plugin_slug ]['hide_description_from_variable_product']);
			$this->opts['auto_expand_variable_product']			 = !empty($_POST[ $this->plugin_slug ]['auto_expand_variable_product']);
			$this->opts['hide_expand_close_all']			 	 = !empty($_POST[ $this->plugin_slug ]['hide_expand_close_all']);
			$this->update_opts(); 
		}
		?>
		
		<h2><?php _e('Other extra options for visually "hidings" and setting default values (the above correspodent option should be set to "yes" in order to use the "hide/default value" for that option)');?></h2>
		<div class="postbox">
		  <table class="form-table">

			<tr class="def">
				<th scope="row">
					<?php _e('Force to be virtual only'); ?>
				</th>
				<td>
					<p>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[force_virtual]" type="radio" value="0" <?php checked(!$this->opts['force_virtual']); ?>><?php _e( 'No' );?>
						</label>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[force_virtual]" type="radio" value="1" <?php checked( $this->opts['force_virtual']); ?>><?php _e( 'Yes' );?>
						</label>
					</p>
				</td>
			</tr>
			
			<tr class="def">
				<th scope="row">
					<?php _e('Force to be downloadable only'); ?>
				</th>
				<td>
					<p>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[force_downloadable]" type="radio" value="0" <?php checked(!$this->opts['force_downloadable']); ?>><?php _e( 'No' );?>
						</label>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[force_downloadable]" type="radio" value="1" <?php checked( $this->opts['force_downloadable']); ?>><?php _e( 'Yes' );?>
						</label>
					</p>
				</td>
			</tr>
			
			<tr class="def">
				<th scope="row">
					<?php _e('Hide "Sale price" field '); ?>
				</th>
				<td>
					<p>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_sale_price]" type="radio" value="0" <?php checked(!$this->opts['hide_sale_price']); ?>><?php _e( 'No' );?>
						</label>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_sale_price]" type="radio" value="1" <?php checked( $this->opts['hide_sale_price']); ?>><?php _e( 'Yes' );?>
						</label>
					</p>
				</td>
			</tr>
			

			<tr class="def">
				<th scope="row">
					<?php _e('Force "Download limit" to:'); ?>
				</th>
				<td>
					<p>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[download_limit]" type="text" class="small-text" value="<?php echo $this->opts['download_limit'];?>" />
						</label>
					</p>
				</td>
			</tr>

			<tr class="def">
				<th scope="row">
					<?php _e('Force "Download expiry" to:'); ?>
				</th>
				<td>
					<p>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[download_expiry]" type="text" class="small-text" value="<?php echo $this->opts['download_expiry'];?>" />
						</label>
					</p>
				</td>
			</tr>
			<tr class="def">
				<th scope="row">
					<?php _e('Hide top toolbar (where Product Type dropdown is displayed) '); ?>
				</th>
				<td>
					<p>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_topbar_chooser]" type="radio" value="0" <?php checked(!$this->opts['hide_topbar_chooser']); ?>><?php _e( 'No' );?>
						</label>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_topbar_chooser]" type="radio" value="1" <?php checked( $this->opts['hide_topbar_chooser']); ?>><?php _e( 'Yes' );?>
						</label>
					</p>
				</td>
			</tr>
			<tr class="def">
				<th scope="row">
					<?php _e('Hide left column (where General, Attributes, etc..  is displayed)'); ?>
				</th>
				<td>
					<p>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_left_tabs_column]" type="radio" value="0" <?php checked(!$this->opts['hide_left_tabs_column']); ?>><?php _e( 'No' );?>
						</label>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_left_tabs_column]" type="radio" value="1" <?php checked( $this->opts['hide_left_tabs_column']); ?>><?php _e( 'Yes' );?>
						</label>
					</p>
				</td>
			</tr>
			<tr class="def">
				<th scope="row">
					<?php _e('Hide left "Attributes" tab '); ?>
				</th>
				<td>
					<p>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_attributes_left_tab]" type="radio" value="0" <?php checked(!$this->opts['hide_attributes_left_tab']); ?>><?php _e( 'No' );?>
						</label>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_attributes_left_tab]" type="radio" value="1" <?php checked( $this->opts['hide_attributes_left_tab']); ?>><?php _e( 'Yes' );?>
						</label>
					</p>
				</td>
			</tr>
			
			
			
			<tr class="def">
				<th colspan=2>
					<h2><?php _e('Visibility'); ?></h2>
				</th>
			</tr>
			<tr class="def">
				<th scope="row">
					<?php _e('Also, hide the checkboxes'); ?>
				</th>
				<td>
					<p>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_checkboxes]" type="radio" value="0" <?php checked(!$this->opts['hide_checkboxes']); ?>><?php _e( 'No' );?>
						</label>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_checkboxes]" type="radio" value="1" <?php checked( $this->opts['hide_checkboxes']); ?>><?php _e( 'Yes' );?>
						</label>
					</p>
				</td>
			</tr>
			<tr class="def">
				<th scope="row">
					<?php _e('Apply these changes for Administrator privilegged users too'); ?>
				</th>
				<td>
					<p>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[apply_to_admins]" type="radio" value="0" <?php checked(!$this->opts['apply_to_admins']); ?>><?php _e( 'No' );?>
						</label>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[apply_to_admins]" type="radio" value="1" <?php checked( $this->opts['apply_to_admins']); ?>><?php _e( 'Yes' );?>
						</label>
					</p>
				</td>
			</tr>

			<tr class="def">
				<th colspan=2>
					<h2><?php _e('"Variable Product" tab'); ?></h2>
				</th>
			</tr>
			<tr class="def">
				<th scope="row">
					<?php _e('Auto-expand content in variable-tab'); ?>
				</th>
				<td>
					<p>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[auto_expand_variable_product]" type="radio" value="0" <?php checked(!$this->opts['auto_expand_variable_product']); ?>><?php _e( 'No' );?>
						</label>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[auto_expand_variable_product]" type="radio" value="1" <?php checked( $this->opts['auto_expand_variable_product']); ?>><?php _e( 'Yes' );?>
						</label>
					</p>
				</td>
			</tr>
			<tr class="def">
				<th scope="row">
					<?php _e('Hide "expand/close all"'); ?>
				</th>
				<td>
					<p>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_expand_close_all]" type="radio" value="0" <?php checked(!$this->opts['hide_expand_close_all']); ?>><?php _e( 'No' );?>
						</label>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_expand_close_all]" type="radio" value="1" <?php checked( $this->opts['hide_expand_close_all']); ?>><?php _e( 'Yes' );?>
						</label>
					</p>
				</td>
			</tr>
			<tr class="def">
				<th scope="row">
					<?php _e('Hide "Default values from" '); ?>
				</th>
				<td>
					<p>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_defaultvalues_from_variable_product]" type="radio" value="0" <?php checked(!$this->opts['hide_defaultvalues_from_variable_product']); ?>><?php _e( 'No' );?>
						</label>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_defaultvalues_from_variable_product]" type="radio" value="1" <?php checked( $this->opts['hide_defaultvalues_from_variable_product']); ?>><?php _e( 'Yes' );?>
						</label>
					</p>
				</td>
			</tr>
			<tr class="def">
				<th scope="row">
					<?php _e('Hide "Picture"'); ?>
				</th>
				<td>
					<p>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_picture_from_variable_product]" type="radio" value="0" <?php checked(!$this->opts['hide_picture_from_variable_product']); ?>><?php _e( 'No' );?>
						</label>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_picture_from_variable_product]" type="radio" value="1" <?php checked( $this->opts['hide_picture_from_variable_product']); ?>><?php _e( 'Yes' );?>
						</label>
					</p>
				</td>
			</tr>
			<tr class="def">
				<th scope="row">
					<?php _e('Hide "stock status"'); ?>
				</th>
				<td>
					<p>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_stockstatus_from_variable_product]" type="radio" value="0" <?php checked(!$this->opts['hide_stockstatus_from_variable_product']); ?>><?php _e( 'No' );?>
						</label>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_stockstatus_from_variable_product]" type="radio" value="1" <?php checked( $this->opts['hide_stockstatus_from_variable_product']); ?>><?php _e( 'Yes' );?>
						</label>
					</p>
				</td>
			</tr>
			<tr class="def">
				<th scope="row">
					<?php _e('Hide "Description"'); ?>
				</th>
				<td>
					<p>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_description_from_variable_product]" type="radio" value="0" <?php checked(!$this->opts['hide_description_from_variable_product']); ?>><?php _e( 'No' );?>
						</label>
						<label>
							<input name="<?php echo $this->plugin_slug;?>[hide_description_from_variable_product]" type="radio" value="1" <?php checked( $this->opts['hide_description_from_variable_product']); ?>><?php _e( 'Yes' );?>
						</label>
					</p>
				</td>
			</tr>
			
			 
			
		  </table>
		</div>
		
		<?php //submit_button( false, 'button-primary', '', true, $attrib= ['id'=>'mainsubmit-button'] ); ?>
		<?php //wp_nonce_field( "nonce_".$this->plugin_slug); ?>
	
		<?php
		$this->end_styles(true);
	} 
	
	
 


  } // End Of Class

  $GLOBALS[__NAMESPACE__] = new PluginClass();

} // End Of NameSpace




?>