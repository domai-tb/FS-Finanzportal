<?php
/**
 * Front-facing Fachschaft finance portal.
 *
 * This file intentionally keeps the prototype small: WordPress stores the
 * records, but users work through the dashboard shortcode instead of wp-admin.
 */

if (!defined('ABSPATH')) {
    exit;
}

const FSFP_DASHBOARD_SLUG = 'dashboard';
const FSFP_STATUS_META = 'beschluss_status';
const FSFP_FACHSCHAFT_META = 'fachschaft';

function fsfp_status_labels(): array
{
    return [
        'draft' => 'Entwurf',
        'submitted' => 'Eingereicht',
        'correction_requested' => 'Rückfrage',
        'approved' => 'Genehmigt',
        'rejected' => 'Abgelehnt',
        'archived' => 'Archiviert',
    ];
}

function fsfp_portal_roles(): array
{
    return ['portal_admin', 'asta_finance', 'asta_reviewer', 'fachschaft_finance', 'fachschaft_reader', 'auditor'];
}

function fsfp_user_has_role(string $role, ?WP_User $user = null): bool
{
    $user = $user ?: wp_get_current_user();
    return $user && in_array($role, (array) $user->roles, true);
}

function fsfp_user_has_any_role(array $roles, ?WP_User $user = null): bool
{
    foreach ($roles as $role) {
        if (fsfp_user_has_role($role, $user)) {
            return true;
        }
    }

    return false;
}

function fsfp_user_has_portal_role(?WP_User $user = null): bool
{
    return fsfp_user_has_any_role(fsfp_portal_roles(), $user);
}

function fsfp_user_sees_all(?WP_User $user = null): bool
{
    return fsfp_user_has_any_role(['portal_admin', 'asta_finance', 'asta_reviewer', 'auditor'], $user);
}

function fsfp_user_fachschaft(?WP_User $user = null): string
{
    $user = $user ?: wp_get_current_user();
    return $user ? sanitize_title((string) get_user_meta($user->ID, 'fsfp_fachschaft', true)) : '';
}

