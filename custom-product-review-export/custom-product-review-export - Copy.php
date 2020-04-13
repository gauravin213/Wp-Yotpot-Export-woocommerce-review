<?php

/*
Plugin Name: Custom Product Review Export
Description: This is the Custom Product Review Export plugin
Author: Dev
Text Domain: custom-product-review-export
*/

//prefix: CustomProductReviewExport  //CustomProductReviewExport

defined( 'ABSPATH' ) or die();

define( 'CustomProductReviewExport_VERSION', '1.0.0' );
define( 'CustomProductReviewExport_URL', plugin_dir_url( __FILE__ ) );
define( 'CustomProductReviewExport_PATH', plugin_dir_path( __FILE__ ) );


add_action('admin_enqueue_scripts', 'CustomProductReviewExport_admin_enqueue_scripts_fun', 10, 1);
function CustomProductReviewExport_admin_enqueue_scripts_fun(){

    wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css' );
    wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js', array('jquery') );

    wp_enqueue_script( 'jquery-ui-datepicker' );
    wp_enqueue_style( 'jquery-style', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/themes/smoothness/jquery-ui.css' );
}



add_action( 'admin_menu', 'CustomProductReviewExport_admin_menu');
function CustomProductReviewExport_admin_menu(){

    $title = "CustomProductReviewExport";
    add_menu_page( $title, $title, 'manage_options', 'custom-product-review-export', 'CustomProductReviewExport_add_menu_page_fun');

}



function CustomProductReviewExport_add_menu_page_fun(){

    ?>
    <div class="wrap">

        <h1 class="wp-heading-inline"><?php _e( 'Report', 'tmm-desred' ); ?></h1><hr class="wp-header-end">

        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">

            <input type="hidden" name="action" value="CustomProductReviewExportAction">

            <button>Export</button>

        </form>

    </div>
    <?php
}


add_action( 'admin_post_CustomProductReviewExportAction', 'CustomProductReviewExportAction_fun' );
add_action( 'admin_post_nopriv_CustomProductReviewExportAction', 'CustomProductReviewExportAction_fun' );
function CustomProductReviewExportAction_fun(){

    if ( ! current_user_can( 'manage_options' ) )
        return;


        global $wpdb;

        $query = "SELECT comment_post_ID AS product_id, 
                         comment_author AS display_name, 
                         comment_date AS date,
                         comment_author_email AS user_email, 
                         comment_content AS review_content, 
                         meta_value AS review_score,
                         post_content AS product_description,
                         post_title AS product_title,
                         user_id
                  FROM `".$wpdb->prefix."comments` 
                  INNER JOIN `".$wpdb->prefix."posts` ON `".$wpdb->prefix."posts`.`ID` = `".$wpdb->prefix."comments`.`comment_post_ID` 
                  INNER JOIN `".$wpdb->prefix."commentmeta` ON `".$wpdb->prefix."commentmeta`.`comment_id` = `".$wpdb->prefix."comments`.`comment_ID` 
                  WHERE `post_type` = 'product' AND meta_key='rating'";
        $results = $wpdb->get_results($query);
        $all_reviews = array();

        foreach ($results as $value) {
            $product_instance = get_product($value->product_id);
            $current_review = array();  
            $review_content = cleanContent($value->review_content); //
            $current_review['0'] = getFirstWords($review_content); //
            $current_review['1'] = $review_content;
            $current_review['2'] = cleanContent($value->display_name); //
            $current_review['3'] = $value->user_email;
            $current_review['4'] = woocommerce_customer_bought_product($value->user_email, $value->user_id, $value->product_id) ? 'verified_buyer' : '';
            $current_review['5'] = $value->review_score;
            $current_review['6'] = $value->date;
            $current_review['7'] = $value->product_id;
            $current_review['8'] = cleanContent($value->product_title); //
            $current_review['9'] = cleanContent(get_post($value->product_id)->post_excerpt); //
            $current_review['10'] = get_permalink($value->product_id);
            $current_review['11'] = wc_yotpo_get_product_image_url($value->product_id);
            $all_reviews[] = $current_review;
        }


        $header_row = array(
            'review_title',
            'review_content',
            'display_name',
            'user_email',
            'user_type',
            'review_score',
            'date',
            'sku',
            'product_title',
            'product_description',
            'product_url',
            'product_image_url',
        );

        $data_rows = $all_reviews;

       /* echo "<pre>---->"; print_r($header_row); echo "</pre>";
        echo "<pre>====>"; print_r($data_rows); echo "</pre>";
        die();*/

        $filename = 'exported_woo_product_review.csv';
            
        $fh = @fopen( 'php://output', 'w' );
        fprintf( $fh, chr(0xEF) . chr(0xBB) . chr(0xBF) );
        header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
        header( 'Content-Description: File Transfer' );
        header( 'Content-type: text/csv' );
        header( "Content-Disposition: attachment; filename={$filename}" );
        header( 'Expires: 0' );
        header( 'Pragma: public' );
        fputcsv( $fh, $header_row );
        foreach ( $data_rows as $data_row ) {
            fputcsv( $fh, $data_row );
        }
        fclose( $fh );
        die();
}




function cleanContent($content) {
    $content = preg_replace('/\<br(\s*)?\/?\>/i', "\n", $content);
    return html_entity_decode(strip_tags(strip_shortcodes($content)));
}

function getFirstWords($content = '', $number_of_words = 5) {
    $words = str_word_count($content,1);
    if(count($words) > $number_of_words) {
        return join(" ",array_slice($words, 0, $number_of_words));
    }
    else {
        return join(" ",$words);
    }
}

function wc_yotpo_get_product_image_url($product_id) {
    $url = wp_get_attachment_url(get_post_thumbnail_id($product_id));
    return $url ? $url : null;
}