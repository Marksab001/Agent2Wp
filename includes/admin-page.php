<?php

// SPDX-FileCopyrightText: 2026 Taibur Rahaman <https://github.com/Taibur-Rahaman>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Collect every registered ability, grouped by ability prefix.
 *
 * Disabled abilities are usually absent from the registry after the policy hook,
 * so persisted disabled rules are merged back in as placeholder rows.
 *
 * @return array<string, list<array{name: string, label: string, description: string, category: string, mcp: string, mcp_type: string, status: string, disabled: bool, protected: bool}>>
 */
function agent2wp_collect_ability_hub_rows(): array
{
    if (!function_exists('wp_get_abilities')) {
        return [];
    }

    $rules = agent2wp_get_ability_rules();
    $groups = [];
    $seen = [];

    foreach (wp_get_abilities() as $ability) {
        $row = agent2wp_build_registered_ability_row($ability, $rules);
        if ($row === null) {
            continue;
        }
        $seen[$row['name']] = true;
        $groups[agent2wp_ability_prefix($row['name'])][] = $row;
    }

    $groups = agent2wp_append_disabled_ability_rows($groups, $rules, $seen);

    foreach ($groups as $source => $rows) {
        usort($rows, static fn(array $a, array $b): int => [$a['name']] <=> [$b['name']]);
        $groups[$source] = $rows;
    }
    uksort($groups, static function (string $a, string $b): int {
        $rank = agent2wp_ability_hub_group_rank($a) <=> agent2wp_ability_hub_group_rank($b);
        return $rank !== 0 ? $rank : strcasecmp($a, $b);
    });

    return $groups;
}

/**
 * Build a hub row for a registered ability, or null when it is hidden or not exposed.
 *
 * @param array<string, array{disabled: bool}> $rules
 * @return array{name: string, label: string, description: string, category: string, mcp: string, mcp_type: string, status: string, disabled: bool, protected: bool}|null
 */
function agent2wp_build_registered_ability_row(WP_Ability $ability, array $rules): ?array
{
    $name = $ability->get_name();
    if (agent2wp_ability_is_hub_hidden($name)) {
        return null;
    }
    $meta = $ability->get_meta();
    if (!agent2wp_ability_is_exposed($meta)) {
        return null;
    }

    $protected = agent2wp_ability_is_hub_protected($name);
    $disabled = !$protected && ($rules[$name]['disabled'] ?? false);
    $category_slug = $ability->get_category();
    $category = $category_slug !== '' ? wp_get_ability_category($category_slug) : null;

    return [
        'name' => $name,
        'label' => $ability->get_label(),
        'description' => $ability->get_description(),
        'category' => $category !== null ? $category->get_label() : $category_slug,
        'mcp' => agent2wp_format_ability_mcp_meta($meta),
        'mcp_type' => agent2wp_ability_mcp_type($meta),
        'status' => $disabled ? __('Disabled', domain: 'agent2wp') : __('Enabled', domain: 'agent2wp'),
        'disabled' => $disabled,
        'protected' => $protected,
    ];
}

/**
 * Merge persisted disabled rules back in as placeholder rows for abilities that
 * are no longer registered (disabled abilities are absent after the policy hook).
 *
 * @param array<string, list<array{name: string, label: string, description: string, category: string, mcp: string, mcp_type: string, status: string, disabled: bool, protected: bool}>> $groups
 * @param array<string, array{disabled: bool}> $rules
 * @param array<string, bool> $seen
 * @return array<string, list<array{name: string, label: string, description: string, category: string, mcp: string, mcp_type: string, status: string, disabled: bool, protected: bool}>>
 */
function agent2wp_append_disabled_ability_rows(array $groups, array $rules, array $seen): array
{
    foreach ($rules as $name => $rule) {
        if (agent2wp_ability_is_hub_hidden($name) || array_key_exists($name, $seen) || !$rule['disabled']) {
            continue;
        }
        $groups[agent2wp_ability_prefix($name)][] = [
            'name' => $name,
            'label' => __('Previously registered ability', domain: 'agent2wp'),
            'description' => '',
            'category' => '',
            'mcp' => __('Unknown', domain: 'agent2wp'),
            'mcp_type' => '',
            'status' => __('Disabled', domain: 'agent2wp'),
            'disabled' => true,
            'protected' => agent2wp_ability_is_hub_protected($name),
        ];
    }

    return $groups;
}

function agent2wp_ability_prefix(string $ability_name): string
{
    $parts = explode('/', $ability_name, limit: 2);
    return $parts[0] !== '' ? $parts[0] : __('Other', domain: 'agent2wp');
}

/**
 * The ability name without its provider prefix. The provider is already the
 * group header, so repeating it on every row is noise.
 */
