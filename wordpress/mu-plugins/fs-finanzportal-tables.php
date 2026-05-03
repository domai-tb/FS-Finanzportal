<?php
/**
 * Plugin Name: FS Finanzportal Tables
 * Description: Custom shortcodes for displaying Beschlüsse and Zahlungsanweisungen tables
 * Version: 1.0.0
 */

function fsfp_display_pods_table($atts) {
    $atts = shortcode_atts([
        'post_type' => 'beschluss',
    ], $atts, 'pods_table');

    $post_type = sanitize_text_field($atts['post_type']);
    
    $args = [
        'post_type' => $post_type,
        'posts_per_page' => -1,
        'post_status' => ['publish', 'draft', 'pending', 'private'],
    ];
    
    $query = new WP_Query($args);
    
    if (!$query->have_posts()) {
        return '<p>Keine Einträge gefunden.</p>';
    }
    
    $output = '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
    $output .= '<thead><tr style="background-color: #f5f5f5; border-bottom: 2px solid #ddd;">';
    $output .= '<th style="padding: 12px; text-align: left; border-right: 1px solid #ddd;">ID</th>';
    $output .= '<th style="padding: 12px; text-align: left; border-right: 1px solid #ddd;">Titel</th>';
    $output .= '<th style="padding: 12px; text-align: left;">Datum</th>';
    $output .= '</tr></thead>';
    $output .= '<tbody>';
    
    while ($query->have_posts()) {
        $query->the_post();
        $output .= '<tr style="border-bottom: 1px solid #ddd;">';
        $output .= '<td style="padding: 12px; border-right: 1px solid #ddd;">' . get_the_ID() . '</td>';
        $output .= '<td style="padding: 12px; border-right: 1px solid #ddd;"><a href="' . get_permalink() . '">' . get_the_title() . '</a></td>';
        $output .= '<td style="padding: 12px;">' . get_the_date('d.m.Y') . '</td>';
        $output .= '</tr>';
    }
    
    $output .= '</tbody></table>';
    wp_reset_postdata();
    
    return $output;
}

add_shortcode('pods_table', 'fsfp_display_pods_table');
