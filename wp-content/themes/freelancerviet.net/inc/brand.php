<?php
add_filter('admin_footer_text', function ()
{
    echo '<span id="footer-thankyou">Developed by. Contact us for any support <a href="https://freelancerviet.net">Freelancerviet.net</a></span>';
});


add_action('admin_bar_menu', 'add_top_link_to_admin_bar', 1);
function add_top_link_to_admin_bar($admin_bar)
{
    // add a child item to our parent item
    $args = array(
        'parent' => 'site-name',
        'id' => 'freelancerviet-support',
        'title' => 'Hỗ trợ kĩ thuật cho website (freelancerviet.net)',
        'href' => 'https://freelancerviet.net/lien-he',
    );
    $admin_bar->add_node($args,10,2);
}





add_filter('login_headerurl', function ()
{
    return home_url();
});

/*
add_action('wp_before_admin_bar_render', function ()
{
    global $wp_admin_bar;

    $wp_admin_bar->remove_menu('updates');          // Remove the updates link
    $wp_admin_bar->remove_menu('comments');         // Remove the comments link
    $wp_admin_bar->remove_menu('wp-logo');          // Remove the comments link
//    $wp_admin_bar->remove_menu('flatsome_panel');   // Remove the comments link
});
*/

function tp_custom_logo()
{ ?>
<style type="text/css">
#login h1 a {
    background-image: url(<?php echo get_theme_mod( 'site_logo' );
    ?>);
    background-size: contain;
    width: 100%;
    height: 110px;
    margin: 0;
}
</style>
<?php }

add_action('login_enqueue_scripts', 'tp_custom_logo');


//* Hide this administrator account from the users list

/*add_action('pre_user_query', 'dt_pre_user_query');
function dt_pre_user_query($user_search)
{
    global $current_user;
    $username = $current_user->user_login;

    if ($username != 'freelancerviet_admin') {
        global $wpdb;
        $user_search->query_where = str_replace('WHERE 1=1',
            "WHERE 1=1 AND {$wpdb->users}.user_login != 'freelancerviet_admin'", $user_search->query_where);
    }
}
*/

add_action( 'admin_menu', function (){

    global $current_user;
    $username = $current_user->user_login;

    if ($username != 'admin1') {
        remove_menu_page( 'themes.php' );               
        remove_menu_page( 'plugins.php' );             
        remove_menu_page( 'options-general.php' );      
        //remove_menu_page( 'edit-comments.php' );   
        remove_menu_page( 'tools.php' );
        remove_menu_page( 'edit.php?post_type=blocks' );   
    }
  
});  