function agent2wp_ability_display_slug(string $ability_name): string
{
    $parts = explode('/', $ability_name, limit: 2);
    return ($parts[1] ?? '') !== '' ? $parts[1] : $ability_name;
}

/**
 * Sort rank for an ability group header: the "agent2wp" provider first, then
 * every other provider (the caller breaks ties alphabetically).
 */
function agent2wp_ability_hub_group_rank(string $source): int
{
    return $source === 'agent2wp' ? 0 : 1;
}

function agent2wp_ability_is_hub_hidden(string $ability_name): bool
{
    return str_starts_with($ability_name, 'mcp-adapter/');
}

/**
 * An ability is exposed when its MCP metadata marks it public.
 *
 * @param array<string, mixed> $meta
 */
function agent2wp_ability_is_exposed(array $meta): bool
{
    /** @var mixed $mcp */
    $mcp = $meta['mcp'] ?? null;
    return is_array($mcp) && ($mcp['public'] ?? false) === true;
}

/**
 * @param array<string, mixed> $meta
 */
function agent2wp_format_ability_mcp_meta(array $meta): string
{
    /** @var mixed $mcp */
    $mcp = $meta['mcp'] ?? null;
    if (!is_array($mcp)) {
        return __('Unknown', domain: 'agent2wp');
    }

    return (string) ($mcp['type'] ?? 'tool');
}

/**
 * Raw MCP exposure type ('tool', 'resource' or 'prompt') for pill logic, kept
 * separate from the translated display label.
 *
 * @param array<string, mixed> $meta
 */
function agent2wp_ability_mcp_type(array $meta): string
{
    /** @var mixed $mcp */
    $mcp = $meta['mcp'] ?? null;
    if (!is_array($mcp)) {
        return 'tool';
    }
    /** @var mixed $type */
    $type = $mcp['type'] ?? '';
    return $type === 'resource' || $type === 'prompt' ? $type : 'tool';
}

function agent2wp_handle_ability_hub_actions(): void
{
    if (($_POST['agent2wp_ability_hub_action'] ?? null) === null) {
        return;
    }

    if (!agent2wp_current_user_can_manage()) {
        return;
    }

    check_admin_referer('agent2wp_ability_hub_action');

    $action = is_string($_POST['agent2wp_ability_hub_action'] ?? null)
        ? sanitize_key(wp_unslash($_POST['agent2wp_ability_hub_action']))
        : '';

    if ($action === 'bulk_update') {
        agent2wp_handle_ability_hub_bulk_action();
        return;
    }

    $ability_name = is_string($_POST['ability_name'] ?? null)
        ? sanitize_text_field(wp_unslash($_POST['ability_name']))
        : '';

    if (!agent2wp_is_valid_ability_name($ability_name)) {
        wp_safe_redirect(admin_url('admin.php?page=agent2wp-abilities&agent2wp_result=invalid'));
        exit();
    }

    $rules = agent2wp_get_ability_rules();
    $rules[$ability_name] ??= ['disabled' => false];

    $rules = agent2wp_apply_ability_hub_action_to_rules($rules, $ability_name, $action);

    agent2wp_update_ability_rules($rules);
    wp_safe_redirect(admin_url('admin.php?page=agent2wp-abilities&agent2wp_result=updated'));
    exit();
}

/**
 * AJAX endpoint for the single-row enable/disable toggle. Mirrors the POST path
 * but responds with JSON so the page does not reload (preserving open sections).
 * The browser falls back to the plain form submit if this request fails.
 */
function agent2wp_handle_ability_toggle_ajax(): void
{
    if (!agent2wp_current_user_can_manage()) {
        wp_send_json_error(['message' => __('Permission denied.', domain: 'agent2wp')], status_code: 403);
    }

    if (!check_ajax_referer('agent2wp_ability_hub_action', query_arg: false, stop: false)) {
        wp_send_json_error(['message' => __(
            'Your session expired. Reload the page.',
            domain: 'agent2wp',
        )], status_code: 403);
    }

    $ability_name = is_string($_POST['ability_name'] ?? null)
        ? sanitize_text_field(wp_unslash($_POST['ability_name']))
        : '';

    if (!agent2wp_is_valid_ability_name($ability_name) || agent2wp_ability_is_hub_hidden($ability_name)) {
        wp_send_json_error(['message' => __('Invalid ability name.', domain: 'agent2wp')], status_code: 400);
    }

    if (agent2wp_ability_is_hub_protected($ability_name)) {
        wp_send_json_error(['message' => __('This ability cannot be changed.', domain: 'agent2wp')], status_code: 403);
    }

    $rules = agent2wp_get_ability_rules();
    $rules[$ability_name] ??= ['disabled' => false];
    $rules = agent2wp_toggle_ability_disabled_rule($rules, $ability_name);
    agent2wp_update_ability_rules($rules);

    $disabled = $rules[$ability_name]['disabled'] === true;
    wp_send_json_success([
        'disabled' => $disabled,
        'status' => $disabled ? __('Disabled', domain: 'agent2wp') : __('Enabled', domain: 'agent2wp'),
        'button' => $disabled ? __('Enable', domain: 'agent2wp') : __('Disable', domain: 'agent2wp'),
    ]);
}

