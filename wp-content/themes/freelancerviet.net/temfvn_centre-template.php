<?php
/*
 * Template Name: Fvn custom page
 * Template Post Type: page
 */
get_header();
$post = get_post();
$meta = get_post_meta($post->ID);
//debug($meta);
//debug(wp_get_attachment_image_url($meta['banner_image'][0]))
?>
<?= $post->post_title?>
<div><img src="<?= wp_get_attachment_image_url($meta['banner_image'][0])?>" style="width:100%;height:200px;" />
<p style="position: absolute;left:50%;top:50%;color:white"><?= $meta['baner_text'][0]?></p></div>


<?php
get_footer();
