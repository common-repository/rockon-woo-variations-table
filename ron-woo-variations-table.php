<?php
/**
* Plugin Name: RockOn Woo Variations Table
* Plugin URI: https://wordpress.org/plugins/rockon-woo-variations-table/
* Description: Plugin to turn Woocommerce normal variations select menus to table - grid. Short-code [rovartable id='1,2,4']
* Version: 6.0
* Author: Vikas Sharma
* Author URI: https://profiles.wordpress.org/devikas301
* License: GPL2
* Text Domain: ronvartable
*/

// Make sure we don't expose any info if called directly

if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define('RWVT_PATH', plugin_dir_path(__FILE__));
define('RWVT_LINK', plugin_dir_url(__FILE__));
define('RWVT_PLUGIN_NAME', plugin_basename(__FILE__));

function ron_woo_variations_table( $atts ) {	
    $v = shortcode_atts( array(
						'id'             => '',
						'thead'          => '',
						'disabled'       => '',
						'categories_exc' => '',    
						), $atts );

    return (ron_woo_variable_add_to_cart($v));
}
add_shortcode( 'rovartable', 'ron_woo_variations_table' );


function ron_woo_find_matching_product_variation( $product, $attributes ) {
    foreach( $attributes as $key => $value ) {
        if( strpos( $key, 'attribute_' ) === 0 ) {
            continue;
        }
 
        unset( $attributes[ $key ] );
        $attributes[ sprintf( 'attribute_%s', $key ) ] = $value;
    }

    if( class_exists('WC_Data_Store') ) { 
        $data_store = WC_Data_Store::load( 'product' );    
		return $data_store->find_matching_product_variation( $product, $attributes );    
    } else {	
        return $product->get_matching_variation( $attributes ); 
    } 
	
}


add_action("wp_ajax_check_choosed_var", "check_choosed_var");
add_action("wp_ajax_nopriv_check_choosed_var", "check_choosed_var");
function check_choosed_var(){
	
  global $product;

   $ro_aname = explode(',', $_REQUEST['att_name']);
   $ro_avalue = explode(',', $_REQUEST['att_value']);
   $att = array();
   $ro_addcart_slug = '';
  
  for($a = 0; $a < sizeof($ro_aname); $a++){
	$att[$ro_aname[$a]] = $ro_avalue[$a];
  	$ro_addcart_slug .= '&attribute_'.$ro_aname[$a].'='.$ro_avalue[$a];
  }
  
   $attributes = $att;
   $product = new WC_Product_Variable( $_REQUEST['post_id'] );
   $variation_id = ron_woo_find_matching_product_variation( $product, $attributes );

  if($variation_id != 0){
	 $cart_slug = 'variation_id='.$variation_id.$ro_addcart_slug; 
  } else {
	$cart_slug = $variation_id;  
  }
  
  echo $cart_slug;
  die();
}

