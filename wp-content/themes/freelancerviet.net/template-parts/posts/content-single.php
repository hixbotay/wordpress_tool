<div class="entry-content single-page">
    <?php $dia_chi = get_field('dia_chi');
    $chu_dau_tu = get_field('chu_dau_tu');
    $quy_mo = get_field('quy_mo');
    $goi_thau = get_field('goi_thau');
    $gia_tri_goi_thau = get_field('gia_tri_goi_thau');
    $thoi_gian_khoi_cong = get_field('thoi_gian_khoi_cong');
    $thoi_gian_hoan_thien = get_field('thoi_gian_hoan_thien');
    $hinh_anh_du_an = get_field('hinh_anh_du_an');
    ?>
    <?php if($dia_chi) {?>
    <div class="chi-tiet-du-an">
        <h2>Thông tin dự án</h2>
        <div class="row-thong-tin">
            <div class="left">
                <span class="label">Địa chỉ</span>
            </div>
            <div class="right">
                <span class="value dia-chi"><?php the_field('dia_chi')?></span>  
            </div>
        </div>
        <?php if($chu_dau_tu){?>
        <div class="row-thong-tin">
            <div class="left">
                <span class="label">Chủ đầu tư</span>
            </div>
            <div class="right">
                <span class="value chu-dau-tu"><?php the_field('chu_dau_tu')?></span>  
            </div>
        </div>
        <?php }?>
        <?php if($quy_mo){?>
        <div class="row-thong-tin">
            <div class="left">
                <span class="label">Quy mô</span>
            </div>
            <div class="right">
                <span class="value quy-mo"><?php the_field('quy_mo')?></span>  
            </div>
        </div>
        <?php }?>
        <?php if($goi_thau){?>
        <div class="row-thong-tin">
            <div class="left">
                <span class="label">Gói thầu</span>
            </div>
            <div class="right">
                <span class="value goi-thau"><?php the_field('goi_thau')?></span>  
            </div>
        </div>
        <?php }?>
        <?php if($gia_tri_goi_thau){?>
        <div class="row-thong-tin">
            <div class="left">
                <span class="label">Giá trị gói thầu</span>
            </div>
            <div class="right">
                <span class="value gia-tri-goi-thau"><?php the_field('gia_tri_goi_thau')?></span>  
            </div>
        </div>
        <?php }?>
        <?php if($thoi_gian_khoi_cong){?>
        <div class="row-thong-tin">
            <div class="left">
                <span class="label">Thời gian khởi công</span>
            </div>
            <div class="right">
                <span class="value thoi-gian-khoi-cong"><?php the_field('thoi_gian_khoi_cong')?></span>  
            </div>
        </div>
        <?php }?>
        <?php if($thoi_gian_hoan_thien){?>
        <div class="row-thong-tin">
            <div class="left">
                <span class="label">Thời gian hoàn thiện</span>
            </div>
            <div class="right">
                <span class="value thoi-gian-hoan-thien"><?php the_field('thoi_gian_hoan_thien')?></span>  
            </div>
        </div>
        <?php }?>
      
        <?php if( $hinh_anh_du_an ): ?>
          <h3>Hình ảnh dự án</h3>
    <ul class="hinh-anh-du-an">
        <?php foreach( $hinh_anh_du_an as $hinh_anh_du_an ): ?>
            <li>
                <a href="<?php echo $hinh_anh_du_an['url']; ?>">
                     <img src="<?php echo $hinh_anh_du_an['sizes']['large']; ?>" alt="<?php echo $hinh_anh_du_an['alt']; ?>" />
                </a>
                <p><?php echo $hinh_anh_du_an['caption']; ?></p>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
    </div><?php }?>

	<?php the_content(); ?>

	<?php
	wp_link_pages( array(
		'before' => '<div class="page-links">' . __( 'Pages:', 'flatsome' ),
		'after'  => '</div>',
	) );
	?>
<?php
    $categories = get_the_category($post->ID);
    if ($categories) 
    {
        $category_ids = array();
        foreach($categories as $individual_category) $category_ids[] = $individual_category->term_id;
 
        $args=array(
        'category__in' => $category_ids,
        'post__not_in' => array($post->ID),
        'showposts'=>6, // Số bài viết bạn muốn hiển thị.
        'caller_get_posts'=>1
        );
        $my_query = new wp_query($args);
        if( $my_query->have_posts() ) 
        {
            echo '<h3 class="bai-viet-lien-quan-title">Bài viết liên quan</h3><ul class="list-news">';
            while ($my_query->have_posts())
            {
                $my_query->the_post();
                ?>
                <li>
                	<div class="new-img"><a href="<?php the_permalink(); ?>"><?php the_post_thumbnail(array(300, 200)); ?></a></div>
                	<div class="item-list">
                		<h4><a href="<?php the_permalink() ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a></h4>
               
                	</div>
                </li>
                <?php
            }
            echo '</ul>';
        }
    }
?>
	<?php if ( get_theme_mod( 'blog_share', 1 ) ) {
		// SHARE ICONS
		echo '<div class="blog-share text-center">';
		echo '<div class="is-divider medium"></div>';
		echo do_shortcode( '[share]' );
		echo '</div>';
	} ?>
</div><!-- .entry-content2 -->

<?php if ( get_theme_mod( 'blog_single_footer_meta', 1 ) ) : ?>
	<footer class="entry-meta text-<?php echo get_theme_mod( 'blog_posts_title_align', 'center' ); ?>">
		<?php
		/* translators: used between list items, there is a space after the comma */
		$category_list = get_the_category_list( __( ', ', 'flatsome' ) );

		/* translators: used between list items, there is a space after the comma */
		$tag_list = get_the_tag_list( '', __( ', ', 'flatsome' ) );


		// But this blog has loads of categories so we should probably display them here.
		if ( '' != $tag_list ) {
			$meta_text = __( 'This entry was posted in %1$s and tagged %2$s.', 'flatsome' );
		} else {
			$meta_text = __( 'This entry was posted in %1$s. Bookmark the <a href="%3$s" title="Permalink to %4$s" rel="bookmark">permalink</a>.', 'flatsome' );
		}

		printf( $meta_text, $category_list, $tag_list, get_permalink(), the_title_attribute( 'echo=0' ) );
		?>
	</footer><!-- .entry-meta -->
<?php endif; ?>

<?php if ( get_theme_mod( 'blog_author_box', 1 ) ) : ?>
	<div class="entry-author author-box">
		<div class="flex-row align-top">
			<div class="flex-col mr circle">
				<div class="blog-author-image">
					<?php
					$user = get_the_author_meta( 'ID' );
					echo get_avatar( $user, 90 );
					?>
				</div>
			</div><!-- .flex-col -->
			<div class="flex-col flex-grow">
				<h5 class="author-name uppercase pt-half">
					<?php echo esc_html( get_the_author_meta( 'display_name' ) ); ?>
				</h5>
				<p class="author-desc small"><?php echo esc_html( get_the_author_meta( 'user_description' ) ); ?></p>
			</div><!-- .flex-col -->
		</div>
	</div>
<?php endif; ?>

<?php if ( get_theme_mod( 'blog_single_next_prev_nav', 1 ) ) :
	flatsome_content_nav( 'nav-below' );
endif; ?>