function fsfp_register_fallback_post_types(): void
{
    if (!post_type_exists('fachschaft')) {
        register_post_type('fachschaft', [
            'labels' => [
                'name' => 'Fachschaften',
                'singular_name' => 'Fachschaft',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
            'supports' => ['title'],
            'capability_type' => 'post',
            'menu_icon' => 'dashicons-groups',
        ]);
    }

    if (!post_type_exists('beschluss')) {
        register_post_type('beschluss', [
            'labels' => [
                'name' => 'Beschlüsse',
                'singular_name' => 'Beschluss',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'author', 'revisions'],
            'capability_type' => 'post',
            'menu_icon' => 'dashicons-portfolio',
        ]);
    }

    if (!post_type_exists('zahlungsanweisung')) {
        register_post_type('zahlungsanweisung', [
            'labels' => [
                'name' => 'Zahlungsanweisungen',
                'singular_name' => 'Zahlungsanweisung',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'author', 'revisions'],
            'capability_type' => 'post',
            'menu_icon' => 'dashicons-money-alt',
        ]);
    }
}
add_action('init', 'fsfp_register_fallback_post_types', 20);

function fsfp_dashboard_url(array $args = []): string
{
    $page = get_page_by_path(FSFP_DASHBOARD_SLUG);
    $url = $page ? get_permalink($page) : home_url('/' . FSFP_DASHBOARD_SLUG . '/');
    return $args ? add_query_arg($args, $url) : $url;
}

function fsfp_fachschaften(): array
{
    return get_posts([
        'post_type' => 'fachschaft',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);
}

function fsfp_fachschaft_slugs(): array
{
    return array_map(static fn(WP_Post $post): string => $post->post_name, fsfp_fachschaften());
}

function fsfp_fachschaft_label(string $slug): string
{
    $slug = sanitize_title($slug);
    $post = get_page_by_path($slug, OBJECT, 'fachschaft');
    return $post ? get_the_title($post) : $slug;
}

function fsfp_beschluss_status(int $post_id): string
{
    $status = (string) get_post_meta($post_id, FSFP_STATUS_META, true);
    return array_key_exists($status, fsfp_status_labels()) ? $status : 'draft';
}

function fsfp_beschluss_fachschaft(int $post_id): string
{
    return sanitize_title((string) get_post_meta($post_id, FSFP_FACHSCHAFT_META, true));
}

function fsfp_can_read_beschluss(int $post_id, ?WP_User $user = null): bool
{
    $user = $user ?: wp_get_current_user();
    if (!$user || !fsfp_user_has_portal_role($user)) {
        return false;
    }

    if (fsfp_user_sees_all($user)) {
        return true;
    }

    $fachschaft = fsfp_user_fachschaft($user);
    return $fachschaft !== '' && $fachschaft === fsfp_beschluss_fachschaft($post_id);
}

function fsfp_can_create_beschluss(?WP_User $user = null): bool
{
    return fsfp_user_has_any_role(['portal_admin', 'asta_finance', 'fachschaft_finance'], $user);
}

function fsfp_can_edit_beschluss(int $post_id, ?WP_User $user = null): bool
{
    $user = $user ?: wp_get_current_user();
    if (!$user || !fsfp_can_read_beschluss($post_id, $user)) {
        return false;
    }

    if (fsfp_user_has_any_role(['portal_admin', 'asta_finance'], $user)) {
        return true;
    }

    if (!fsfp_user_has_role('fachschaft_finance', $user)) {
        return false;
    }

    return in_array(fsfp_beschluss_status($post_id), ['draft', 'correction_requested'], true);
}

function fsfp_can_submit_beschluss(int $post_id, ?WP_User $user = null): bool
{
    $user = $user ?: wp_get_current_user();
    if (!$user || !fsfp_user_has_role('fachschaft_finance', $user) || !fsfp_can_read_beschluss($post_id, $user)) {
        return false;
    }

    return in_array(fsfp_beschluss_status($post_id), ['draft', 'correction_requested'], true);
}

function fsfp_allowed_transition(string $from, string $to, ?WP_User $user = null): bool
{
    $user = $user ?: wp_get_current_user();
    if (!$user) {
        return false;
    }

    if (fsfp_user_has_role('portal_admin', $user)) {
        return $from !== $to && array_key_exists($to, fsfp_status_labels());
    }

    if (fsfp_user_has_role('asta_finance', $user)) {
        return in_array($from . '>' . $to, [
            'submitted>correction_requested',
            'submitted>approved',
            'submitted>rejected',
            'approved>archived',
        ], true);
    }

    if (fsfp_user_has_role('asta_reviewer', $user)) {
        return $from === 'submitted' && $to === 'correction_requested';
    }

    return false;
}

function fsfp_add_status_history(int $post_id, string $from, string $to, string $comment = ''): void
{
    add_post_meta($post_id, '_fsfp_status_history', [
        'from' => $from,
        'to' => $to,
        'user_id' => get_current_user_id(),
        'user' => wp_get_current_user()->display_name,
        'timestamp' => current_time('mysql'),
        'comment' => $comment,
    ]);
}

function fsfp_parse_amount(string $amount): ?string
{
    $amount = trim(str_replace([' ', '.'], ['', ''], $amount));
    $amount = str_replace(',', '.', $amount);
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
        return null;
    }

    return number_format((float) $amount, 2, '.', '');
}

function fsfp_valid_date(string $date): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt && $dt->format('Y-m-d') === $date;
}

function fsfp_request_fachschaft(WP_User $user, array $data): string
{
    if (fsfp_user_has_role('fachschaft_finance', $user) && !fsfp_user_sees_all($user)) {
        return fsfp_user_fachschaft($user);
    }

    return sanitize_title((string) ($data['fachschaft'] ?? ''));
}

