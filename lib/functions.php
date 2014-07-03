<?php

//  Make the Valid WP Query Object
function pds_ss_make_filtered_wp_query($query_term, $post_type, $args = array(), $limit = 5, $thresholdStart = 2, $thresholdEnd = 4) {

    //  Search
    $wp_query = pds_ss_filtered_wp_search($query_term, $post_type, $args, $limit);

    //  Check Posts
    if(!$wp_query->have_posts()) {

        //  Get Suggestions
        $suggestions = getSSFinderInstance()->getSuggestions($query_term);

        //  Check for Suggestions
        if(sizeof($suggestions) > 0) {

            //  Make Query
            $wp_query = pds_ss_filtered_wp_search($suggestions[0], $post_type, $args, $limit);
        }
    }

    //  Check Posts
    if(!$wp_query->have_posts())
        $wp_query = pds_ss_filtered_wp_search(pds_ss_mapped_breakdown_string($query_term, $thresholdStart, $thresholdEnd), $post_type, $args, $limit);

    //  Check Posts Again
    if(!$wp_query->have_posts())
        $wp_query = pds_ss_filtered_wp_search(pds_ss_breakdown_string($query_term, $thresholdStart, $thresholdEnd), $post_type, $args, $limit);

    //  Return Query
    return $wp_query;
}

//  Filtered Wordpress Search
function pds_ss_filtered_wp_search($query_term, $post_type, $args = array(), $limit = 5) {

    //  Check Args
    if(!$args || !is_array($args))  $args = array();

    //  Add Filter
    add_filter( 'posts_where', 'pds_ss_filter_posts_like', 10, 2 );

    //  Get Properties
    $wp_query = new WP_Query(array_merge(array(
        'post_type' => $post_type,
        'filter_post_query' => (is_array($query_term) ? $query_term : array($query_term)),
        'posts_per_page' => $limit,
        'post_status' => 'publish'
    ), $args));

    //  Remove Filter
    remove_filter( 'posts_where', 'pds_ss_filter_posts_like', 10, 2 );

    //  Return
    return $wp_query;
}

//  Mapped Data Breakdown
function pds_ss_mapped_breakdown_string($str, $thresholdStart = 2, $thresholdEnd = 4) {
    $breaks = array();
    $words = explode(' ', $str);
    if(sizeof($words) < 2)
        return pds_ss_breakdown_string($str, $thresholdStart, $thresholdEnd);
    foreach($words as $i => $word) {
        $break = array($word);
        foreach($words as $j => $word2) {
            if($i != $j)
                $break[] = pds_ss_breakdown_string($word2, $thresholdStart, $thresholdEnd);
        }
        $breaks[] = $break;
    }
    return $breaks;
}

//  Breakdown String
function pds_ss_breakdown_string($str, $thresholdStart = 2, $thresholdEnd = 4) {
    $breaks = array();
    $strlen = strlen($str);
    //if($strlen == $thresholdStart)  $thresholdStart--;
    if($strlen > $thresholdStart) {
        $strs = explode(' ', $str);
        foreach($strs as $strNow) {
            $strLenNow = strlen($strNow);
            for($i = $thresholdStart; $i <= $thresholdEnd; $i++) {
                $j = 0;
                while($j <= $strLenNow) {
                    $strCut = substr($strNow, $j, $i);
                    if($strCut && !empty($strCut))
                        $breaks[] = $strCut;
                    $j += $i;
                }
            }
        }
    } else {
        $breaks[] = $str;
    }
    return $breaks;
}

//  Filter Posts
function pds_ss_filter_posts_like( $where, &$wp_query ) {
    global $wpdb;
    $search_terms = $wp_query->get('filter_post_query');
    $likes = array();
    if($search_terms && is_array($search_terms) && sizeof($search_terms) > 0) {
        foreach($search_terms as $search_term) {
            if(is_array($search_term)) {
                $thisLikes = array();
                foreach($search_term as $l => $sTerm) {
                    if($l == 0) continue;
                    foreach($sTerm as $sT) {
                        $sT = esc_sql(like_escape($sT));
                        $thisLikes[] = $wpdb->posts . ".post_title LIKE '%{$sT}%'";
                    }
                }
                $likes[] = '( ' . $wpdb->posts . ".post_title = '{$search_term[0]}' AND (" . implode(' OR ', $thisLikes) . ") )";
            } else {
                $search_term = esc_sql(like_escape($search_term));
                $likes[] = $wpdb->posts . ".post_title LIKE '%{$search_term}%'";
                $likes[] = $wpdb->posts . ".post_content LIKE '%{$search_term}%'";
            }
        }
    }
    if(sizeof($likes) > 0)
        $where .= ' AND (' . implode(' OR ', $likes) . ')';
    return $where;
}