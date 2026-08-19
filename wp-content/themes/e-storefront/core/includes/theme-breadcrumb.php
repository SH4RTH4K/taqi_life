<?php

function e_storefront_breadcrumb() {
    $separator = get_theme_mod('e_storefront_breadcrumb_separator', ' / '); // Define the separator here

    if (is_home()) {
        echo "<span>Home</span>";
    } else {
        echo '<a href="' . home_url() . '">Home</a>' . $separator;

        if (is_archive()) {
            if (is_category()) {
                echo "<span>";
                single_cat_title();
                echo "</span>";
            } elseif (is_tag()) {
                echo "<span>";
                single_tag_title();
                echo "</span>";
            } elseif (is_date()) {
                echo "<span>";
                echo get_the_date('F Y');
                echo "</span>";
            } elseif (is_author()) {
                echo '<span>Author: ';
                the_author();
                echo '</span>';
            } else {
                echo post_type_archive_title() . $separator;
            }
        } elseif (is_single()) {
            if ('product' === get_post_type()) { 
                // WooCommerce Product - Add "Shop" before the product name
                $shop_page_url = get_permalink(wc_get_page_id('shop'));

                if ($shop_page_url) {
                    echo '<a href="' . esc_url($shop_page_url) . '">Shop</a>' . $separator;
                }

                echo "<span>";
                the_title();
                echo "</span>";
            } else {
                // Regular post breadcrumb
                the_category(', ');
                echo $separator;
                echo "<span>";
                the_title();
                echo "</span>";
            }
        } elseif (is_page()) {
            echo "<span>";
            the_title();
            echo "</span>";
        } elseif (is_search()) {
            echo '<span>Search Results for: ' . get_search_query() . '</span>';
        } elseif (is_404()) {
            echo "<span>404</span>";
        } else {
            echo "<span>";
            the_title();
            echo "</span>";
        }
    }
}