<?php
//count post
add_action( 'wp_ajax_fvn_count_post', 'fvn_count_post' );
add_action( 'wp_ajax_nopriv_fvn_count_post', 'fvn_count_post' );
function fvn_count_post(){
	$post_id = $_REQUEST['post_id'];
	$view = (int)get_post_meta($post_id, 'count_view',true);
	$view += 1;
	update_post_meta($post_id, 'count_view',$view);
	echo $view;
	exit();
}

function fvn_get_post_view($id) {
	$count = get_post_meta($id, 'count_view', true );
	return (int)$count;
}
add_filter( 'the_content', function ( $content )
{
	if ( !is_singular('post') )
		return $content;
	
	$content  .= "<script>
	let data = {
		'action': 'fvn_count_post',
		'post_id': ".get_the_ID()."
	};
	
	let ajaxurl = '".admin_url('admin-ajax.php')."';
	jQuery.ajax({ 
		type: 'POST', 
		url: ajaxurl, 
		data: data, 
		dataType: 'json',
		success: function (response) { 
			
		}
	});
	</script>";

	return $content;
});
/*end post count*/