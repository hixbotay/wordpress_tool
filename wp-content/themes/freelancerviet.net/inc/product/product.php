<?php

add_action( 'init', function () {
 
// Add new taxonomy, make it hierarchical like categories
//first do the translations part for GUI
  
 
}, 0 );

add_action( 'init', function () {	
	//sanpham
	$labels = array(
		'name'                  => 'Sản phẩm',
		'singular_name'         => 'Sản phẩm',
		'menu_name'             => 'Sản phẩm',
		'name_admin_bar'        => 'Sản phẩm',
		'archives'              => 'Item Archives',
		'attributes'            => 'Item Attributes',
		'parent_item_colon'     => 'Parent Item:',
		'all_items'             => 'All',
		'add_new_item'          => 'Add',
		'add_new'               => 'Add',
		'new_item'              => 'new',
		'edit_item'             => 'Edit Item',
		'update_item'           => 'Update Item',
		'view_item'             => 'View Item',
		'view_items'            => 'View Items',
		'search_items'          => 'Search Item',
		'not_found'             => 'Not found',
		'not_found_in_trash'    => 'Not found in Trash',
		'featured_image'        => 'Featured Image',
		'set_featured_image'    => 'Set featured image',
		'remove_featured_image' => 'Remove featured image',
		'use_featured_image'    => 'Use as featured image',
		'insert_into_item'      => 'Insert into item',
		'uploaded_to_this_item' => 'Uploaded to this item',
		'items_list'            => 'Items list',
		'items_list_navigation' => 'Items list navigation',
		'filter_items_list'     => 'Filter items list',
	);
	$args = array(
		'label'                 => 'Sản phẩm',
		'description'           => 'Sản phẩm',
		'labels'                => $labels,
		'supports'              => array( 'title', 'editor', 'thumbnail','excerpt' ),
		'taxonomies'            => array(  ),
		'hierarchical'          => false,
		'public'                => true,
		'show_ui'               => true,
		'show_in_menu'          => true,
		'menu_position'         => 5,
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => true,
		'exclude_from_search'   => false,
		'publicly_queryable'    => true,
		'capability_type'       => 'page',		
		'menu_icon' => 'dashicons-calendar',		
	);
	register_post_type( 'sanpham', $args );

	if ( function_exists( 'add_ux_builder_post_type' ) ) {
		add_ux_builder_post_type( 'sanpham' );
	}
	
	register_taxonomy('product_category',array('sanpham'), array(
		'hierarchical' => true,
		'labels' => array(
			'name' => __( 'Category' ),
			'singular_name' => __( 'Category' ),
			'search_items' =>  __( 'Find Category' ),
			'all_items' => __( 'All Category' ),
			'parent_item' => __( 'Parent Category' ),
			'parent_item_colon' => __( 'Parent Category:' ),
			'edit_item' => __( 'Edit Category' ), 
			'update_item' => __( 'Update Category' ),
			'add_new_item' => __( 'Add Category' ),
			'new_item_name' => __( 'Category new' ),
			'menu_name' => __( 'Category' ),
		  ),
		'show_ui' => true,
		'show_in_rest' => true,
		'show_admin_column' => true,
		'query_var' => true,
		'rewrite' => array( 'slug' => 'product_category' ),
	  ));

}, 0 );

