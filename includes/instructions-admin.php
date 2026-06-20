<?php

// SPDX-FileCopyrightText: 2026 Taibur Rahaman <https://github.com/Taibur-Rahaman>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Agent2Wp\Context;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Admin page for site-level context shown to connected agents. The saved
 * content is prepended to the Agent2Wp discover-abilities instructions, while
 * the read-only preview shows the system instructions without that user layer.
 */

const INSTRUCTIONS_ENABLED_OPTION = 'agent2wp_instructions_enabled';

const INSTRUCTIONS_CONTENT_OPTION = 'agent2wp_instructions_content';

const LEGACY_PRO_INSTRUCTIONS_ENABLED_OPTION = 'nvp_instructions_enabled';

const LEGACY_PRO_INSTRUCTIONS_CONTENT_OPTION = 'nvp_instructions_content';

const OPTION_MISSING = '__agent2wp_option_missing__';

function context_page_slug(): string
{
    return 'agent2wp-context';
}

/** @param array<string, scalar> $query */
function context_page_url(array $query = []): string
{
    return add_query_arg(array_merge(['page' => context_page_slug()], $query), admin_url('admin.php'));
}

function instructions_enabled_option_name(): string
{
    return INSTRUCTIONS_ENABLED_OPTION;
}

function instructions_content_option_name(): string
{
    return INSTRUCTIONS_CONTENT_OPTION;
}

function instructions_legacy_enabled_option_name(): string
{
    return LEGACY_PRO_INSTRUCTIONS_ENABLED_OPTION;
}

function instructions_legacy_content_option_name(): string
{
    return LEGACY_PRO_INSTRUCTIONS_CONTENT_OPTION;
}

function instructions_read_option_with_legacy(string $option_name, string $legacy_option_name, mixed $default): mixed
{
    /** @var mixed $value */
    $value = get_option($option_name, default_value: OPTION_MISSING);
    if ($value !== OPTION_MISSING) {
        return $value;
    }

    /** @var mixed $legacy_value */
    $legacy_value = get_option($legacy_option_name, default_value: OPTION_MISSING);
    if ($legacy_value !== OPTION_MISSING) {
        return $legacy_value;
    }

    return $default;
}

function instructions_update_enabled_value(string $value): void
{
    update_option(instructions_enabled_option_name(), $value);
    update_option(instructions_legacy_enabled_option_name(), $value);
}

function instructions_update_content(string $content): void
{
    update_option(instructions_content_option_name(), $content);
    update_option(instructions_legacy_content_option_name(), $content);
}

function legacy_pro_context_loaded(): bool
{
    return false;
}

function instructions_is_enabled(): bool
{
    return filter_var(
        instructions_read_option_with_legacy(
            option_name: instructions_enabled_option_name(),
            legacy_option_name: instructions_legacy_enabled_option_name(),
            default: true,
        ),
        FILTER_VALIDATE_BOOLEAN,
    );
}

function instructions_get_content(): string
{
    /** @var mixed $raw */
    $raw = instructions_read_option_with_legacy(
        option_name: instructions_content_option_name(),
        legacy_option_name: instructions_legacy_content_option_name(),
        default: '',
    );
    return is_string($raw) ? $raw : '';
}

function instructions_custom_injection_suppression_state(string $action = 'read'): bool
{
    static $suppressed = false;

    $previous = $suppressed;
    if ($action === 'suppress') {
        $suppressed = true;
    }
    if ($action === 'restore_enabled') {
        $suppressed = true;
    }
    if ($action === 'restore_disabled') {
        $suppressed = false;
    }

    return $previous;
}

function instructions_custom_injection_suppressed(): bool
{
    return instructions_custom_injection_suppression_state();
}

function instructions_post_string(string $key): string
{
    $raw = $_POST[$key] ?? '';
    if (!is_string($raw)) {
        return '';
    }

    return wp_unslash($raw);
}