function agent2wp_handle_ability_hub_bulk_action(): void
{
    $bulk_action = agent2wp_get_ability_hub_bulk_action();
    $ability_names = agent2wp_get_ability_hub_bulk_ability_names();
    if ($bulk_action === '' || $ability_names === []) {
        wp_safe_redirect(admin_url('admin.php?page=agent2wp-abilities&agent2wp_result=invalid'));
        exit();
    }

    $rules = agent2wp_get_ability_rules();
    foreach ($ability_names as $ability_name) {
        $rules[$ability_name] ??= ['disabled' => false];
        $rules = agent2wp_apply_ability_hub_bulk_action_to_rules($rules, $ability_name, $bulk_action);
    }

    agent2wp_update_ability_rules($rules);
    wp_safe_redirect(admin_url('admin.php?page=agent2wp-abilities&agent2wp_result=bulk_updated'));
    exit();
}

function agent2wp_get_ability_hub_bulk_action(): string
{
    $top_action = is_string($_POST['bulk_action'] ?? null) ? sanitize_key(wp_unslash($_POST['bulk_action'])) : '';
    $bottom_action = is_string($_POST['bulk_action2'] ?? null) ? sanitize_key(wp_unslash($_POST['bulk_action2'])) : '';
    $action = $top_action !== '-1' && $top_action !== '' ? $top_action : $bottom_action;

    return in_array($action, ['enable', 'disable'], strict: true) ? $action : '';
}

/**
 * @return list<string>
 */
function agent2wp_get_ability_hub_bulk_ability_names(): array
{
    $raw_names = is_array($_POST['ability_names'] ?? null) ? $_POST['ability_names'] : [];

    $ability_names = [];
    foreach ($raw_names as $raw_name) {
        if (!is_string($raw_name)) {
            continue;
        }
        $ability_name = sanitize_text_field(wp_unslash($raw_name));
        if (!agent2wp_is_valid_ability_name($ability_name) || agent2wp_ability_is_hub_hidden($ability_name)) {
            continue;
        }
        $ability_names[] = $ability_name;
    }

    return array_values(array_unique($ability_names));
}

/**
 * @param array<string, array{disabled: bool}> $rules
 * @return array<string, array{disabled: bool}>
 */
function agent2wp_apply_ability_hub_bulk_action_to_rules(array $rules, string $ability_name, string $action): array
{
    if (agent2wp_ability_is_hub_protected($ability_name)) {
        return $rules;
    }

    if ($action === 'enable') {
        $rules[$ability_name]['disabled'] = false;
        return $rules;
    }

    if ($action === 'disable') {
        $rules[$ability_name]['disabled'] = true;
    }

    return $rules;
}

/**
 * @param array<string, array{disabled: bool}> $rules
 * @return array<string, array{disabled: bool}>
 */
function agent2wp_apply_ability_hub_action_to_rules(array $rules, string $ability_name, string $action): array
{
    if ($action === 'toggle_disabled') {
        return agent2wp_toggle_ability_disabled_rule($rules, $ability_name);
    }

    return $rules;
}

/**
 * @param array<string, array{disabled: bool}> $rules
 * @return array<string, array{disabled: bool}>
 */
function agent2wp_toggle_ability_disabled_rule(array $rules, string $ability_name): array
{
    if (agent2wp_ability_is_hub_protected($ability_name)) {
        return $rules;
    }

    $rules[$ability_name]['disabled'] = !$rules[$ability_name]['disabled'];
    return $rules;
}

function agent2wp_handle_sandbox_actions()
{
    if (!agent2wp_current_user_can_manage()) {
        return;
    }

    $action = is_string($_GET['action'] ?? null) ? sanitize_key(wp_unslash($_GET['action'])) : '';
    $file_param = is_string($_GET['file'] ?? null) ? wp_unslash($_GET['file']) : '';

    if ($action === '' || $file_param === '') {
        return;
    }

    $file = basename($file_param);
    if (!check_admin_referer('agent2wp_manage_file_' . $file)) {
        return;
    }

    $path = agent2wp_get_sandbox_dir(true) . $file;
    if (!file_exists($path)) {
        return;
    }

    $result = match ($action) {
        'delete' => unlink($path),
        'disable' => str_ends_with($file, '.php') && rename($path, $path . '.disabled'),
        'enable' => str_ends_with($file, '.disabled') && rename($path, substr($path, offset: 0, length: -9)),
        'exit_safe_mode' => $file === '.crashed' && unlink($path),
        default => false,
    };

    if ($result) {
        wp_safe_redirect(admin_url('admin.php?page=agent2wp-sandbox&agent2wp_result=' . $action));
        exit();
    }
}