//element flatsome
function fvn_flatsome_popup_ux_builder_element()
{
    add_ux_builder_shortcode('ux_fvn_popup', array(
        'name'      => __('Pop-up'),
        'category'  => __('Content'),
        'priority'  => 1,
        'type' => 'container',
        'options' => array(
            'image_options' => array(
                'type' => 'group',
                'heading' => __('Image'),
                'options' => array(
                    'img' => array(
                        'type' => 'image',
                        'heading' => 'Image',
                        'group' => 'background',
                        'param_name' => 'img',
                    ),
                    'image_height' => array(
                        'type' => 'scrubfield',
                        'heading' => __('Height'),
                        'default' => '',
                        'placeholder' => __('Auto'),
                        'min' => 0,
                        'max' => 1000,
                        'step' => 1,
                        'helpers' => array(
                            array('title' => 'X', 'value' => ''),
                            array('title' => '1:1', 'value' => '100%'),
                            array('title' => '2:1', 'value' => '200%'),
                            array('title' => '4:3', 'value' => '75%'),
                            array('title' => '16:9', 'value' => '56.25%'),
                            array('title' => '1:2', 'value' => '50%'),
                        ),
                        'on_change' => array(
                            'selector' => '.box-image-inner',
                            'style' => 'padding-top: {{ value }}'
                        )
                    ),

                    'image_width' => array(
                        'type' => 'slider',
                        'heading' => __('Width'),
                        'unit' => '%',
                        'default' => 100,
                        'max' => 100,
                        'min' => 0,
                        'on_change' => array(
                            'selector' => '.box-image',
                            'style' => 'width: {{ value }}%'
                        )
                    ),

                    'image_radius' => array(
                        'type' => 'slider',
                        'heading' => __('Radius'),
                        'unit' => 'px',
                        'default' => 0,
                        'max' => 100,
                        'min' => 0,
                        'on_change' => array(
                            'selector' => '.fvn-popup',
                            'style' => 'border-radius: {{ value }}px'
                        )
                    ),

                    'image_size' => array(
                        'type' => 'select',
                        'heading' => __('Size'),
                        'default' => '',
                        'options' => array(
                            '' => 'Default',
                            'large' => 'Large',
                            'medium' => 'Medium',
                            'thumbnail' => 'Thumbnail',
                            'original' => 'Original',
                        )
                    ),
                    'image_padding' => array(
                        'type' => 'slider',
                        'heading' => __('Border'),
                        'unit' => 'px',
                        'default' => 0,
                        'max' => 100,
                        'min' => 0,
                        'on_change' => array(
                            'selector' => '.fvn-popup',
                            'style' => 'fvn-popup: {{ value }}px'
                        )
                    ),
                ),
            ),
            'delay' => array(
                'type' => 'scrubfield',
                'heading' => 'Delay time',
                'default' => '0',
                'step' => '1',
                'unit' => '',
                'min'   =>  0,
            ),
            
            'text_options' => array(
                'type' => 'group',
                'heading' => __('Text'),
                'options' => array(
                    'text_pos' => array(
                        'type' => 'select',
                        'heading' => __('Position'),
                        'default' => 'bottom',
                        'options' => array(
                            'top' => 'Top',                            
                            'middle' => 'Middle',
                            'bottom' => 'Bottom',
                        ),

                        'on_change' => array(
                            'selector' => '.box',
                            'class' => 'box-text-{{ value }}'
                        )
                    ),

                    'text_align' => array(
                        'type' => 'radio-buttons',
                        'heading' => __('Align'),
                        'default' => 'center',
                        'options' => array(
                            'left'   => array('title' => 'Left',   'icon' => 'dashicons-editor-alignleft'),
                            'center' => array('title' => 'Center', 'icon' => 'dashicons-editor-aligncenter'),
                            'right'  => array('title' => 'Right',  'icon' => 'dashicons-editor-alignright'),
                        ),
                        'on_change' => array(
                            'selector' => '.box-text',
                            'class' => 'text-{{ value }}'
                        )
                    ),

                    'text_size' => array(
                        'type' => 'radio-buttons',
                        'heading' => __('Size'),
                        'default' => 'medium',
                        'options' => array(
                            'xsmall' => array('title' => 'XS'),
                            'small'   => array('title' => 'S'),
                            'medium'  => array('title' => 'M'),
                            'large'  => array('title' => 'L'),
                            'xlarge'  => array('title' => 'XL'),
                        ),
                        'on_change' => array(
                            'selector' => '.box-text',
                            'class' => 'is-{{ value }}'
                        )
                    ),

                    'text_bg' => array(
                        'type' => 'colorpicker',
                        'heading' => __('Bg Color'),
                        'default' => '',
                        'alpha' => true,
                        'format' => 'rgb',
                        'position' => 'bottom right',
                        'on_change' => array(
                            'selector' => '.box-text',
                            'style' => 'background-color:{{ value }}'
                        )
                    ),

                    'text_color' => array(
                        'type' => 'radio-buttons',
                        'heading' => __('Color'),
                        'default' => 'light',
                        'options' => array(
                            'light' => array('title' => 'Dark'),
                            'dark' => array('title' => 'Light'),
                        ),
                    ),
                    'text_padding' => array(
                        'type' => 'margins',
                        'heading' => __('Padding'),
                        'value' => '',
                        'full_width' => true,
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,

                        'on_change' => array(
                            'selector' => '.box-text',
                            'style' => 'padding: {{ value }}'
                        )
                    ),
                ),
            ),
            'link_group' => array(
                'type' => 'group',
                'heading' => __('Link'),
                'options' => array(
                    'link' =>   array(
                        'type' => 'textfield',
                        'heading' => __('Link'),
                    ),
                    'target' => array(
                        'type' => 'select',
                        'heading' => __('Target'),
                        'default' => '',
                        'options' => array(
                            '' => 'Same window',
                            '_blank' => 'New window',
                        )
                    ),
                    'rel' => array(
                        'type' => 'textfield',
                        'heading' => __('Rel'),
                    ),
                )
            ),
            'advanced_options' => array(
                'type' => 'group',
                'heading' => 'Advanced',
                'options' => array(
                    'class' => array(
                        'type' => 'textfield',
                        'heading' => 'Class',
                        'param_name' => 'class',
                        'default' => '',
                    ),
                    'visible' => array(
                        'type' => 'select',
                        'heading' => 'Visible',
                        'default' => 'visible',
                        'options' => [
                            'visible'=>'Both desktop and mobile',
                            'mobile'=>'Mobile only',
                            'desktop'=>'Desktop only',
                        ]
                    )
                ),
            )
        ),
    ));
}
add_action('ux_builder_setup', 'fvn_flatsome_popup_ux_builder_element');