function register_context_menu(): void
{
    if (!defined('AGENT2WP_VERSION')) {
        return;
    }

    add_submenu_page(
        parent_slug: 'agent2wp-connect',
        page_title: __('Agent2Wp Context', domain: 'agent2wp'),
        menu_title: __('Context', domain: 'agent2wp'),
        capability: \agent2wp_manage_capability(),
        menu_slug: context_page_slug(),
        callback: __NAMESPACE__ . '\\render_context_page',
    );
}

/** @return array{type:string,message:string}|null */
function context_handle_post(): ?array
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return null;
    }

    $action = instructions_post_string('agent2wp_context_action');
    if (!in_array($action, ['toggle_context', 'save_context'], strict: true)) {
        return null;
    }

    check_admin_referer('agent2wp_context');

    if (!\agent2wp_current_user_can_manage()) {
        wp_die(esc_html__('You are not allowed to manage context.', domain: 'agent2wp'));
    }

    if ($action === 'toggle_context') {
        return instructions_handle_toggle();
    }

    return instructions_handle_save();
}

/** @return array{type:string,message:string} */
function instructions_handle_toggle(): array
{
    $enable = instructions_post_string('instructions_enabled') === '1';
    // Store as string to avoid WordPress's update_option short-circuit when the
    // option doesn't yet exist: get_option returns false as the implicit old
    // value, and update_option(name, false) bails out before add_option runs.
    instructions_update_enabled_value($enable ? '1' : '0');

    return [
        'type' => 'success',
        'message' => $enable
            ? __('User context enabled for agents.', domain: 'agent2wp')
            : __('User context disabled for agents. Content is kept.', domain: 'agent2wp'),
    ];
}

/** @return array{type:string,message:string} */
function instructions_handle_save(): array
{
    $content = instructions_post_string('instructions_content');
    instructions_update_content($content);

    return [
        'type' => 'success',
        'message' => __('User context saved.', domain: 'agent2wp'),
    ];
}

function context_system_instructions_preview(): string
{
    if (!function_exists('agent2wp_build_server_instructions')) {
        return __('System instructions are unavailable until the base Agent2Wp plugin has loaded.', domain: 'agent2wp');
    }

    $previous = instructions_custom_injection_suppression_state(action: 'suppress');

    try {
        return (string) apply_filters(
            'agent2wp_discover_abilities_instructions',
            \agent2wp_build_server_instructions(),
        );
    } finally {
        instructions_custom_injection_suppression_state(action: $previous ? 'restore_enabled' : 'restore_disabled');
    }
}

function context_system_instructions_excerpt(string $instructions, int $line_count = 6): string
{
    if ($line_count < 1) {
        return '...';
    }

    $normalized = preg_replace(pattern: '/\r\n?/', replacement: "\n", subject: $instructions) ?? $instructions;
    $lines = explode(separator: "\n", string: ltrim($normalized, characters: "\n"));

    if (count($lines) <= $line_count) {
        return implode(separator: "\n", array: $lines);
    }

    $excerpt = array_slice($lines, offset: 0, length: $line_count);
    $excerpt[] = '...';

    return implode(separator: "\n", array: $excerpt);
}

function render_context_page(): void
{
    if (!\agent2wp_current_user_can_manage()) {
        return;
    }

    $notice = context_handle_post();

    if (function_exists('agent2wp_render_admin_header')) {
        agent2wp_render_admin_header();
    }

    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('Context', domain: 'agent2wp'); ?></h1>
        <hr class="wp-header-end">

        <p class="description agent2wp-context-intro"><?php esc_html_e(
            'Review the system instructions connected agents receive, then add site-specific context that should apply in every conversation.',
            domain: 'agent2wp',
        ); ?></p>

        <?php render_context_notice($notice); ?>
        <?php render_context_styles(); ?>
        <?php render_context_system_section(); ?>
        <?php render_user_context_section(); ?>
        <?php do_action('agent2wp_context_page_sections'); ?>
    </div>
    <?php
}

