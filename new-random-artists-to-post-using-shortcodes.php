<?php
/*
Plugin Name: New Random Artists To Posts Using Shortcodes
Description: Plugin add new random Artists to posts using shortcodes
*/

// [new_random_artists_using_shotcodes replace-posts="2" offset="0"]
// --------------------------------------------------------------------------------------
//  Artists - adding random artists to posts. This function adding between 1 and 3 artists
// --------------------------------------------------------------------------------------
add_shortcode('new_random_artists_using_shotcodes', 'new_random_artists_using_shotcodes_function');

function new_random_artists_using_shotcodes_function($atts)
{
    $atts = array_change_key_case((array) $atts, CASE_LOWER);
    $new_random_artists_atts = shortcode_atts(
        array(
            'replace-posts' => 5,
            'offset' => 0,
        ),
        $atts 
    );

    $cat_artists_id = get_cat_ID('Artists'); 
    $cat_salons_id = get_cat_ID('Salons');
    global $wpdb;
    $query_posts = $wpdb->prepare("
        SELECT p.*
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->term_relationships} tr ON (p.ID = tr.object_id)
        LEFT JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
        LEFT JOIN {$wpdb->terms} t ON (tt.term_id = t.term_id)
        WHERE p.post_type = 'post'
        AND p.post_status = 'publish'
        AND (t.term_id NOT IN (%d, %d) OR t.term_id IS NULL)
        GROUP BY p.ID
        ORDER BY p.post_date DESC
        LIMIT %d OFFSET %d
    ", $cat_artists_id, 
       $cat_salons_id, 
       $new_random_artists_atts['replace-posts'], 
       $new_random_artists_atts['offset']);

    $query_aritsts = $wpdb->prepare("
        SELECT p.*
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->term_relationships} tr ON (p.ID = tr.object_id)
        INNER JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
        WHERE tt.term_id = %d
        AND p.post_type = 'post'
        AND p.post_status = 'publish'
        ", $cat_artists_id);
    $posts = $wpdb->get_results($query_posts);
    $artists = $wpdb->get_results($query_aritsts);


    foreach($posts as $post){
        $post_id = $post->ID;

        $meta_field_metabox_artists = get_post_meta($post_id, 'metabox-artists', true);
        if( empty($meta_field_metabox_artists) || !isset($meta_field_metabox_artists) ){
            update_post_meta($post_id, 'metabox-artists', json_encode(array()));
        }
        $metabox_artists_json = get_post_meta($post_id, 'metabox-artists', true);
        $metabox_artists = json_decode($metabox_artists_json);
        $random_artists_indexes = get_random_artits_indexes(count($metabox_artists));

        

        // -----------------------------------------------------------
        // Check to existing "metabox-artists" data in particular post
        // -----------------------------------------------------------
        if(count($metabox_artists) > 1){
            // Check to how much artist in the post with statue equal yes 
            // and delete qunique user meta that used in previous versions
            $count_active_artists = 0;
            foreach ($metabox_artists as $artist) {
                if ($artist->status === 'yes') {
                    $count_active_artists++;
                }
                delete_unique_artist_meta_field_from_post($post_id, $artist->slug);
            }

            // If all dont' have statuse yes add random artist with status yes to metabox-artists 
            if ($count_active_artists === 0) {
                foreach ($random_artists_indexes as $artist_index) {
                    if(!empty($metabox_artists[$artist_index]->slug)){
                        $metabox_artists[$artist_index]->status = 'yes';
                    }
                }
                $metabox_artists_json = json_encode($metabox_artists);
                update_post_meta($post_id, 'metabox-artists', $metabox_artists_json);               
            }

            //  If all dont' have statuse yes create and add shortcodes with random artists
            if ($count_active_artists === 0){
                $shortcodes_with_random_artists = '';
                $count_of_shortcodes = 0;
                foreach ($random_artists_indexes as $artist_index) {
                    if(!empty($metabox_artists[$artist_index]->slug) && $count_of_shortcodes <= 2){
                        $count_of_shortcodes++; 

                        $random_artist = $metabox_artists[$artist_index];
                        $shortcode_with_random_artist = create_shortcode_with_artist($random_artist);
                        $shortcodes_with_random_artists .= $shortcode_with_random_artist . ' '; 
                    }
                }
                update_post_meta($post_id , '_add_artits_to_post_key', $shortcodes_with_random_artists);
            }

            //  If some artists have status yes create and add shocrodes with theese artists
            if ($count_active_artists !== 0){
                $shortcodes_with_artists = '';
                $count_of_shortcodes = 0;
                foreach ($metabox_artists as $artist) {
                    if(!empty($artist->slug) && $artist->status === 'yes' && $count_of_shortcodes <= 2){
                        $count_of_shortcodes++;

                        $artist = $artist;
                        $shortcode_with_artist = create_shortcode_with_artist($artist);
                        $shortcodes_with_artists .= $shortcode_with_artist . ' '; 
                    }
                }
                update_post_meta($post_id , '_add_artits_to_post_key', $shortcodes_with_artists);
            }
        }

        // --------------------------------------------------------
        // If the post don't have entire metabox "metabox-artists"
        // --------------------------------------------------------
        if(count($metabox_artists) <= 1){
                $new_metabox_artists = array();
                $random_artists_indexes = get_random_artits_indexes(count($artists));
                foreach ( $artists as $index => $artist ) {

                    // $post = get_post($artist->ID);
                    $artist_link = get_permalink($artist);
                    $artist_thumbnail_url = get_the_post_thumbnail_url($artist);
                    $artist_excerpt = $artist->post_excerpt;
                    $artist_name = $artist->post_title;
                    $slug = $artist->post_name;
                    $artist_slug = 'metabox-artist-' . $slug;
                    $artist_description = get_post_meta($artist->ID, 'artist_description', true);
                    $artist_active_meta= array(
                        'name' => $artist_name, 
                        'status' => 'no', 
                        'link' => $artist_link, 
                        'slug' => $artist_slug, 
                        'excerpt' => $artist_excerpt, 
                        'thumbnail_url' => $artist_thumbnail_url, 
                        'artist_description' => $artist_description);

                    delete_unique_artist_meta_field_from_post($post_id, $artist_slug); 

                    foreach($random_artists_indexes as $artist_index){
                        if($index === $artist_index ){
                             $artist_active_meta['status'] = 'yes';
                        } 
                    }
                    array_push($new_metabox_artists, $artist_active_meta);
                }

                $new_metabox_artists_json = json_encode($new_metabox_artists);
                update_post_meta($post_id, 'metabox-artists', $new_metabox_artists_json);
        }
    }
}

function get_random_artits_indexes($count_artists)
{
    $rand_count_artists = mt_rand(1, 3);
    $random_artists = array();
    for ($i = 0; $i < $rand_count_artists; $i++) {
        $random_artists[] = mt_rand(0, $count_artists);
    }
    return $random_artists;
}

function delete_unique_artist_meta_field_from_post($post_id, $artist_slug){
    $unique_artist_post_meta_field = get_post_meta($post_id, $artist_slug);
    if(isset($unique_artist_post_meta_field)){
        delete_post_meta($post_id, $artist_slug);
    } 
}

function create_shortcode_with_artist($artist){
    $shortcode = '[artist link="' . $artist->link .'" name="' . $artist->name . '"]';
    return $shortcode;
}