<?php


function getUrlLang(){
	return substr(get_locale(),0,2)=='en' ? '/en' : '';
}

function fvn_get_post_like() {
    $count = get_post_meta( get_the_ID(), 'like', true );
    return "$count views";
}

//breadcum
class FvnBreadcum{
	function catName($cat_id) {
		$cat_id = (int) $cat_id;
		$category = &get_category($cat_id);
		return $category->name;
	}
	function catLink($cat_id) {
		$category = get_the_category();
		$category_link = get_category_link($cat_id);
		echo $category_link;
	}

	function print($post=false,$post_type,$cat='',$post_type_text='') {
		if($post){
			$cats = get_the_terms( $post->ID, $cat );
			$catLink = get_category_link($cats[0]);
		}
	   
		?>
		<div class="intro mt-2">
				<div class="row">
					<div class="col small-12">
						<div class="box intro-bg border-bottom">
							<div class="text ">
								<a href="<?=get_post_type_archive_link( $post_type )?>" class="text-primary"><?= $post_type_text ?></a>
								<?php if($catLink){?><a href="" class="text-primary">/ <?= $cats[0]->name ?></a><?php }?>
							</div>
						</div>
					</div>
				</div>
			</div>
		<?php
	}
}

class FvnPostThemeHelper{
	static function getPost($query,$options = []){
		return get_posts($query);
	}
}