function agent2wp_render_sandbox_page(): void
{
    if (!agent2wp_current_user_can_manage()) {
        return;
    }

    $result_message = match (agent2wp_admin_request_get_string('agent2wp_result')) {
        'delete' => __('File deleted.', domain: 'agent2wp'),
        'disable' => __('File disabled.', domain: 'agent2wp'),
        'enable' => __('File enabled.', domain: 'agent2wp'),
        'exit_safe_mode' => __(
            'Safe mode deactivated. Sandbox files will load on the next request.',
            domain: 'agent2wp',
        ),
        default => null,
    };
    $sandbox_dir = agent2wp_get_sandbox_dir(true);
    $is_crashed = file_exists($sandbox_dir . '.crashed');

    agent2wp_render_admin_header();
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('Sandbox files', domain: 'agent2wp'); ?></h1>
        <hr class="wp-header-end" />

        <?php if ($result_message !== null): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($result_message); ?></p></div>
        <?php endif; ?>

        <?php if ($is_crashed): ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('Safe mode is active.', domain: 'agent2wp'); ?></strong>
                    <?php esc_html_e(
                        'A sandbox file caused a fatal error on a previous request. All sandbox files are suspended until you fix or delete the broken file and exit safe mode.',
                        domain: 'agent2wp',
                    ); ?>
                </p>
                <p>
                    <?php

                    $exit_url = wp_nonce_url(
                        admin_url('admin.php?page=agent2wp-sandbox&action=exit_safe_mode&file=.crashed'),
                        action: 'agent2wp_manage_file_.crashed',
                    );
                    ?>
                    <a href="<?php echo esc_url($exit_url); ?>" class="button button-primary"><?php esc_html_e(
                        'Exit Safe Mode',
                        domain: 'agent2wp',
                    ); ?></a>
                </p>
            </div>
        <?php endif; ?>

        <?php agent2wp_render_sandbox_list($sandbox_dir); ?>
    </div>
    <?php
}

/**
 * Render the file list as a card section.
 * Layout mirrors the Skills admin page so the two pages feel consistent.
 */
function agent2wp_render_sandbox_list(string $sandbox_dir): void
{
    $files = agent2wp_get_sandbox_files($sandbox_dir);
    $sandbox_status = file_exists($sandbox_dir . '.crashed') ? 'suspended' : 'active';
    ?>
    <section class="agent2wp-sandbox-section">
        <div class="agent2wp-sandbox-header">
            <h2><?php esc_html_e('Files', domain: 'agent2wp'); ?>
                <span class="count"><?php echo (int) count($files); ?></span>
            </h2>
        </div>
        <?php if ($files === []): ?>
            <div class="agent2wp-sandbox-empty"><?php esc_html_e(
                'No sandbox files yet. AI agents will place generated files here.',
                domain: 'agent2wp',
            ); ?></div>
        <?php endif; ?>
        <?php if ($files !== []): ?>
            <?php agent2wp_render_sandbox_rows($sandbox_dir, $files, $sandbox_status); ?>
        <?php endif; ?>
    </section>
    <?php
}

/**
 * @return list<string>
 */
function agent2wp_get_sandbox_files(string $sandbox_dir): array
{
    $scanned_files = is_dir($sandbox_dir) ? scandir($sandbox_dir) : false;
    $files = $scanned_files !== false ? array_diff($scanned_files, ['.', '..', '.loading', '.crashed']) : [];

    return array_values(array_filter($files, static fn(string $file): bool => !is_dir($sandbox_dir . $file)));
}

/**
 * @param list<string> $files
 */
function agent2wp_render_sandbox_rows(string $sandbox_dir, array $files, string $sandbox_status): void
{
    $format = agent2wp_get_datetime_format();
    $base_url = admin_url('admin.php?page=agent2wp-sandbox');
    ?>
    <div class="agent2wp-sandbox-rows">
        <?php foreach ($files as $file): ?>
            <?php agent2wp_render_sandbox_row($sandbox_dir, $file, $sandbox_status, $format, $base_url); ?>
        <?php endforeach; ?>
    </div>
    <?php
}

