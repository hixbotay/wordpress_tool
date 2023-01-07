<?php

/**
 * The blog template file.
 *
 * @package flatsome
 */

$lang = get_locale();
$lang_url = $lang == 'en_GB' ? '/en' : '';
$post = get_post();
$meta = get_post_meta($post->ID);
$hinh_anh_du_an = get_field('hinh_anh_san_pham');

get_header();
?>

<div class="single-tenders padding-footer">
<?= FvnBreadcum::print($post,'sanpham','product_category','Sản phẩm')?>

	<div class="single-tenders row align-center">
		<div class="large-12 col">
			<div class="col-inner">
				<div class="row head-title">
					<div class="col medium-9">
						<h1 class=" w-100 mt-2"><span class="" ><?= $post->post_title ?></h1>
					</div>
				</div>
				<div class="mt-1 content-experpt"><?= $post->post_excerpt ?></div>
				<div class="entry-content single-page mt-2"><?= the_content() ?></div>
				<div class="gap mt-4"></div>
				 <div class="chi-tiet-du-an">
				 
					<div class="hidden">
					<h2>Thông tin sản phẩm</h2>
					<div class="row-thong-tin">
						<div class="left">
							<span class="label">Địa chỉ</span>
						</div>
						<div class="right">
							<span class="value dia-chi"><?php the_field('dia_chi')?></span>  
						</div>
					</div>
					<div class="row-thong-tin">
						<div class="left">
							<span class="label">Chủ đầu tư</span>
						</div>
						<div class="right">
							<span class="value chu-dau-tu"><?php the_field('chu_dau_tu')?></span>  
						</div>
					</div>
					</div>
				  
					<?php if( $hinh_anh_du_an ): ?>
					  <h3>Hình ảnh sản phẩm</h3>
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
				</div>
			</div>
		</div>
	</div>
</div>
<style>
.chi-tiet-du-an{display:block}
.chi-tiet-du-an .row-thong-tin{padding-bottom: 8px;    border-bottom: 1px dashed #ececec;color: black; display:inline-block; width:100% !important; clear:both}
.chi-tiet-du-an .row-thong-tin .left{ width:20%; float:left; display:inline-block}
.chi-tiet-du-an .row-thong-tin .right{float:right; width: 80%; display:inline-block; padding-left:15px}
.chi-tiet-du-an h2, .chi-tiet-du-an h3, .bai-viet-lien-quan-title{font-size: 19px;
    color: #16367F;
   
    padding-bottom: 6px;}
ul.hinh-anh-du-an{display: inline-block;
    width: 100%;
    clear: both;}
ul.hinh-anh-du-an li{    margin-left: 0;
    list-style: none;
    width: 50%;
    float: left;
    display: inline-block;}
ul.hinh-anh-du-an li:nth-child(2n+1){padding-right:5px}
ul.hinh-anh-du-an li:nth-child(2n+2){padding-left:5px}
.blog-archive .post-item:nth-child(1), .blog-archive .post-item:nth-child(2) {flex-basis:50%; max-width:50%}
.blog-archive .post-item:nth-child(1) .box-image, .blog-archive .post-item:nth-child(2) .box-image{    width: 100% !important;
    display: table-caption;}
.blog-archive .post-item:nth-child(1) .box-text, .blog-archive .post-item:nth-child(2) .box-text{padding:0; padding-top:10px}
.blog-archive .post-item .box-text{padding:0 ;padding-left:15px}
.page-numbers.nav-pagination{text-align:left}
.nav-pagination>li>.current{    background: #16367F;
    border: 0;
    border-radius: 0;}
.nav-pagination>li a{background:#ececec; border: none; border-radius:0; }
.nav-pagination>li>a:hover{    border-color: #16367F;
    background-color: #16367F; font-weight:normal}
.list-news .new-img img{height: 180px;
    object-fit: cover;
    object-position: 50% 50%;}
</style>
<?php
get_footer();