function fvn_flatsome_shortcode_popup($atts,$content = null )
{
    extract(shortcode_atts(array(
        'number'    => '0',
        'image' => '',
        'visible' => 'visible',
        '_id' => 'pop-up'.rand(1,100),
        'delay' => 0,
        'image_width' => '400px',
        'img'=>'',
        'text_pos' => 'bottom',
        'text_bg' => '',
        'text_hover' => '',
        'text_align' => 'center',
        'text_size' => '',
        'text_color' => '',
    ), $atts));
    if(substr($image_width,-2) != 'px'){
        $image_width = (int)$image_width.'%';
    }
    $css_args = array(		
		array( 'attribute' => 'padding', 'value' => $text_padding ),
    );
    if($text_bg){
        $css_args[] = array( 'attribute' => 'background-color', 'value' => $text_bg );
    }
    $classes_box = [];
    $classes_text = [];
    $classes_box[] = $text_pos;
    if ( $atts['text_hover'] ) $classes_text[] = 'show-on-hover hover-' . $atts['text_hover'];
	if ( $atts['text_align'] ) $classes_text[] = 'text-' . $atts['text_align'];
	if ( $atts['text_size'] ) $classes_text[] = 'is-' . $atts['text_size'];
    if ( $atts['text_color'] == 'dark' ) $classes_text[] = 'dark';
    // echo '<pre>';print_r($atts);print_r($css_args);
    ob_start();
    $mobile = wp_is_mobile();
    // print_r($atts);
    // print_r(wp_get_attachment_image_src( $img, $image_size, false ));die;
    if(($mobile && $visible=='desktop') || ($visible=='desktop' && !$mobile)){
        return;
    }
    ?>
    <div id="<?php echo $_id?>-bg" class="fvn-popup-bg <?php echo $class?>">
        <div id="<?php echo $_id?>" class="box fvn-popup <?php  echo implode(" ",$classes_box)?>" style="<?php 
        echo $mobile ? 'width:300px;' : 'width:'.$image_width.';';
        echo $atts['image_radius'] ? 'border-radius:'.$atts['image_radius'].'px;' : '';        
        echo $atts['image_padding'] ? 'padding:'.$atts['image_padding'].'px;' : '';
        ?>">
            <a target="<?php echo $target?>" href="<?php  echo $link?$link:'#'?>" rel="<?php echo $rel?>" >
                <img style="width:100%;<?php ?>" src="<?php echo wp_get_attachment_image_src( $img, $image_size, false )[0]?>" alt="" />


                <div class="box-text <?php echo implode( ' ', $classes_text ); ?>" <?php echo fvn_get_shortcode_inline_css( $css_args ); ?>>
                    <div class="box-text-inner">
                        <?php echo flatsome_contentfix( $content ); ?>
                    </div><!-- box-text-inner -->
                </div><!-- box-text -->
            </a>           

            <a href="javascript:void(0)" class="fvn-close" onclick="">&#10005;</a>
        </div>
    </div>
    
    <style>
        
        .fvn-popup-bg{
            position: fixed;
            left: 0;
            top: 0;
            width: 100%;        
            display: none;          
            background: rgba(100, 100, 100, 0.5);    
            z-index: 999;  
            <?php if(sanitize_text_field($_REQUEST['ux_builder_action']) == 'do_shortcode'){ echo "display:block;";}?>
        }
        
        .fvn-popup{
            margin: 0 auto;
            position: relative;
            margin-top: 150px;
            background: white;
        }
        .fvn-popup .box-text{
            position: absolute;
            left: 0;
            top: 0;
            padding:20px;
        }
        .fvn-popup.bottom .box-text{
            bottom:0;
            top: inherit;
        }
        .fvn-popup.middle .box-text{
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            background:green;
        } 
        .fvn-popup-bg .fvn-popup .fvn-close{
            position: absolute;
            right: -15px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 3px solid white;
            top: -15px;
            color:white;
            background: black;
            text-align: center;
            line-height: 22px;
        }
        .fvn-close:hover{
            color:silver;
        }
    </style>
    <script>
        var bg_element = document.getElementById('<?php echo $_id?>-bg');
        bg_element.style.height = window.innerHeight+'px';

        bg_element.addEventListener('click', function() {
            this.style.display = 'none';
        });
        bg_element.querySelector('.fvn-close').addEventListener('click', function() {
            bg_element.style.display = 'none';
        });  
        
        setTimeout(function(){
            document.getElementById('<?php echo $_id?>-bg').style.display = 'block';
            document.getElementById('<?php echo $_id?>').style.marginTop = parseInt((window.innerHeight - document.getElementById('<?php echo $_id?>').offsetHeight )/2)+'px';
        },<?php echo (int)($delay*1000) ?>);
    </script>
    <?php 
    return ob_get_clean();
}
add_shortcode('ux_fvn_popup', 'fvn_flatsome_shortcode_popup');

function fvn_get_shortcode_inline_css($args){
    $style = '';
      foreach ($args as $key => $value) {
        $unit = array_key_exists( 'unit', $value ) ? $value['unit'] : null;
        if($value['value']) $style .=  $value['attribute'].':'.$value['value'].$unit.';';
       }
    if($style) return 'style="'.$style.'"';
}