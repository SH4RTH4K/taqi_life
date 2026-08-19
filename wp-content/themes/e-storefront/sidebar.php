<div class="sidebar-area mt-5 wow zoomIn">
    <?php if ( ! dynamic_sidebar( 'e-storefront-sidebar' ) ) : ?>
        <aside id="search" class="sidebar-widget widget_search" role="complementary">
            <h4 class="title"><?php esc_html_e('Search Here', 'e-storefront'); ?></h4>
            <form method="get" id="searchform" class="searchform" action="<?php echo esc_url(home_url('/')); ?>">
                <input placeholder="<?php esc_attr_e('Type here...', 'e-storefront'); ?>" type="text" name="s" id="search" value="<?php the_search_query(); ?>" />
                <input type="submit" class="search-submit" value="<?php esc_attr_e('Search', 'e-storefront');?>" />
            </form>
        </aside>
        <aside id="categories-2" class="sidebar-widget widget_categories" role="complementary">
            <h4 class="title"><?php esc_html_e('Categories', 'e-storefront'); ?></h4>
            <ul>
                <?php
                wp_list_categories(array(
                    'title_li' => '',
                ));
                ?>
            </ul>
        </aside>
        <aside id="pages-2" class="sidebar-widget widget_pages" role="complementary">
            <h4 class="title"><?php esc_html_e('Pages', 'e-storefront'); ?></h4>
            <ul>
                <?php
                wp_list_pages(array(
                    'title_li' => '',
                ));
                ?>
            </ul>
        </aside>
        <aside id="archives-3" class="sidebar-widget widget_archive" role="complementary">
            <h4 class="title"><?php esc_html_e('Archives', 'e-storefront'); ?></h4>
            <ul>
            <?php
            wp_get_archives(array(
                'type' => 'postbypost',
                'format' => 'html'
            ));
            ?>
        </ul>
        </aside>
    <?php endif; ?>
</div>