function agent2wp_render_sandbox_row(
    string $sandbox_dir,
    string $file,
    string $sandbox_status,
    string $format,
    string $base_url,
): void {
    $path = $sandbox_dir . $file;
    $file_status = agent2wp_get_sandbox_file_status($file, $sandbox_status);
    $display_name = $file_status === 'disabled' ? substr($file, offset: 0, length: -9) : $file;
    $ext = strtolower(pathinfo($display_name, PATHINFO_EXTENSION));
    $mtime = filemtime($path);
    $wp_date = $mtime !== false ? wp_date($format, $mtime) : false;
    $modified = $wp_date !== false ? $wp_date : __('Unknown', domain: 'agent2wp');

    $delete_url = wp_nonce_url(
        $base_url . '&action=delete&file=' . urlencode($file),
        action: 'agent2wp_manage_file_' . $file,
    );
    ?>
    <div class="<?php echo esc_attr('agent2wp-sandbox-row is-' . $file_status); ?>">
        <?php agent2wp_render_sandbox_toggle($file, $file_status, $ext, $base_url); ?>

        <div class="agent2wp-sandbox-main">
            <span class="slug"><?php echo esc_html($display_name); ?></span>
            <span class="meta"><?php echo esc_html($modified); ?></span>
        </div>

        <div class="agent2wp-sandbox-pills">
            <?php agent2wp_render_sandbox_pills($ext, $file_status); ?>
        </div>

        <div class="agent2wp-sandbox-actions">
            <a
                href="<?php echo esc_url($delete_url); ?>"
                class="action-btn action-btn--danger"
                onclick="return confirm('<?php echo
                    esc_js(__('Are you sure you want to delete this file?', domain: 'agent2wp'))
                ; ?>');"
            ><?php esc_html_e('Delete', domain: 'agent2wp'); ?></a>
        </div>
    </div>
    <?php
}

function agent2wp_get_sandbox_file_status(string $file, string $sandbox_status): string
{
    if ($sandbox_status === 'suspended') {
        return 'suspended';
    }

    if (str_ends_with($file, '.disabled')) {
        return 'disabled';
    }

    return 'on';
}

function agent2wp_render_sandbox_toggle(string $file, string $file_status, string $ext, string $base_url): void
{
    if ($file_status === 'suspended' || $file_status !== 'disabled' && $ext !== 'php') {
        ?>
        <span class="agent2wp-sandbox-check" aria-hidden="true"></span>
        <?php

        return;
    }

    $is_disabled = $file_status === 'disabled';
    $toggle_action = $is_disabled ? 'enable' : 'disable';
    $toggle_url = wp_nonce_url(
        $base_url . '&action=' . $toggle_action . '&file=' . urlencode($file),
        action: 'agent2wp_manage_file_' . $file,
    );
    ?>
    <a
        href="<?php echo esc_url($toggle_url); ?>"
        class="agent2wp-sandbox-toggle"
        title="<?php echo
            $is_disabled ? esc_attr__('Enable', domain: 'agent2wp') : esc_attr__('Disable', domain: 'agent2wp')
        ; ?>"
        aria-label="<?php echo
            $is_disabled
                ? esc_attr__('Enable file', domain: 'agent2wp')
                : esc_attr__('Disable file', domain: 'agent2wp')
        ; ?>"
    ><span class="agent2wp-sandbox-check"></span></a>
    <?php
}

function agent2wp_render_sandbox_pills(string $ext, string $file_status): void
{
    if ($ext !== '') { ?>
        <span class="pill ext-<?php echo esc_attr($ext); ?>"><?php echo esc_html($ext); ?></span>
        <?php }

    if ($file_status === 'suspended') {
        ?>
        <span class="pill warn"><?php esc_html_e('Suspended', domain: 'agent2wp'); ?></span>
        <?php

        return;
    }

    if ($file_status === 'disabled') { ?>
        <span class="pill"><?php esc_html_e('Disabled', domain: 'agent2wp'); ?></span>
        <?php }
}