function ron_woo_variable_add_to_cart($allsets) {
  global $product, $post, $woocommerce;  

   // get values from shortcode
 
   $pro_id = explode(",",$allsets['id']);
   
   $table_head = explode(",",$allsets['thead']);
  
    $data = '<div class="ron-woo-table-section">';
    $data .= '<table>'; 
  
  if(sizeof($pro_id) > 0){
    $data_array = array();
   for($a = 0; $a < sizeof($pro_id); $a++){
	   
	$data .= '<div class="ro-loader-'.$pro_id[$a].'"></div>';
	
    $product = new WC_Product_Variable( $pro_id[$a] );
  
    $attributes = $product->get_attributes();	 
     foreach( $attributes as $attribute ) {
			/*	if ( empty( $attribute['is_visible'] ) || ( $attribute['is_taxonomy'] && ! taxonomy_exists( $attribute['name'] ) ) ) {
					continue;
				}
				*/
		$title = wc_attribute_label($attribute['name']);			
		$data_array['att_title'][] = $title;
		$data_array['product_id'][] = $product->id;	
	}

   }
  
  $data .= '<thead><tr>';  
  
  if(!empty($allsets['thead']) && sizeof($table_head) > 0){	 
   foreach($table_head as $th){
	$data .= '<th>'.$th.'</th>';
   }	 
  } else {	  
	$data .= '<th>Title</th>';  
   foreach($data_array as $rok => $rov){
	  
    if($rok == 'att_title'){	  
     $ccc = array_unique($rov);
    
	 foreach($ccc as $ane){
	  $data .= '<th>'.$ane.'</th>';
	 }
	
    }	
   }
	$data .= '<th>BASKET</th>';    
  }
  
  $data .= '</tr></thead><tbody class="row-hover">';

  for($a = 0; $a < sizeof($pro_id); $a++){
	  
   if (0 == $a % 2) {
     $trcl = 'even'; // even
   } else {
     $trcl = 'odd';
   }
	
	$data .= '<tr class="row-2 '.$trcl.'">';
  
    $product = new WC_Product_Variable( $pro_id[$a] );
  
    $attributes = $product->get_attributes();

     $data .= '<td>'.get_the_title( $pro_id[$a] ).'</td>';

	  $c = 0;

    if($product->is_type('variable')){	
	
     foreach( $attributes as $attribute ) {
			/*	if ( empty( $attribute['is_visible'] ) || ( $attribute['is_taxonomy'] && ! taxonomy_exists( $attribute['name'] ) ) ) {
					continue;
				}
				*/
			$title = wc_attribute_label( $attribute['name'] );
			$name = $attribute['name'];
				
				if ( $attribute['is_taxonomy'] ) {
					$slug_val = wc_get_product_terms( $product->id, $attribute['name'], array( 'fields' => 'slugs' ) );
					$values = wc_get_product_terms( $product->id, $attribute['name'], array( 'fields' => 'names' ) );
				} else {
					$slug_val = $values = array_map( 'trim', explode( WC_DELIMITER, $attribute['value'] ) );
				}
	
				natsort($values);
				
		
		$data .= '<td class="value">
			      <input type="hidden" value="'.$name.'" id="ron-att-name-'.$product->id.$c.'"/>		
				  <select onchange="getVarData('.$product->id.','.sizeof($attributes).');" class="user_vote" id="ron-attr-'.$product->id.$c.'" name="attribute_'.$name.'" data-attribute_name="attribute_'.$name.'">
							<option value="">Choose an option &hellip;</option>';
						
					foreach ( $values as $k => $cvalue ){
						
					 if ( isset( $_REQUEST[ 'attribute_' . sanitize_title( $name ) ] ) ) {
							$selected_value = $_REQUEST[ 'attribute_' . sanitize_title( $name ) ];
					 } else {
							$selected_value = '';
					 }
					 
			         $data .= '<option value="'.$slug_val[$k].'">'.$cvalue.'</option>';	
					 
					}
							
			$data .= '</select></td>';
				
			$c++;	
	 }
	 
	  $href_link = 'javascript:void(0);';
	} else {
	  $href_link = get_permalink().'?add-to-cart='.$product->id;	
	}
	
   	   $data .= '<td class="column-5">
				 <a href="'.$href_link.'" id="add-cart-'.$product->id.'" disabled="true">
				  <img src="'.RWVT_LINK.'images/add-to-cart.png" alt="Add to Cart">
				 </a>
				</td>';
				
	$data .= '</tr>';	
 }	
  
}	
		
  $data .= '</tbody></table></div>';

 return $data;
}

 add_action( 'wp_enqueue_scripts', 'rwvt_enqueue_styles' );
 function rwvt_enqueue_styles() {     
    global $wp_styles;
	wp_register_style('RWVT', RWVT_LINK. "assets/css/rwvt_style.css");
	
    wp_enqueue_style('RWVT');
 }
 
 add_action( 'wp_head', 'rwvt_head_scripts' );
function rwvt_head_scripts(){  
?>
<script>
 //jQuery(document).ready( function() {

 function getVarData(pid,tatt){  
      post_id = pid;
      attValue = '';
	  attName = '';
	  
		for (i = 0; i < tatt; i++) { 
		 if(i != 0){
			 attName += ','; 
			attValue += ','; 
		 }
		 attName += jQuery('#ron-att-name-'+pid+i).val();
		 attValue += jQuery('#ron-attr-'+pid+i+' :selected').val();

		}	
		
       jQuery('div.ro-loader-'+pid).html('<img src="<?php echo RWVT_LINK;?>images/spinner.png" alt="loading..">');
	   
      jQuery.ajax({
         type : "post",
        // dataType : "json",
         url : '<?php echo admin_url('admin-ajax.php');?>',
         data : {action: "check_choosed_var", post_id : post_id, att_name: attName, att_value: attValue},
         success: function(response) {
			 
          if(response != 0) {
			  
		   jQuery('a#add-cart-'+pid).prop("disabled", false); 
		   jQuery('a#add-cart-'+pid).attr('href', '<?php echo get_permalink();?>?add-to-cart='+pid+'&'+response);
		
          } else {
			 jQuery('a#add-cart-'+pid).prop("disabled", true); 
		  } 
		  
		  jQuery('div.ro-loader-'+pid).html('');
		  
		 } 
       });   
  }  
</script>
<?php } ?>