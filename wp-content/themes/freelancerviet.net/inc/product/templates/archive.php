<?php

/**
 * The blog template file.
 *
 * @package flatsome
 */

get_header();
global $wp_query;
$args = array_merge( $wp_query->query_vars, array( 'posts_per_page' => '12' ) );
query_posts( $args );

?>
 
<div class='fvn-categories'>
    <div class='main-area mt-1'>
        <div class="row head-title">
            <div class="col small-12">
                <h1 class=" w-100 border-top-red pt-0"><span ><?= __("Sản phẩm", 'flatsome-child') ?></span></h1>
            </div>
        </div>
        <div class="row">
            <div class="col large-12">
                <div class="col-inner">
                    <div class="row archive-list w-100">
                        <?php while (have_posts()) : the_post();
						//$meta = get_post_meta(get_the_ID());
						?>
                        <div class="col post-item medium-3">
                            <div class="relative">
                                <div class="box box-text-banner relative">
                                    <div class="box-image">
                                        <img class="w-100 object-fit" src="<?= get_the_post_thumbnail_url()?>" alt="">
                                    </div>
                                    <div class="box-text">
                                        <div class="box-text-title strong text-center fs-4">
											<a class="text-primary" href="<?= get_the_permalink()?>" ><?= get_the_title()?></a>
										</div>
                                        <div class="box-text-meta flex">
                                            <div class="text-meta w-100 text-center"><a href="/lien-he" class="text-danger">Liên hệ</a></div>
                                            <div class="text-meta w-100 text-center mt-2"><a href="<?= get_the_permalink()?>" class="btn btn-primary">Đọc tiếp</a></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
						endwhile;
						?>
                    </div>
                </div>
                <?php flatsome_posts_pagination(); ?>
            </div>
        </div>
    </div>
</div>
<style>


</style>
<?php
get_footer();