function agent2wp_render_settings_page()
{
    if (!agent2wp_current_user_can_manage()) {
        return;
    }

    $ability_groups = agent2wp_collect_ability_hub_rows();
    $result_raw = agent2wp_admin_request_get_string('agent2wp_result');
    $result = $result_raw !== '' ? sanitize_key($result_raw) : null;
    ?>
    <?php agent2wp_render_admin_header(); ?>
    <div
        class="wrap agent2wp-hub"
        data-alloff-label="<?php esc_attr_e('All disabled', domain: 'agent2wp'); ?>"
        data-confirm-disable="<?php esc_attr_e(
            'Disable the %d selected abilities? You can re-enable them anytime.',
            domain: 'agent2wp',
        ); ?>"
    >
        <div class="wrap-title">
            <div>
                <h1><?php esc_html_e('Abilities Hub', domain: 'agent2wp'); ?></h1>
                <p class="description"><?php printf(
                    /* translators: %s: link to the Configuration page */
                    esc_html__(
                        'Manage every ability exposed to AI agents. This lists abilities registered by Agent2Wp and any other plugin that uses the WordPress Abilities API, grouped by provider. Disabled abilities are removed from registry discovery and MCP execution while AI Abilities are enabled on the %s page.',
                        domain: 'agent2wp',
                    ),
                    '<a href="'
                    . esc_url(admin_url('admin.php?page=agent2wp-connect'))
                    . '">'
                    . esc_html__('Configuration', domain: 'agent2wp')
                    . '</a>',
                ); ?></p>
            </div>
        </div>
        <?php agent2wp_render_ability_hub_result_notice($result); ?>
        <?php if ($ability_groups === []): ?>
            <div class="notice notice-info"><p><?php esc_html_e(
                'No abilities are currently registered.',
                domain: 'agent2wp',
            ); ?></p></div>
        <?php endif; ?>
        <?php if ($ability_groups !== []): ?>
            <form id="agent2wp-abilities-bulk" method="post">
                <?php wp_nonce_field('agent2wp_ability_hub_action'); ?>
                <input type="hidden" name="agent2wp_ability_hub_action" value="bulk_update" />
            </form>
            <?php agent2wp_render_ability_bulk_actions('top'); ?>
        <?php endif; ?>
        <?php $expanded_source = array_key_first($ability_groups); ?>
        <?php $seen_core = false; ?>
        <?php $divider_done = false; ?>
        <?php foreach ($ability_groups as $source => $abilities): ?>
            <?php $is_core = agent2wp_ability_hub_group_rank($source) === 0; ?>
            <?php if (!$is_core && $seen_core && !$divider_done): ?>
                <?php agent2wp_render_ability_other_plugins_divider(); ?>
                <?php $divider_done = true; ?>
            <?php endif; ?>
            <?php $seen_core = $seen_core || $is_core; ?>
            <?php agent2wp_render_ability_group_section($source, $abilities, $expanded_source); ?>
        <?php endforeach; ?>
        <?php if ($ability_groups !== []): ?>
            <?php agent2wp_render_ability_bulk_actions('bottom'); ?>
        <?php endif; ?>
    </div>
    <?php
}

function agent2wp_render_ability_hub_result_notice(?string $result): void
{
    $notice = match ($result) {
        'updated' => ['success', __('Ability rule updated.', domain: 'agent2wp')],
        'bulk_updated' => ['success', __('Ability rules updated.', domain: 'agent2wp')],
        'invalid' => ['error', __('Invalid ability name.', domain: 'agent2wp')],
        default => null,
    };

    if ($notice === null) {
        return;
    }
    ?>
    <div class="<?php echo esc_attr('notice notice-' . $notice[0] . ' is-dismissible'); ?>">
        <p><?php echo esc_html($notice[1]); ?></p>
    </div>
    <?php
}

/**
 * Divider that separates Agent2Wp's own abilities from those registered by
 * other plugins, so a provider like "jet-engine" reads clearly as the plugin's.
 */
function agent2wp_render_ability_other_plugins_divider(): void
{ ?>
    <h2 class="agent2wp-hub-divider"><?php esc_html_e('Registered by other plugins', domain: 'agent2wp'); ?></h2>
    <?php }

/**
 * @param list<array{name: string, label: string, description: string, category: string, mcp: string, mcp_type: string, status: string, disabled: bool, protected: bool}> $abilities
 * @param string|null $expanded_source Group key that should render expanded.
 */
function agent2wp_render_ability_group_section(string $source, array $abilities, ?string $expanded_source): void
{ ?>
    <details class="agent2wp-hub-section"<?php echo $source === $expanded_source ? ' open' : ''; ?>>
        <summary class="agent2wp-hub-header">
            <?php agent2wp_render_ability_select_all(sprintf(
                /* translators: %s: provider name */
                __('Select all abilities from %s', domain: 'agent2wp'),
                $source,
            )); ?>
            <h2><?php echo esc_html($source); ?>
                <?php agent2wp_render_ability_header_meta($abilities); ?>
            </h2>
        </summary>
        <?php agent2wp_render_ability_group_body($abilities); ?>
    </details>
    <?php }

/**
 * Render a section header's count and, when every ability in it is disabled, an
 * "All disabled" pill. The count shows `enabled / total` while some are off and
 * the bare total when all are enabled. hub.js keeps both in sync after an
 * AJAX toggle.
 *
 * @param list<array{name: string, label: string, description: string, category: string, mcp: string, mcp_type: string, status: string, disabled: bool, protected: bool}> $abilities
 */