function fsfp_handle_dashboard_post(): void
{
    if (!is_page(FSFP_DASHBOARD_SLUG) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if (!is_user_logged_in()) {
        auth_redirect();
    }

    $action = sanitize_key((string) ($_POST['fsfp_action'] ?? ''));
    if (!wp_verify_nonce((string) ($_POST['_wpnonce'] ?? ''), 'fsfp_' . $action)) {
        wp_safe_redirect(fsfp_dashboard_url(['fs_notice' => 'invalid']));
        exit;
    }

    $notice = 'saved';
    $user = wp_get_current_user();

    if ($action === 'create' || $action === 'update') {
        if (!fsfp_can_create_beschluss($user)) {
            wp_safe_redirect(fsfp_dashboard_url(['fs_notice' => 'denied']));
            exit;
        }

        $post_id = absint($_POST['beschluss_id'] ?? 0);
        if ($action === 'update' && (!$post_id || !fsfp_can_edit_beschluss($post_id, $user))) {
            wp_safe_redirect(fsfp_dashboard_url(['fs_notice' => 'denied']));
            exit;
        }

        $title = sanitize_text_field((string) ($_POST['title'] ?? ''));
        $fachschaft = fsfp_request_fachschaft($user, $_POST);
        $date = sanitize_text_field((string) ($_POST['beschlussdatum'] ?? ''));
        $amount = fsfp_parse_amount((string) ($_POST['betrag'] ?? ''));
        $description = sanitize_textarea_field((string) ($_POST['zweck_beschreibung'] ?? ''));
        $notes = sanitize_textarea_field((string) ($_POST['notes'] ?? ''));

        if ($title === '' || $fachschaft === '' || !in_array($fachschaft, fsfp_fachschaft_slugs(), true) || !fsfp_valid_date($date) || $amount === null) {
            wp_safe_redirect(fsfp_dashboard_url(['fs_notice' => 'invalid']));
            exit;
        }

        $post_data = [
            'post_type' => 'beschluss',
            'post_title' => $title,
            'post_status' => 'publish',
        ];

        if ($action === 'update') {
            $post_data['ID'] = $post_id;
            $result = wp_update_post($post_data, true);
        } else {
            $post_data['post_author'] = get_current_user_id();
            $result = wp_insert_post($post_data, true);
            $post_id = is_wp_error($result) ? 0 : (int) $result;
            $notice = 'created';
        }

        if (is_wp_error($result) || !$post_id) {
            wp_safe_redirect(fsfp_dashboard_url(['fs_notice' => 'invalid']));
            exit;
        }

        update_post_meta($post_id, FSFP_FACHSCHAFT_META, $fachschaft);
        update_post_meta($post_id, 'beschlussdatum', $date);
        update_post_meta($post_id, 'betrag', $amount);
        update_post_meta($post_id, 'zweck_beschreibung', $description);
        update_post_meta($post_id, 'notes', $notes);
        if ($action === 'create') {
            update_post_meta($post_id, FSFP_STATUS_META, 'draft');
            fsfp_add_status_history($post_id, '', 'draft', 'Angelegt');
        }
    } elseif ($action === 'submit') {
        $post_id = absint($_POST['beschluss_id'] ?? 0);
        if (!$post_id || !fsfp_can_submit_beschluss($post_id, $user)) {
            wp_safe_redirect(fsfp_dashboard_url(['fs_notice' => 'denied']));
            exit;
        }

        $from = fsfp_beschluss_status($post_id);
        update_post_meta($post_id, FSFP_STATUS_META, 'submitted');
        fsfp_add_status_history($post_id, $from, 'submitted');
        $notice = 'submitted';
    } elseif ($action === 'status') {
        $post_id = absint($_POST['beschluss_id'] ?? 0);
        $to = sanitize_key((string) ($_POST['new_status'] ?? ''));
        $comment = sanitize_textarea_field((string) ($_POST['status_comment'] ?? ''));
        $from = $post_id ? fsfp_beschluss_status($post_id) : '';

        if (!$post_id || !fsfp_can_read_beschluss($post_id, $user) || !fsfp_allowed_transition($from, $to, $user)) {
            wp_safe_redirect(fsfp_dashboard_url(['fs_notice' => 'denied']));
            exit;
        }

        update_post_meta($post_id, FSFP_STATUS_META, $to);
        fsfp_add_status_history($post_id, $from, $to, $comment);
        $notice = 'status';
    }

    wp_safe_redirect(fsfp_dashboard_url(['fs_notice' => $notice]));
    exit;
}
add_action('template_redirect', 'fsfp_handle_dashboard_post', 1);

function fsfp_protect_dashboard(): void
{
    if (is_page(FSFP_DASHBOARD_SLUG) && !is_user_logged_in()) {
        auth_redirect();
    }
}
add_action('template_redirect', 'fsfp_protect_dashboard', 2);

function fsfp_redirect_portal_users_from_admin(): void
{
    if (!is_admin() || wp_doing_ajax() || !is_user_logged_in() || current_user_can('manage_options')) {
        return;
    }

    if (fsfp_user_has_portal_role()) {
        wp_safe_redirect(fsfp_dashboard_url());
        exit;
    }
}
add_action('admin_init', 'fsfp_redirect_portal_users_from_admin');

function fsfp_login_redirect(string $redirect_to, string $requested_redirect_to, WP_User|WP_Error $user): string
{
    if ($user instanceof WP_User && fsfp_user_has_portal_role($user) && !user_can($user, 'manage_options')) {
        return fsfp_dashboard_url();
    }

    return $redirect_to;
}
add_filter('login_redirect', 'fsfp_login_redirect', 10, 3);

function fsfp_dashboard_shortcode(): string
{
    if (!is_user_logged_in()) {
        auth_redirect();
        return '';
    }

    if (!fsfp_user_has_portal_role()) {
        return '<div class="fsfp-dashboard"><h1>Fachschaftsfinanzen</h1><p>Zugriff verweigert. Deinem Konto ist keine Portalrolle zugeordnet.</p></div>';
    }

    wp_enqueue_style('fsfp-dashboard', false, [], '1.0.0');
    wp_add_inline_style('fsfp-dashboard', fsfp_dashboard_css());

    $action = sanitize_key((string) ($_GET['fs_action'] ?? 'list'));
    if ($action === 'new' && fsfp_can_create_beschluss()) {
        return fsfp_render_shell(fsfp_render_beschluss_form(null));
    }

    if ($action === 'edit') {
        $post_id = absint($_GET['id'] ?? 0);
        if ($post_id && fsfp_can_edit_beschluss($post_id)) {
            return fsfp_render_shell(fsfp_render_beschluss_form(get_post($post_id)));
        }
    }

    if ($action === 'view') {
        $post_id = absint($_GET['id'] ?? 0);
        if ($post_id && fsfp_can_read_beschluss($post_id)) {
            return fsfp_render_shell(fsfp_render_beschluss_detail(get_post($post_id)));
        }
    }

    return fsfp_render_shell(fsfp_render_beschluss_list());
}
add_shortcode('fs_finanzportal_dashboard', 'fsfp_dashboard_shortcode');

function fsfp_render_shell(string $content): string
{
    $notices = [
        'created' => 'Beschluss wurde als Entwurf angelegt.',
        'saved' => 'Beschluss wurde gespeichert.',
        'submitted' => 'Beschluss wurde eingereicht.',
        'status' => 'Status wurde aktualisiert.',
        'invalid' => 'Bitte prüfe die Eingaben.',
        'denied' => 'Diese Aktion ist für dein Konto nicht erlaubt.',
    ];
    $notice_key = sanitize_key((string) ($_GET['fs_notice'] ?? ''));
    $notice = $notices[$notice_key] ?? '';

    ob_start();
    ?>
    <div class="fsfp-dashboard">
        <div class="fsfp-header">
            <div>
                <p class="fsfp-kicker">Portal</p>
                <h1>Fachschaftsfinanzen</h1>
            </div>
            <a class="fsfp-button fsfp-button-secondary" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Abmelden</a>
        </div>
        <?php if ($notice !== '') : ?>
            <div class="fsfp-notice"><?php echo esc_html($notice); ?></div>
        <?php endif; ?>
        <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

function fsfp_render_beschluss_list(): string
{
    $user = wp_get_current_user();
    $status_filter = sanitize_key((string) ($_GET['status'] ?? ''));
    $fachschaft_filter = sanitize_title((string) ($_GET['fachschaft'] ?? ''));

    $meta_query = [];
    if ($status_filter !== '' && array_key_exists($status_filter, fsfp_status_labels())) {
        $meta_query[] = ['key' => FSFP_STATUS_META, 'value' => $status_filter];
    }

    if (fsfp_user_sees_all($user)) {
        if ($fachschaft_filter !== '' && in_array($fachschaft_filter, fsfp_fachschaft_slugs(), true)) {
            $meta_query[] = ['key' => FSFP_FACHSCHAFT_META, 'value' => $fachschaft_filter];
        }
    } else {
        $fachschaft = fsfp_user_fachschaft($user);
        $meta_query[] = ['key' => FSFP_FACHSCHAFT_META, 'value' => $fachschaft !== '' ? $fachschaft : '__none__'];
    }

    $query = new WP_Query([
        'post_type' => 'beschluss',
        'post_status' => 'publish',
        'posts_per_page' => 50,
        'orderby' => 'modified',
        'order' => 'DESC',
        'meta_query' => $meta_query,
    ]);

    ob_start();
    ?>
    <div class="fsfp-toolbar">
        <form class="fsfp-filters" method="get">
            <label>Status
                <select name="status">
                    <option value="">Alle</option>
                    <?php foreach (fsfp_status_labels() as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($status_filter, $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (fsfp_user_sees_all($user)) : ?>
                <label>Fachschaft
                    <select name="fachschaft">
                        <option value="">Alle</option>
                        <?php foreach (fsfp_fachschaften() as $fachschaft) : ?>
                            <option value="<?php echo esc_attr($fachschaft->post_name); ?>" <?php selected($fachschaft_filter, $fachschaft->post_name); ?>><?php echo esc_html(get_the_title($fachschaft)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <button class="fsfp-button fsfp-button-secondary" type="submit">Filtern</button>
        </form>
        <?php if (fsfp_can_create_beschluss($user)) : ?>
            <a class="fsfp-button" href="<?php echo esc_url(fsfp_dashboard_url(['fs_action' => 'new'])); ?>">Beschluss erstellen</a>
        <?php endif; ?>
    </div>

    <div class="fsfp-table-wrap">
        <table class="fsfp-table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Fachschaft</th>
                    <th>Titel</th>
                    <th>Beschlussdatum</th>
                    <th>Betrag</th>
                    <th>Zweck / Beschreibung</th>
                    <th>Erstellt von</th>
                    <th>Aktualisiert</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($query->have_posts()) : ?>
                <?php while ($query->have_posts()) : $query->the_post(); ?>
                    <?php echo fsfp_render_beschluss_row(get_post()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endwhile; wp_reset_postdata(); ?>
            <?php else : ?>
                <tr><td colspan="9">Keine Beschlüsse gefunden.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    return (string) ob_get_clean();
}

function fsfp_render_beschluss_row(WP_Post $post): string
{
    $status = fsfp_beschluss_status($post->ID);
    $labels = fsfp_status_labels();
    $author = get_user_by('id', (int) $post->post_author);
    $amount = (float) get_post_meta($post->ID, 'betrag', true);
    $description = (string) get_post_meta($post->ID, 'zweck_beschreibung', true);

    ob_start();
    ?>
    <tr>
        <td><span class="fsfp-status fsfp-status-<?php echo esc_attr($status); ?>"><?php echo esc_html($labels[$status]); ?></span></td>
        <td><?php echo esc_html(fsfp_fachschaft_label(fsfp_beschluss_fachschaft($post->ID))); ?></td>
        <td><?php echo esc_html(get_the_title($post)); ?></td>
        <td><?php echo esc_html((string) get_post_meta($post->ID, 'beschlussdatum', true)); ?></td>
        <td><?php echo esc_html(number_format_i18n($amount, 2)); ?> €</td>
        <td><?php echo esc_html(wp_trim_words($description, 18)); ?></td>
        <td><?php echo esc_html($author ? $author->display_name : 'Unbekannt'); ?></td>
        <td><?php echo esc_html(get_the_modified_date('d.m.Y H:i', $post)); ?></td>
        <td class="fsfp-actions">
            <a class="fsfp-link" href="<?php echo esc_url(fsfp_dashboard_url(['fs_action' => 'view', 'id' => $post->ID])); ?>">Details</a>
            <?php if (fsfp_can_edit_beschluss($post->ID)) : ?>
                <a class="fsfp-link" href="<?php echo esc_url(fsfp_dashboard_url(['fs_action' => 'edit', 'id' => $post->ID])); ?>">Bearbeiten</a>
            <?php endif; ?>
            <?php if (fsfp_can_submit_beschluss($post->ID)) : ?>
                <form method="post">
                    <?php wp_nonce_field('fsfp_submit'); ?>
                    <input type="hidden" name="fsfp_action" value="submit">
                    <input type="hidden" name="beschluss_id" value="<?php echo esc_attr((string) $post->ID); ?>">
                    <button class="fsfp-link-button" type="submit">Einreichen</button>
                </form>
            <?php endif; ?>
        </td>
    </tr>
    <?php
    return (string) ob_get_clean();
}

function fsfp_render_beschluss_form(?WP_Post $post): string
{
    $is_edit = $post instanceof WP_Post;
    $user = wp_get_current_user();
    $post_id = $is_edit ? $post->ID : 0;
    $fachschaft_value = $is_edit ? fsfp_beschluss_fachschaft($post_id) : fsfp_user_fachschaft($user);

    ob_start();
    ?>
    <div class="fsfp-card">
        <div class="fsfp-subheader">
            <h2><?php echo esc_html($is_edit ? 'Beschluss bearbeiten' : 'Beschluss erstellen'); ?></h2>
            <a class="fsfp-link" href="<?php echo esc_url(fsfp_dashboard_url()); ?>">Zurück zur Übersicht</a>
        </div>
        <form class="fsfp-form" method="post">
            <?php wp_nonce_field($is_edit ? 'fsfp_update' : 'fsfp_create'); ?>
            <input type="hidden" name="fsfp_action" value="<?php echo esc_attr($is_edit ? 'update' : 'create'); ?>">
            <?php if ($is_edit) : ?>
                <input type="hidden" name="beschluss_id" value="<?php echo esc_attr((string) $post_id); ?>">
            <?php endif; ?>

            <label>Titel
                <input required name="title" value="<?php echo esc_attr($is_edit ? get_the_title($post) : ''); ?>">
            </label>

            <?php if (fsfp_user_sees_all($user)) : ?>
                <label>Fachschaft
                    <select required name="fachschaft">
                        <?php foreach (fsfp_fachschaften() as $fachschaft) : ?>
                            <option value="<?php echo esc_attr($fachschaft->post_name); ?>" <?php selected($fachschaft_value, $fachschaft->post_name); ?>><?php echo esc_html(get_the_title($fachschaft)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php else : ?>
                <div class="fsfp-readonly-field">
                    <span>Fachschaft</span>
                    <strong><?php echo esc_html(fsfp_fachschaft_label($fachschaft_value)); ?></strong>
                </div>
            <?php endif; ?>

            <label>Beschlussdatum
                <input required type="date" name="beschlussdatum" value="<?php echo esc_attr($is_edit ? (string) get_post_meta($post_id, 'beschlussdatum', true) : current_time('Y-m-d')); ?>">
            </label>

            <label>Betrag
                <input required inputmode="decimal" name="betrag" value="<?php echo esc_attr($is_edit ? (string) get_post_meta($post_id, 'betrag', true) : ''); ?>">
            </label>

            <label>Zweck / Beschreibung
                <textarea required name="zweck_beschreibung" rows="5"><?php echo esc_textarea($is_edit ? (string) get_post_meta($post_id, 'zweck_beschreibung', true) : ''); ?></textarea>
            </label>

            <label>Notizen
                <textarea name="notes" rows="3"><?php echo esc_textarea($is_edit ? (string) get_post_meta($post_id, 'notes', true) : ''); ?></textarea>
            </label>

            <div class="fsfp-form-actions">
                <button class="fsfp-button" type="submit">Speichern</button>
                <a class="fsfp-button fsfp-button-secondary" href="<?php echo esc_url(fsfp_dashboard_url()); ?>">Abbrechen</a>
            </div>
        </form>
    </div>
    <?php
    return (string) ob_get_clean();
}

function fsfp_render_beschluss_detail(?WP_Post $post): string
{
    if (!$post) {
        return '<p>Beschluss nicht gefunden.</p>';
    }

    $status = fsfp_beschluss_status($post->ID);
    $history = get_post_meta($post->ID, '_fsfp_status_history');
    $amount = (float) get_post_meta($post->ID, 'betrag', true);
    $description = (string) get_post_meta($post->ID, 'zweck_beschreibung', true);

    ob_start();
    ?>
    <div class="fsfp-card">
        <div class="fsfp-subheader">
            <h2><?php echo esc_html(get_the_title($post)); ?></h2>
            <a class="fsfp-link" href="<?php echo esc_url(fsfp_dashboard_url()); ?>">Zurück zur Übersicht</a>
        </div>
        <dl class="fsfp-detail-grid">
            <div><dt>Status</dt><dd><?php echo esc_html(fsfp_status_labels()[$status]); ?></dd></div>
            <div><dt>Fachschaft</dt><dd><?php echo esc_html(fsfp_fachschaft_label(fsfp_beschluss_fachschaft($post->ID))); ?></dd></div>
            <div><dt>Beschlussdatum</dt><dd><?php echo esc_html((string) get_post_meta($post->ID, 'beschlussdatum', true)); ?></dd></div>
            <div><dt>Betrag</dt><dd><?php echo esc_html(number_format_i18n($amount, 2)); ?> €</dd></div>
            <div><dt>Erstellt von</dt><dd><?php echo esc_html(get_the_author_meta('display_name', (int) $post->post_author)); ?></dd></div>
            <div><dt>Aktualisiert</dt><dd><?php echo esc_html(get_the_modified_date('d.m.Y H:i', $post)); ?></dd></div>
        </dl>
        <h3>Zweck / Beschreibung</h3>
        <p><?php echo nl2br(esc_html($description)); ?></p>
        <?php if ((string) get_post_meta($post->ID, 'notes', true) !== '') : ?>
            <h3>Notizen</h3>
            <p><?php echo nl2br(esc_html((string) get_post_meta($post->ID, 'notes', true))); ?></p>
        <?php endif; ?>

        <?php echo fsfp_render_review_actions($post); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <h3>Statushistorie</h3>
        <ol class="fsfp-history">
            <?php foreach (array_reverse($history) as $entry) : ?>
                <?php if (!is_array($entry)) { continue; } ?>
                <li>
                    <?php echo esc_html(($entry['timestamp'] ?? '') . ' - ' . ($entry['user'] ?? 'System')); ?>:
                    <?php echo esc_html((fsfp_status_labels()[$entry['from'] ?? ''] ?? 'Neu') . ' -> ' . (fsfp_status_labels()[$entry['to'] ?? ''] ?? '')); ?>
                    <?php if (!empty($entry['comment'])) : ?>
                        <br><span><?php echo esc_html($entry['comment']); ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
    <?php
    return (string) ob_get_clean();
}

function fsfp_render_review_actions(WP_Post $post): string
{
    $status = fsfp_beschluss_status($post->ID);
    $targets = [];
    foreach (array_keys(fsfp_status_labels()) as $target) {
        if (fsfp_allowed_transition($status, $target)) {
            $targets[] = $target;
        }
    }

    if (!$targets) {
        return '';
    }

    ob_start();
    ?>
    <form class="fsfp-review" method="post">
        <h3>Prüfung</h3>
        <?php wp_nonce_field('fsfp_status'); ?>
        <input type="hidden" name="fsfp_action" value="status">
        <input type="hidden" name="beschluss_id" value="<?php echo esc_attr((string) $post->ID); ?>">
        <label>Neuer Status
            <select name="new_status">
                <?php foreach ($targets as $target) : ?>
                    <option value="<?php echo esc_attr($target); ?>"><?php echo esc_html(fsfp_status_labels()[$target]); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Kommentar
            <textarea name="status_comment" rows="2"></textarea>
        </label>
        <button class="fsfp-button" type="submit">Status ändern</button>
    </form>
    <?php
    return (string) ob_get_clean();
}

function fsfp_dashboard_css(): string
{
    return '
    .fsfp-dashboard{max-width:1200px;margin:0 auto;padding:32px 20px;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#17202a}
    .fsfp-header,.fsfp-subheader,.fsfp-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px}
    .fsfp-kicker{margin:0 0 4px;color:#586575;font-size:14px}.fsfp-dashboard h1{margin:0;font-size:32px}.fsfp-dashboard h2{margin:0;font-size:24px}.fsfp-dashboard h3{margin:24px 0 8px;font-size:18px}
    .fsfp-notice{padding:12px 14px;background:#eef7ef;border:1px solid #b9ddc0;border-radius:6px;margin-bottom:18px}
    .fsfp-button{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:8px 14px;border:1px solid #1f6f4a;border-radius:6px;background:#1f6f4a;color:#fff;text-decoration:none;font-weight:600;cursor:pointer}
    .fsfp-button-secondary{background:#fff;color:#1f2937;border-color:#c8d0d9}.fsfp-link,.fsfp-link-button{color:#195b88;background:none;border:0;padding:0;text-decoration:underline;cursor:pointer;font:inherit}
    .fsfp-table-wrap{overflow:auto;border:1px solid #d7dde5;border-radius:8px}.fsfp-table{width:100%;border-collapse:collapse;background:#fff}.fsfp-table th,.fsfp-table td{padding:10px 12px;border-bottom:1px solid #e6ebf0;text-align:left;vertical-align:top;font-size:14px}.fsfp-table th{background:#f5f7fa;font-weight:700}.fsfp-actions{display:flex;gap:10px;flex-wrap:wrap}.fsfp-actions form{display:inline}
    .fsfp-status{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px;font-weight:700;background:#eef1f4;color:#384250}.fsfp-status-submitted{background:#e8f2ff;color:#164f82}.fsfp-status-correction_requested{background:#fff4d6;color:#73510d}.fsfp-status-approved{background:#e4f7e8;color:#1d6530}.fsfp-status-rejected{background:#fde8e8;color:#8f1f1f}.fsfp-status-archived{background:#edf0f2;color:#4a5563}
    .fsfp-filters,.fsfp-form,.fsfp-review{display:grid;gap:14px}.fsfp-filters{grid-template-columns:repeat(3,minmax(160px,1fr));align-items:end}.fsfp-form label,.fsfp-filters label,.fsfp-review label{display:grid;gap:6px;font-weight:600}.fsfp-form input,.fsfp-form select,.fsfp-form textarea,.fsfp-filters select,.fsfp-review select,.fsfp-review textarea{width:100%;box-sizing:border-box;border:1px solid #c8d0d9;border-radius:6px;padding:9px 10px;font:inherit}
    .fsfp-card{border:1px solid #d7dde5;border-radius:8px;background:#fff;padding:20px}.fsfp-form-actions{display:flex;gap:10px;flex-wrap:wrap}.fsfp-readonly-field{display:grid;gap:4px}.fsfp-readonly-field span,.fsfp-detail-grid dt{color:#586575;font-size:13px}.fsfp-detail-grid{display:grid;grid-template-columns:repeat(3,minmax(160px,1fr));gap:14px;margin:16px 0}.fsfp-detail-grid dt,.fsfp-detail-grid dd{margin:0}.fsfp-detail-grid dd{font-weight:700}.fsfp-history{padding-left:22px}
    @media (max-width:760px){.fsfp-header,.fsfp-subheader,.fsfp-toolbar{align-items:flex-start;flex-direction:column}.fsfp-filters,.fsfp-detail-grid{grid-template-columns:1fr}.fsfp-dashboard{padding:20px 12px}.fsfp-table th,.fsfp-table td{white-space:nowrap}}
    ';
}