function render_context_styles(): void
{ ?>
    <style>
        .agent2wp-context-intro { margin-top:8px; max-width:800px; }
        .agent2wp-context-section { margin-top:24px; max-width:1100px; }
        .agent2wp-context-section h2 { margin-bottom:4px; font-size:16px; }
        .agent2wp-context-panel { background:#fff; border:1px solid #dcdcde; border-radius:12px; padding:16px 20px; margin-top:12px; }
        .agent2wp-context-status { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
        .agent2wp-context-status .status-text { display:flex; align-items:center; gap:10px; }
        .agent2wp-context-status .status-pill { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:999px; font-weight:600; font-size:12px; }
        .agent2wp-context-status .status-pill.is-on { background:#edf7ed; color:#0a5c1b; }
        .agent2wp-context-status .status-pill.is-off { background:#fcf0f1; color:#8a2424; }
        .agent2wp-context-status .status-hint { color:#50575e; margin:6px 0 0; max-width:680px; }
        .agent2wp-system-box summary { cursor:pointer; font-weight:600; }
        .agent2wp-system-preview-wrap { position:relative; margin-top:12px; }
        .agent2wp-system-preview { box-sizing:border-box; width:100%; max-height:none; overflow:auto; margin:0; padding:14px 16px; background:#f6f7f7; border:1px solid #dcdcde; border-radius:8px; white-space:pre-wrap; font-family:ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace; font-size:12px; line-height:1.55; }
        .agent2wp-system-preview.is-excerpt { margin-top:12px; }
        .agent2wp-system-details[open] + .agent2wp-system-preview.is-excerpt { display:none; }
        .agent2wp-context-guidance { margin-top:12px; color:#1d2327; }
        .agent2wp-context-guidance ul { list-style:disc; margin-left:20px; max-width:780px; }
        .agent2wp-context-guidance li { margin:4px 0; }
        .agent2wp-context-form { margin-top:12px; }
        .agent2wp-context-form textarea { font-family:ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace; font-size:13px; }
    </style>
    <?php }

function render_context_system_section(): void
{
    $preview = context_system_instructions_preview();
    $excerpt = context_system_instructions_excerpt($preview);
    ?>
    <section class="agent2wp-context-section" aria-labelledby="agent2wp-system-context-heading">
        <h2 id="agent2wp-system-context-heading"><?php esc_html_e('System context', domain: 'agent2wp'); ?></h2>
        <p class="description"><?php esc_html_e(
            'Read-only instructions Agent2Wp generates for this site, adapting them to the plugins you have active. Shown here for visibility and not editable from this page.',
            domain: 'agent2wp',
        ); ?></p>

        <div class="agent2wp-context-panel agent2wp-system-box">
            <details class="agent2wp-system-details">
                <summary><?php esc_html_e('Show full system context', domain: 'agent2wp'); ?></summary>
                <div class="agent2wp-system-preview-wrap" aria-label="<?php echo
                    esc_attr__('Read-only system context preview', domain: 'agent2wp')
                ; ?>">
                    <pre class="agent2wp-system-preview is-full"><?php echo esc_html($preview); ?></pre>
                </div>
            </details>
            <pre class="agent2wp-system-preview is-excerpt"><?php echo esc_html($excerpt); ?></pre>
        </div>
    </section>
    <?php
}

function render_user_context_section(): void
{
    $is_enabled = instructions_is_enabled();
    $content = instructions_get_content();
    $submit_value = $is_enabled ? '0' : '1';
    $toggle_label = $is_enabled
        ? __('Disable user context', domain: 'agent2wp')
        : __('Enable user context', domain: 'agent2wp');
    $toggle_class = $is_enabled ? 'button button-secondary' : 'button button-primary';
    $status_class = $is_enabled ? 'is-on' : 'is-off';
    $status_label = $is_enabled ? __('On', domain: 'agent2wp') : __('Off', domain: 'agent2wp');
    $hint = $is_enabled
        ? __('User-added context is prepended to the system context shown to agents.', domain: 'agent2wp')
        : __(
            'User-added context is hidden from agents. Content stays in place and is used again when you re-enable.',
            domain: 'agent2wp',
        );
    $form_url = context_page_url();
    ?>
    <section class="agent2wp-context-section" aria-labelledby="agent2wp-user-context-heading">
        <h2 id="agent2wp-user-context-heading"><?php esc_html_e('User context', domain: 'agent2wp'); ?></h2>
        <p class="description"><?php esc_html_e(
            'Additional instructions provided by this site owner for all connected agents.',
            domain: 'agent2wp',
        ); ?></p>

        <div class="agent2wp-context-panel agent2wp-context-status">
            <div>
                <div class="status-text">
                    <strong><?php esc_html_e('User context:', domain: 'agent2wp'); ?></strong>
                    <span class="status-pill <?php echo esc_attr($status_class); ?>"><?php

                    echo esc_html($status_label); ?></span>
                </div>
                <p class="status-hint"><?php echo esc_html($hint); ?></p>
            </div>
            <form method="post" action="<?php echo esc_url($form_url); ?>">
                <?php wp_nonce_field('agent2wp_context'); ?>
                <input type="hidden" name="agent2wp_context_action" value="toggle_context">
                <input type="hidden" name="instructions_enabled" value="<?php echo esc_attr($submit_value); ?>">
                <button type="submit" class="<?php echo esc_attr($toggle_class); ?>"><?php echo
                    esc_html($toggle_label)
                ; ?></button>
            </form>
        </div>

        <div class="agent2wp-context-panel agent2wp-context-guidance">
            <p style="margin-top:0;"><?php esc_html_e(
                'Stable context agents should apply on this site without asking again.',
                domain: 'agent2wp',
            ); ?></p>
            <ul>
                <li><?php esc_html_e(
                    'Site goals, brand voice, audience, and naming conventions.',
                    domain: 'agent2wp',
                ); ?></li>
                <li><?php esc_html_e(
                    'Constraints: what to avoid, what needs approval, and preferred workflows.',
                    domain: 'agent2wp',
                ); ?></li>
            </ul>
            <p style="margin-bottom:0;"><?php esc_html_e(
                'No passwords, API keys, private data, or one-off notes. Keep it stable and site-wide.',
                domain: 'agent2wp',
            ); ?></p>
        </div>

        <form method="post" action="<?php echo esc_url($form_url); ?>" class="agent2wp-context-form">
            <?php wp_nonce_field('agent2wp_context'); ?>
            <input type="hidden" name="agent2wp_context_action" value="save_context">
            <label for="instructions_content" class="screen-reader-text"><?php esc_html_e(
                'User context',
                domain: 'agent2wp',
            ); ?></label>
            <textarea
                id="instructions_content"
                name="instructions_content"
                rows="14"
                class="large-text code"
                placeholder="<?php echo
                    esc_attr__(
                        'Write site-level context for connected agents. Markdown is supported.',
                        domain: 'agent2wp',
                    )
                ; ?>"
            ><?php echo esc_textarea($content); ?></textarea>
            <p style="margin-top:8px;">
                <button type="submit" class="button button-primary"><?php esc_html_e(
                    'Save context',
                    domain: 'agent2wp',
                ); ?></button>
            </p>
        </form>
    </section>
    <?php
}

/** @param array{type:string,message:string}|null $notice */
function render_context_notice(?array $notice): void
{
    if ($notice === null) {
        return;
    }

    $type = in_array($notice['type'], ['success', 'warning', 'error', 'info'], strict: true) ? $notice['type'] : 'info';
    ?>
    <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible"><p><?php echo
        esc_html($notice['message'])
    ; ?></p></div>
    <?php
}

function boot_context_admin(): void
{
    add_action('admin_menu', callback: __NAMESPACE__ . '\\register_context_menu', priority: 12);
}