function agent2wp_render_ability_header_meta(array $abilities): void
{
    $total = count($abilities);
    $enabled = 0;
    foreach ($abilities as $ability) {
        if ($ability['disabled']) {
            continue;
        }
        $enabled++;
    }
    ?>
    <span class="count"><?php echo
        esc_html($enabled === $total ? (string) $total : $enabled . ' / ' . $total)
    ; ?></span>
    <?php if ($enabled === 0 && $total > 0): ?>
        <span class="pill status is-disabled agent2wp-hub-alloff"><?php

        esc_html_e('All disabled', domain: 'agent2wp'); ?></span>
    <?php endif; ?>
    <?php
}

/**
 * Render the "select all" checkbox shown in a provider or category header. It
 * toggles every row checkbox within its section client-side (see hub.js); the
 * actual enable/disable still goes through the existing bulk action + nonce.
 */
function agent2wp_render_ability_select_all(string $label): void
{ ?>
    <label class="agent2wp-hub-select-all">
        <span class="screen-reader-text"><?php echo esc_html($label); ?></span>
        <input type="checkbox" class="agent2wp-hub-select-all-input" />
    </label>
    <?php }

/**
 * Render a provider group's body: category sub-sections when there is more than
 * one category, otherwise a flat row list.
 *
 * @param list<array{name: string, label: string, description: string, category: string, mcp: string, mcp_type: string, status: string, disabled: bool, protected: bool}> $abilities
 */
