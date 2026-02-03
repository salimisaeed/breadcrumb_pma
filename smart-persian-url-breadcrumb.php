<?php
/**
 * Plugin Name: Smart Persian URL Breadcrumb
 * Description: نمایش مسیر دقیق بر اساس URL با اولویت خودکار و اصلاح جهت آیکون.
 * Plugin URI: https://pmaimperio.com
 * Version: 1.0.0
 * Author: saeed salimi shad
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ۱. تنظیمات پنل مدیریت
add_action( 'admin_menu', 'csn_breadcrumb_admin_menu' );
function csn_breadcrumb_admin_menu() {
    add_menu_page( 'مدیریت ناوبری', 'مدیریت ناوبری', 'manage_options', 'csn-settings', 'csn_breadcrumb_settings_html', 'dashicons-location', 100 );
}

add_action( 'admin_init', 'csn_breadcrumb_settings_init' );
function csn_breadcrumb_settings_init() {
    register_setting( 'csn_br_group', 'csn_excluded_pages' );
}

function csn_breadcrumb_settings_html() {
    $excluded = get_option( 'csn_excluded_pages', array() );
    if ( ! is_array( $excluded ) ) {
        $excluded = array();
    }
    ?>
    <div class="wrap">
        <h1>تنظیمات موقعیت‌یاب هوشمند فارسی</h1>
        <form action="options.php" method="post">
            <?php settings_fields( 'csn_br_group' ); ?>
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; max-width: 600px;">
                <label><input type="checkbox" name="csn_excluded_pages[]" value="front_page" <?php checked( in_array( 'front_page', $excluded, true ) ); ?>> مخفی سازی در صفحه اصلی</label>
            </div>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function csn_get_home_label() {
    $home_id = (int) get_option( 'page_on_front' );
    if ( $home_id ) {
        return get_the_title( $home_id );
    }

    return __( 'Home', 'default' );
}

function csn_get_post_type_archive_item( $post_type ) {
    $post_type_obj = get_post_type_object( $post_type );
    if ( ! $post_type_obj || ! $post_type_obj->has_archive ) {
        return null;
    }

    $url = get_post_type_archive_link( $post_type );
    if ( ! $url ) {
        return null;
    }

    return array(
        'title' => $post_type_obj->labels->name,
        'url'   => $url,
    );
}

function csn_get_page_ancestors_items( $page_id ) {
    $items     = array();
    $ancestors = array_reverse( get_post_ancestors( $page_id ) );
    foreach ( $ancestors as $ancestor_id ) {
        $items[] = array(
            'title' => get_the_title( $ancestor_id ),
            'url'   => get_permalink( $ancestor_id ),
        );
    }

    return $items;
}

function csn_get_term_ancestors_items( $term ) {
    $items     = array();
    $ancestors = array_reverse( get_ancestors( $term->term_id, $term->taxonomy ) );
    foreach ( $ancestors as $ancestor_id ) {
        $ancestor = get_term( $ancestor_id, $term->taxonomy );
        if ( ! $ancestor || is_wp_error( $ancestor ) ) {
            continue;
        }
        $items[] = array(
            'title' => $ancestor->name,
            'url'   => get_term_link( $ancestor ),
        );
    }

    return $items;
}

function csn_build_breadcrumb_items() {
    $items = array(
        array(
            'title' => csn_get_home_label(),
            'url'   => home_url( '/' ),
        ),
    );

    if ( is_front_page() ) {
        return $items;
    }

    if ( is_home() ) {
        $blog_id = (int) get_option( 'page_for_posts' );
        if ( $blog_id ) {
            $items[] = array(
                'title' => get_the_title( $blog_id ),
                'url'   => get_permalink( $blog_id ),
            );
        }
        return $items;
    }

    if ( is_singular() ) {
        $post = get_queried_object();
        if ( $post && $post instanceof WP_Post ) {
            if ( 'page' === $post->post_type ) {
                $items = array_merge( $items, csn_get_page_ancestors_items( $post->ID ) );
            } else {
                $archive = csn_get_post_type_archive_item( $post->post_type );
                if ( $archive ) {
                    $items[] = $archive;
                }
            }

            $items[] = array(
                'title' => get_the_title( $post->ID ),
                'url'   => null,
            );
        }

        return $items;
    }

    if ( is_post_type_archive() ) {
        $post_type = get_query_var( 'post_type' );
        $post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
        if ( $post_type ) {
            $archive = csn_get_post_type_archive_item( $post_type );
            if ( $archive ) {
                $archive['url'] = null;
                $items[]        = $archive;
            }
        }

        return $items;
    }

    if ( is_tax() || is_category() || is_tag() ) {
        $term = get_queried_object();
        if ( $term && ! is_wp_error( $term ) ) {
            $items = array_merge( $items, csn_get_term_ancestors_items( $term ) );
            $items[] = array(
                'title' => $term->name,
                'url'   => null,
            );
        }

        return $items;
    }

    if ( is_search() ) {
        $items[] = array(
            'title' => sprintf( __( 'Search results for: %s', 'default' ), get_search_query() ),
            'url'   => null,
        );
        return $items;
    }

    if ( is_404() ) {
        $items[] = array(
            'title' => __( 'Not Found', 'default' ),
            'url'   => null,
        );
        return $items;
    }

    return $items;
}

// ۳. نمایش خروجی
add_action( 'wp_footer', 'csn_show_smart_breadcrumb' );
function csn_show_smart_breadcrumb() {
    $excluded = get_option( 'csn_excluded_pages', array() );
    if ( ! is_array( $excluded ) ) {
        $excluded = array();
    }

    if ( is_front_page() && in_array( 'front_page', $excluded, true ) ) {
        return;
    }
    if ( in_array( get_queried_object_id(), $excluded, true ) ) {
        return;
    }

    $is_rtl = is_rtl();

    // جهت آیکون: RTL به چپ، LTR به راست
    $sep_icon = $is_rtl ? '❮' : '❯';
    $sep      = '<span class="sep">' . $sep_icon . '</span>';

    echo '<div id="custom-breadcrumb-wrapper" class="' . esc_attr( $is_rtl ? 'is-rtl' : 'is-ltr' ) . '">';
    echo '<div class="container">';
    echo '<nav class="br-content">';
    $items      = csn_build_breadcrumb_items();
    $last_index = count( $items ) - 1;

    foreach ( $items as $index => $item ) {
        $title = isset( $item['title'] ) ? $item['title'] : '';
        $url   = isset( $item['url'] ) ? $item['url'] : null;

        if ( 0 === $index ) {
            echo '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';
            continue;
        }

        echo $sep;

        if ( $index === $last_index || ! $url ) {
            echo '<span class="' . ( $index === $last_index ? 'current' : '' ) . '">' . esc_html( $title ) . '</span>';
        } else {
            echo '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';
        }
    }

    echo '</nav></div></div>';
    ?>
    <style>
        #custom-breadcrumb-wrapper {
            position: relative; width: 100%; background: #ffffff;
            border-bottom: 1px solid #eee; z-index: 10; padding: 12px 0; display: block;
        }
        #custom-breadcrumb-wrapper .container { max-width: 1200px; margin: 0 auto; padding: 0 15px; }
        .br-content {
            font-size: 14px; color: #888; display: flex; align-items: center; gap: 10px;
            white-space: nowrap; overflow-x: auto; scrollbar-width: none;
        }
        .br-content::-webkit-scrollbar { display: none; }
        .br-content a { color: #666; text-decoration: none; transition: all 0.2s; }
        .br-content a:hover { color: #000; }
        .br-content .current { color: #222; font-weight: bold; cursor: default; }
        .br-content .sep { color: #ccc; font-size: 12px; display: flex; align-items: center; margin-top: 2px; }
        .is-rtl { direction: rtl; text-align: right; }
        .is-ltr { direction: ltr; text-align: left; }
        @media (max-width: 768px) { .br-content { font-size: 13px; } }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var bar = document.getElementById('custom-breadcrumb-wrapper');
            var header = document.querySelector('.whb-header') || document.querySelector('header.site-header') || document.querySelector('header');
            if (header && bar) {
                header.parentNode.insertBefore(bar, header.nextSibling);
            }
        });
    </script>
    <?php
}