function agent2wp_render_ability_group_body(array $abilities): void
{
    $by_category = agent2wp_group_abilities_by_category($abilities);
    if (count($by_category) > 1) {
        foreach ($by_category as $category => $rows) {
            agent2wp_render_ability_category_subsection($category, $rows);
        }
        return;
    }
    ?>
    <div class="agent2wp-hub-rows">
        <?php foreach ($abilities as $ability): ?>
            <?php agent2wp_render_ability_hub_row($ability); ?>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Group hub rows by their category label. Uncategorized rows sort last.
 *
 * @param list<array{name: string, label: string, description: string, category: string, mcp: string, mcp_type: string, status: string, disabled: bool, protected: bool}> $abilities
 * @return array<string, list<array{name: string, label: string, description: string, category: string, mcp: string, mcp_type: string, status: string, disabled: bool, protected: bool}>>
 */
function agent2wp_group_abilities_by_category(array $abilities): array
{
    $groups = [];
    foreach ($abilities as $ability) {
        $groups[$ability['category']][] = $ability;
    }

    uksort($groups, static function (string $a, string $b): int {
        if ($a === '' || $b === '') {
            return $a === '' ? 1 : -1;
        }
        return strcasecmp($a, $b);
    });

    return $groups;
}

/**
 * @param list<array{name: string, label: string, description: string, category: string, mcp: string, mcp_type: string, status: string, disabled: bool, protected: bool}> $rows
 */
function agent2wp_render_ability_category_subsection(string $category, array $rows): void
{
    $label = $category !== '' ? $category : __('Other', domain: 'agent2wp');
    ?>
    <details class="agent2wp-hub-subsection">
        <summary class="agent2wp-hub-subheader">
            <?php agent2wp_render_ability_select_all(sprintf(
                /* translators: %s: category name */
                __('Select all abilities in %s', domain: 'agent2wp'),
                $label,
            )); ?>
            <h3><?php echo esc_html($label); ?>
                <?php agent2wp_render_ability_header_meta($rows); ?>
            </h3>
        </summary>
        <div class="agent2wp-hub-rows">
            <?php foreach ($rows as $ability): ?>
                <?php agent2wp_render_ability_hub_row($ability); ?>
            <?php endforeach; ?>
        </div>
    </details>
    <?php
}

/**
 * @param array{name: string, label: string, description: string, category: string, mcp: string, mcp_type: string, status: string, disabled: bool, protected: bool} $ability
 */
function agent2wp_render_ability_hub_row(array $ability): void
{
    $row_class = 'agent2wp-hub-row ' . ($ability['disabled'] ? 'is-off' : 'is-on');
    $row_class .= $ability['protected'] ? ' is-protected' : '';
    ?>
    <div class="<?php echo esc_attr($row_class); ?>">
        <label class="agent2wp-hub-select">
            <span class="screen-reader-text"><?php echo
                esc_html(sprintf(
                    /* translators: %s: ability name */
                    __('Select %s', domain: 'agent2wp'),
                    $ability['name'],
                ))
            ; ?></span>
            <input
                type="checkbox"
                name="ability_names[]"
                value="<?php echo esc_attr($ability['name']); ?>"
                form="agent2wp-abilities-bulk"
            />
        </label>

        <?php agent2wp_render_ability_hub_main($ability); ?>

        <?php agent2wp_render_ability_hub_pills($ability); ?>
        <?php agent2wp_render_ability_toggle_action($ability); ?>
    </div>
    <?php
}

/**
 * Render the ability's slug and description. When a description is available the
 * row becomes expandable (CSS-only <details>) to reveal the full text and its
 * safety annotations; placeholder rows without a description stay flat.
 *
 * @param array{name: string, label: string, description: string, category: string, mcp: string, mcp_type: string, status: string, disabled: bool, protected: bool} $ability
 */
function agent2wp_render_ability_hub_main(array $ability): void
{
    if ($ability['description'] === '') {
        ?>
        <div class="agent2wp-hub-main agent2wp-hub-main--plain">
            <span class="slug" title="<?php echo esc_attr($ability['name']); ?>"><?php echo
                esc_html(agent2wp_ability_display_slug($ability['name']))
            ; ?></span>
            <span class="desc"><?php echo esc_html($ability['label']); ?></span>
        </div>
        <?php

        return;
    }
    ?>
    <details class="agent2wp-hub-main">
        <summary class="agent2wp-hub-summary">
            <span class="slug" title="<?php echo esc_attr($ability['name']); ?>"><?php echo
                esc_html(agent2wp_ability_display_slug($ability['name']))
            ; ?></span>
            <span class="desc"><?php echo esc_html($ability['description']); ?></span>
        </summary>
        <div class="agent2wp-hub-detail">
            <p class="desc-full"><?php echo esc_html($ability['description']); ?></p>
        </div>
    </details>
    <?php
}

/**
 * @param array{name: string, label: string, description: string, category: string, mcp: string, mcp_type: string, status: string, disabled: bool, protected: bool} $ability
 */
function agent2wp_render_ability_hub_pills(array $ability): void
{ ?>
    <div class="agent2wp-hub-pills">
        <?php if (in_array($ability['mcp_type'], ['prompt', 'resource'], strict: true)): ?>
            <span class="pill mcp"><?php echo esc_html($ability['mcp']); ?></span>
        <?php endif; ?>
        <span class="<?php echo esc_attr('pill status ' . ($ability['disabled'] ? 'is-disabled' : 'is-enabled')); ?>">
            <?php echo esc_html($ability['status']); ?>
        </span>
        <?php if ($ability['protected']): ?>
            <span class="pill protected"><?php esc_html_e('Protected', domain: 'agent2wp'); ?></span>
        <?php endif; ?>
    </div>
    <?php }

/**
 * @param array{name: string, label: string, description: string, category: string, mcp: string, mcp_type: string, status: string, disabled: bool, protected: bool} $ability
 */
function agent2wp_render_ability_toggle_action(array $ability): void
{ ?>
    <div class="agent2wp-hub-actions">
        <?php if (!$ability['protected']): ?>
            <form method="post">
                <?php wp_nonce_field('agent2wp_ability_hub_action'); ?>
                <input type="hidden" name="agent2wp_ability_hub_action" value="toggle_disabled" />
                <input type="hidden" name="ability_name" value="<?php echo esc_attr($ability['name']); ?>" />
                <button type="submit" class="action-btn">
                    <?php echo
                        esc_html(
                            $ability['disabled'] ? __('Enable', domain: 'agent2wp') : __('Disable', domain: 'agent2wp'),
                        )
                    ; ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
    <?php }

function agent2wp_render_ability_bulk_actions(string $position): void
{
    $suffix = $position === 'bottom' ? '2' : '';
    ?>
    <div class="tablenav <?php echo esc_attr($position); ?>">
        <div class="alignleft actions bulkactions">
            <label for="<?php echo
                esc_attr('agent2wp-bulk-action-selector-' . $position)
            ; ?>" class="screen-reader-text">
                <?php esc_html_e('Select bulk action', domain: 'agent2wp'); ?>
            </label>
            <select
                name="<?php echo esc_attr('bulk_action' . $suffix); ?>"
                id="<?php echo esc_attr('agent2wp-bulk-action-selector-' . $position); ?>"
                form="agent2wp-abilities-bulk"
            >
                <option value="-1"><?php esc_html_e('Bulk actions', domain: 'agent2wp'); ?></option>
                <option value="enable"><?php esc_html_e('Enable', domain: 'agent2wp'); ?></option>
                <option value="disable"><?php esc_html_e('Disable', domain: 'agent2wp'); ?></option>
            </select>
            <button type="submit" class="button action" form="agent2wp-abilities-bulk">
                <?php esc_html_e('Apply', domain: 'agent2wp'); ?>
            </button>
        </div>
        <br class="clear" />
    </div>
    <?php
}
