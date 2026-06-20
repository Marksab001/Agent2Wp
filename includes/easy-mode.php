<?php

// SPDX-FileCopyrightText: 2026 Taibur Rahaman <https://github.com/Taibur-Rahaman>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

/**
 * Easy Mode — one-click setup for non-technical users.
 */

if (!defined('ABSPATH')) {
    exit();
}

const AGENT2WP_SETUP_COMPLETE_OPTION = 'agent2wp_setup_complete';

const AGENT2WP_EASY_MODE_OPTION = 'agent2wp_easy_mode';

const AGENT2WP_PENDING_AUTO_SETUP_OPTION = 'agent2wp_pending_auto_setup';

const AGENT2WP_CLIPBOARD_TRANSIENT_PREFIX = 'agent2wp_clipboard_';

const AGENT2WP_AUTO_SETUP_NOTICE_PREFIX = 'agent2wp_auto_setup_notice_';

function agent2wp_easy_mode_enabled(): bool
{
    return get_option(AGENT2WP_EASY_MODE_OPTION, default_value: '1') === '1';
}

function agent2wp_is_setup_complete(): bool
{
    return (
        get_option(AGENT2WP_SETUP_COMPLETE_OPTION, default_value: '0') === '1'
        && agent2wp_is_enabled()
        && agent2wp_get_mcp_passwords() !== []
    );
}

/**
 * @return array{complete: bool, enabled: bool, has_password: bool, dependency_ok: bool}
 */
function agent2wp_get_setup_status(): array
{
    return [
        'complete' => agent2wp_is_setup_complete(),
        'enabled' => agent2wp_is_enabled(),
        'has_password' => agent2wp_get_mcp_passwords() !== [],
        'dependency_ok' => agent2wp_get_mcp_dependency_error() === null,
    ];
}

/**
 * @return list<string>
 */
function agent2wp_easy_setup_step_labels(): array
{
    return [
        __('Turning on AI tools', domain: 'agent2wp'),
        __('Unlocking Expert Suite', domain: 'agent2wp'),
        __('Preparing workspace', domain: 'agent2wp'),
        __('Creating secure connection', domain: 'agent2wp'),
        __('Loading site rules', domain: 'agent2wp'),
    ];
}

/**
 * @return string|WP_Error Plaintext password on success.
 */
function agent2wp_create_mcp_application_password(int $user_id, string $label = 'Auto')
{
    $status = agent2wp_app_passwords_status();
    if (!$status['available']) {
        return new WP_Error('not_available', $status['message']);
    }

    $app_name = 'Agent2Wp: ' . trim($label);
    if ($app_name === 'Agent2Wp:') {
        $app_name = 'Agent2Wp Auto';
    }

    $existing = WP_Application_Passwords::get_user_application_passwords($user_id);
    $names = array_column($existing, 'name');
    if (in_array($app_name, $names, strict: true)) {
        $i = 2;
        while (in_array($app_name . ' ' . $i, $names, strict: true)) {
            $i++;
        }
        $app_name = $app_name . ' ' . $i;
    }

    $result = WP_Application_Passwords::create_new_application_password($user_id, ['name' => $app_name]);
    if (is_wp_error($result)) {
        return $result;
    }

    return $result[0];
}

function agent2wp_easy_enable_context(): void
{
    \Agent2Wp\Context\instructions_update_enabled_value('1');
}

function agent2wp_easy_seed_default_context(): void
{
    if (trim(\Agent2Wp\Context\instructions_get_content()) !== '') {
        return;
    }

    $default = implode("\n", [
        'Load the `site-rulesbook` skill before any site-building work.',
        '',
        'Rules summary:',
        '- Elementor: native free widgets + Containers only (no Inner Sections, Pro, HTML, or Atomic)',
        '- Header & footer: XPRO only — save templates to the XPRO library',
        '- Contact page: WPForms',
        '- Set homepage and primary menu explicitly; store IDs here after each milestone',
    ]);

    \Agent2Wp\Context\instructions_update_content($default);
}

/**
 * @return array{success: bool, password: ?string, reused_password: bool, messages: list<string>}|WP_Error
 */
function agent2wp_run_magic_setup(?int $user_id = null)
{
    $user_id = $user_id ?? get_current_user_id();
    if ($user_id <= 0) {
        return new WP_Error('no_user', __('You must be logged in to set up Agent2Wp.', domain: 'agent2wp'));
    }

    if (agent2wp_get_mcp_dependency_error() !== null) {
        $dependency_error = agent2wp_get_mcp_dependency_error();
        return new WP_Error(
            'dependency',
            $dependency_error instanceof WP_Error
                ? $dependency_error->get_error_message()
                : __('MCP dependencies are unavailable.', domain: 'agent2wp'),
        );
    }

    $messages = [];

    if (!agent2wp_enable_ai_abilities()) {
        return new WP_Error('enable_failed', __('Could not enable AI Abilities.', domain: 'agent2wp'));
    }
    $messages[] = __('AI Abilities turned on', domain: 'agent2wp');

    agent2wp_easy_enable_context();
    agent2wp_easy_seed_default_context();
    $messages[] = __('Agent memory and site rules ready', domain: 'agent2wp');

    if (!is_dir(AGENT2WP_SANDBOX_DIR)) {
        wp_mkdir_p(AGENT2WP_SANDBOX_DIR);
    }
    $messages[] = __('Workspace folder ready', domain: 'agent2wp');

    $password = null;
    $reused = false;
    if (agent2wp_get_mcp_passwords() !== []) {
        $reused = true;
        $messages[] = __('Using your existing connection password', domain: 'agent2wp');
    } else {
        $created = agent2wp_create_mcp_application_password($user_id, label: 'Auto');
        if (is_wp_error($created)) {
            return $created;
        }
        $password = $created;
        set_transient(AGENT2WP_CLIPBOARD_TRANSIENT_PREFIX . $user_id, $password, expiration: DAY_IN_SECONDS);
        $messages[] = __('New connection password created', domain: 'agent2wp');
    }

    update_option(AGENT2WP_SETUP_COMPLETE_OPTION, '1', autoload: false);
    delete_option(AGENT2WP_PENDING_AUTO_SETUP_OPTION);

    do_action('agent2wp_after_magic_setup', $user_id, $password);

    return [
        'success' => true,
        'password' => $password,
        'reused_password' => $reused,
        'messages' => $messages,
    ];
}

function agent2wp_get_clipboard_password(int $user_id): ?string
{
    $value = get_transient(AGENT2WP_CLIPBOARD_TRANSIENT_PREFIX . $user_id);
    return is_string($value) && $value !== '' ? $value : null;
}

function agent2wp_easy_connect_url(array $args = []): string
{
    return add_query_arg(array_merge(['page' => 'agent2wp-connect'], $args), admin_url('admin.php'));
}

/** @return array{redirect: string, new_password: bool} */
function agent2wp_easy_setup_redirect_args(array $result): array
{
    $args = ['agent2wp_setup' => 'ok'];
    $new_password = false;
    if (($result['password'] ?? null) !== null) {
        $args['agent2wp_new_pw'] = '1';
        $args['agent2wp_autocopy'] = '1';
        $new_password = true;
    }
    return ['redirect' => agent2wp_easy_connect_url($args), 'new_password' => $new_password];
}

function agent2wp_handle_easy_setup_post(): void
{
    if (($_POST['agent2wp_magic_setup'] ?? null) === null && ($_POST['agent2wp_reset_connection'] ?? null) === null) {
        return;
    }
    if (!agent2wp_current_user_can_manage()) {
        return;
    }

    if (($_POST['agent2wp_reset_connection'] ?? null) !== null) {
        check_admin_referer('agent2wp_reset_connection');
        foreach (agent2wp_get_mcp_passwords() as $pw) {
            $uuid = $pw['uuid'] ?? '';
            if (is_string($uuid) && $uuid !== '') {
                WP_Application_Passwords::delete_application_password(get_current_user_id(), $uuid);
            }
        }
        delete_option(AGENT2WP_SETUP_COMPLETE_OPTION);
        delete_transient(AGENT2WP_CLIPBOARD_TRANSIENT_PREFIX . get_current_user_id());
        wp_safe_redirect(agent2wp_easy_connect_url());
        exit();
    }

    check_admin_referer('agent2wp_magic_setup');

    $result = agent2wp_run_magic_setup();
    if (is_wp_error($result)) {
        wp_safe_redirect(agent2wp_easy_connect_url([
            'agent2wp_setup' => 'error',
            'agent2wp_setup_msg' => rawurlencode($result->get_error_message()),
        ]));
        exit();
    }

    wp_safe_redirect(agent2wp_easy_setup_redirect_args($result)['redirect']);
    exit();
}

function agent2wp_ajax_magic_setup(): void
{
    check_ajax_referer('agent2wp_magic_setup', 'nonce');
    if (!agent2wp_current_user_can_manage()) {
        wp_send_json_error(['message' => __('Permission denied.', domain: 'agent2wp')], status_code: 403);
    }

    $result = agent2wp_run_magic_setup();
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], status_code: 400);
    }

    wp_send_json_success(agent2wp_easy_setup_redirect_args($result));
}

function agent2wp_maybe_run_pending_auto_setup(): void
{
    if (get_option(AGENT2WP_PENDING_AUTO_SETUP_OPTION, default_value: '0') !== '1') {
        return;
    }
    if (!agent2wp_easy_mode_enabled() || !agent2wp_current_user_can_manage()) {
        return;
    }
    if (!is_admin() || wp_doing_ajax()) {
        return;
    }
    if (agent2wp_is_setup_complete()) {
        delete_option(AGENT2WP_PENDING_AUTO_SETUP_OPTION);
        return;
    }

    $page = agent2wp_admin_request_page();
    if ($page === 'agent2wp-connect') {
        return;
    }

    $result = agent2wp_run_magic_setup();
    if (!is_wp_error($result)) {
        set_transient(AGENT2WP_AUTO_SETUP_NOTICE_PREFIX . get_current_user_id(), '1', DAY_IN_SECONDS);
    }
}

function agent2wp_mark_pending_auto_setup(): void
{
    if (!agent2wp_easy_mode_enabled()) {
        return;
    }
    update_option(AGENT2WP_PENDING_AUTO_SETUP_OPTION, '1', autoload: false);
}

function agent2wp_render_auto_setup_admin_notice(): void
{
    if (!agent2wp_current_user_can_manage() || !agent2wp_easy_mode_enabled()) {
        return;
    }
    $user_id = get_current_user_id();
    if (get_transient(AGENT2WP_AUTO_SETUP_NOTICE_PREFIX . $user_id) !== '1') {
        return;
    }
    delete_transient(AGENT2WP_AUTO_SETUP_NOTICE_PREFIX . $user_id);

    $url = agent2wp_easy_connect_url(['agent2wp_autocopy' => '1']);
    ?>
    <div class="notice notice-success is-dismissible">
        <p>
            <strong><?php esc_html_e('Agent2Wp is ready!', domain: 'agent2wp'); ?></strong>
            <?php esc_html_e('We set everything up in the background.', domain: 'agent2wp'); ?>
            <a href="<?php echo esc_url($url); ?>" class="button button-primary" style="margin-left:8px;">
                <?php esc_html_e('Copy for AI', domain: 'agent2wp'); ?>
            </a>
        </p>
    </div>
    <?php
}

function agent2wp_render_setup_needed_admin_notice(): void
{
    if (!agent2wp_current_user_can_manage() || !agent2wp_easy_mode_enabled()) {
        return;
    }
    if (agent2wp_is_setup_complete()) {
        return;
    }
    $page = agent2wp_admin_request_page();
    if ($page === 'agent2wp-connect') {
        return;
    }
    if (agent2wp_get_mcp_dependency_error() !== null) {
        return;
    }
    ?>
    <div class="notice notice-info">
        <p>
            <strong><?php esc_html_e('Agent2Wp', domain: 'agent2wp'); ?></strong>
            <?php esc_html_e('One click to connect AI to your site.', domain: 'agent2wp'); ?>
            <a href="<?php echo
                esc_url(agent2wp_easy_connect_url())
            ; ?>" class="button button-primary" style="margin-left:8px;">
                <?php esc_html_e('Get started', domain: 'agent2wp'); ?>
            </a>
        </p>
    </div>
    <?php
}

function agent2wp_register_easy_dashboard_widget(): void
{
    if (!agent2wp_easy_mode_enabled() || !agent2wp_current_user_can_manage()) {
        return;
    }
    wp_add_dashboard_widget(
        'agent2wp_easy_dashboard',
        __('Agent2Wp', domain: 'agent2wp'),
        'agent2wp_render_easy_dashboard_widget',
    );
}

function agent2wp_render_easy_dashboard_widget(): void
{
    $status = agent2wp_get_setup_status();
    if (!$status['complete']) {
        echo '<p>' . esc_html__('Connect AI to WordPress in one click.', domain: 'agent2wp') . '</p>';
        echo '<a class="agent2wp-easy-dashboard-cta" href="' . esc_url(agent2wp_easy_connect_url()) . '">';
        esc_html_e('Start Agent2Wp', domain: 'agent2wp');
        echo '</a>';
        return;
    }

    echo '<p><strong style="color:#15803d;">' . esc_html__('Ready', domain: 'agent2wp') . '</strong> — ';
    esc_html_e('AI can connect to this site.', domain: 'agent2wp');
    echo '</p>';
    echo
        '<a class="agent2wp-easy-dashboard-cta" href="'
            . esc_url(agent2wp_easy_connect_url(['agent2wp_autocopy' => '1']))
            . '">'
    ;
    esc_html_e('Copy for AI', domain: 'agent2wp');
    echo '</a>';
}

function agent2wp_filter_easy_mode_submenus(): void
{
    if (!agent2wp_easy_mode_enabled()) {
        return;
    }
    if (agent2wp_admin_request_flag('agent2wp_advanced')) {
        return;
    }

    // @mago-expect lint:no-global -- WordPress admin submenu API requires the global.
    global $submenu;
    if (!is_array($submenu) || !is_array($submenu['agent2wp-connect'] ?? null)) {
        return;
    }

    $keep = ['agent2wp-connect'];
    $filtered = [];
    foreach ($submenu['agent2wp-connect'] as $entry) {
        if (in_array($entry[2] ?? '', $keep, strict: true)) {
            $filtered[] = $entry;
        }
    }
    $submenu['agent2wp-connect'] = $filtered;
}

function agent2wp_enqueue_easy_mode_assets(string $hook): void
{
    if ($hook !== 'toplevel_page_agent2wp-connect') {
        return;
    }
    if (!agent2wp_easy_mode_enabled()) {
        return;
    }

    wp_enqueue_style(
        'agent2wp-easy-mode',
        (string) AGENT2WP_PLUGIN_URL . 'includes/assets/easy-mode.css',
        [],
        AGENT2WP_VERSION,
    );
    wp_enqueue_script(
        'agent2wp-easy-mode',
        (string) AGENT2WP_PLUGIN_URL . 'includes/assets/easy-mode.js',
        [],
        AGENT2WP_VERSION,
        args: true,
    );

    $status = agent2wp_get_setup_status();
    wp_localize_script('agent2wp-easy-mode', 'agent2wpEasy', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('agent2wp_magic_setup'),
        'readyUrl' => agent2wp_easy_connect_url(['agent2wp_setup' => 'ok', 'agent2wp_autocopy' => '1']),
        'setupSteps' => agent2wp_easy_setup_step_labels(),
        'copiedLabel' => __('Copied!', domain: 'agent2wp'),
        'errorLabel' => __('Setup failed. Please try again.', domain: 'agent2wp'),
        'autoCopy' => agent2wp_admin_request_flag('agent2wp_autocopy') && $status['complete'],
    ]);
}

function agent2wp_render_easy_connect_page(): void
{
    $status = agent2wp_get_setup_status();
    $dependency_error = agent2wp_get_mcp_dependency_error();
    $user_id = get_current_user_id();
    $username = wp_get_current_user()->user_login;
    $rest_url = rest_url('mcp/agent2wp');
    $mcp_name = agent2wp_get_mcp_server_name_default();
    $clipboard_pw = agent2wp_get_clipboard_password($user_id);
    $setup_result = agent2wp_admin_request_get_string('agent2wp_setup');
    $step = $status['complete'] ? 2 : 1;

    $display_password = $clipboard_pw ?? 'YOUR-APP-PASSWORD';
    $paste_text = agent2wp_build_paste_to_agent_paragraph($rest_url, $username, $display_password, $mcp_name);

    agent2wp_render_admin_header();
    ?>
    <div class="wrap agent2wp-easy-wrap">
        <nav class="agent2wp-easy-steps" aria-label="<?php esc_attr_e('Setup steps', domain: 'agent2wp'); ?>">
            <div class="agent2wp-easy-steps__item <?php echo $step === 1 ? 'is-current' : 'is-done'; ?>">
                <?php esc_html_e('1 · Start', domain: 'agent2wp'); ?>
            </div>
            <div class="agent2wp-easy-steps__item <?php echo $step === 2 ? 'is-current' : ''; ?>">
                <?php esc_html_e('2 · Connect AI', domain: 'agent2wp'); ?>
            </div>
        </nav>

        <?php if ($dependency_error !== null): ?>
            <?php agent2wp_render_mcp_dependency_inline_notice($dependency_error); ?>
        <?php elseif ($setup_result === 'ok'): ?>
            <div class="agent2wp-easy-alert agent2wp-easy-alert--success">
                <?php esc_html_e('Done! Everything runs automatically in the background now.', domain: 'agent2wp'); ?>
            </div>
        <?php elseif ($setup_result === 'error'): ?>
            <div class="agent2wp-easy-alert agent2wp-easy-alert--error">
                <?php echo
                    esc_html(rawurldecode(agent2wp_admin_request_get_string('agent2wp_setup_msg', __(
                        'Setup failed.',
                        domain: 'agent2wp',
                    ))))
                ; ?>
            </div>
        <?php endif; ?>

        <header class="agent2wp-easy-hero">
            <?php if ($status['complete']): ?>
                <span class="agent2wp-easy-hero__badge"><?php esc_html_e('Live', domain: 'agent2wp'); ?></span>
            <?php endif; ?>
            <h1><?php esc_html_e('Agent2Wp', domain: 'agent2wp'); ?></h1>
            <p class="agent2wp-easy-hero__tagline">
                <?php

                echo
                    $status['complete']
                        ? esc_html__('Tap once to copy — paste into Claude or Cursor. That\'s it.', domain: 'agent2wp')
                        : esc_html__('One button starts everything. No settings. No tech talk.', domain: 'agent2wp')
                ;
                ?>
            </p>
        </header>

        <?php if (!$status['complete']): ?>
            <div class="agent2wp-easy-card agent2wp-easy-card--primary">
                <h2><?php esc_html_e('Start', domain: 'agent2wp'); ?></h2>
                <p><?php esc_html_e(
                    'We turn on AI tools, Expert Suite, memory, workspace, and a secure password — automatically.',
                    domain: 'agent2wp',
                ); ?></p>

                <ul class="agent2wp-easy-progress" id="agent2wp-easy-progress" hidden>
                    <?php foreach (agent2wp_easy_setup_step_labels() as $label): ?>
                        <li class="agent2wp-easy-progress__item"><?php echo esc_html($label); ?></li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($dependency_error === null): ?>
                    <form method="post" id="agent2wp-easy-start-form">
                        <?php wp_nonce_field('agent2wp_magic_setup'); ?>
                        <button type="submit" name="agent2wp_magic_setup" value="1" class="agent2wp-easy-btn agent2wp-easy-btn--start" id="agent2wp-easy-start-btn">
                            <span class="agent2wp-easy-btn__spinner" aria-hidden="true"></span>
                            <?php esc_html_e('Start Agent2Wp', domain: 'agent2wp'); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="agent2wp-easy-card agent2wp-easy-card--ready">
                <div class="agent2wp-easy-status">
                    <span class="agent2wp-easy-status__dot" aria-hidden="true"></span>
                    <strong><?php esc_html_e('Running in the background', domain: 'agent2wp'); ?></strong>
                </div>
                <ul class="agent2wp-easy-checklist">
                    <li><?php esc_html_e('AI tools', domain: 'agent2wp'); ?></li>
                    <li><?php esc_html_e('Expert Suite', domain: 'agent2wp'); ?></li>
                    <li><?php esc_html_e('Site memory', domain: 'agent2wp'); ?></li>
                    <li><?php esc_html_e('Connection', domain: 'agent2wp'); ?></li>
                </ul>
            </div>

            <div class="agent2wp-easy-card agent2wp-easy-card--action">
                <h2><?php esc_html_e('Connect AI', domain: 'agent2wp'); ?></h2>
                <p><?php esc_html_e('Copy the message below and paste it into your AI app.', domain: 'agent2wp'); ?></p>

                <?php if ($clipboard_pw !== null): ?>
                    <div class="agent2wp-easy-password-box">
                        <code><?php echo esc_html($clipboard_pw); ?></code>
                        <button type="button" class="agent2wp-easy-btn agent2wp-easy-btn--ghost" id="agent2wp-easy-copy-pw" data-password="<?php echo
                            esc_attr($clipboard_pw)
                        ; ?>" style="width:auto;min-height:44px;padding:10px 18px;">
                            <?php esc_html_e('Copy password', domain: 'agent2wp'); ?>
                        </button>
                    </div>
                <?php elseif ($status['has_password']): ?>
                    <p class="agent2wp-easy-hint agent2wp-easy-hint--muted">
                        <?php esc_html_e(
                            'Connection is active. Reset below if you need a fresh password in the copied message.',
                            domain: 'agent2wp',
                        ); ?>
                    </p>
                <?php endif; ?>

                <textarea id="agent2wp-easy-paste" class="agent2wp-easy-paste" readonly aria-hidden="true"><?php echo
                    esc_textarea($paste_text)
                ; ?></textarea>

                <button type="button" class="agent2wp-easy-btn agent2wp-easy-btn--copy" id="agent2wp-easy-copy-btn">
                    <?php esc_html_e('Copy for AI', domain: 'agent2wp'); ?>
                </button>
                <p class="agent2wp-easy-hint" id="agent2wp-easy-copy-hint" hidden>
                    <?php esc_html_e('Copied! Open Claude or Cursor and paste.', domain: 'agent2wp'); ?>
                </p>

                <div class="agent2wp-easy-clients">
                    <span>Claude</span>
                    <span>Cursor</span>
                    <span>Copilot</span>
                    <span>ChatGPT</span>
                </div>

                <form method="post" style="margin-top:18px;text-align:center;">
                    <?php wp_nonce_field('agent2wp_reset_connection'); ?>
                    <button type="submit" name="agent2wp_reset_connection" value="1" class="button button-link" onclick="return confirm(<?php echo
                        wp_json_encode(__('Get a new connection password?', domain: 'agent2wp'))
                    ; ?>);">
                        <?php esc_html_e('Reset connection', domain: 'agent2wp'); ?>
                    </button>
                </form>
            </div>

            <div class="agent2wp-easy-card agent2wp-easy-card--muted">
                <h3><?php esc_html_e('What AI does automatically', domain: 'agent2wp'); ?></h3>
                <p><?php esc_html_e(
                    'Builds pages, menus, Elementor layouts, XPRO header/footer, and contact forms — following your site rules. You just chat with your AI.',
                    domain: 'agent2wp',
                ); ?></p>
            </div>
        <?php endif; ?>

        <p class="agent2wp-easy-advanced-link">
            <a href="<?php echo esc_url(agent2wp_easy_connect_url(['agent2wp_advanced' => '1'])); ?>">
                <?php esc_html_e('Advanced settings →', domain: 'agent2wp'); ?>
            </a>
        </p>
    </div>
    <?php
}

add_action('admin_init', 'agent2wp_handle_easy_setup_post', priority: 5);
add_action('admin_init', 'agent2wp_maybe_run_pending_auto_setup', priority: 20);
add_action('admin_menu', 'agent2wp_filter_easy_mode_submenus', priority: 999);
add_action('admin_enqueue_scripts', 'agent2wp_enqueue_easy_mode_assets');
add_action('wp_ajax_agent2wp_magic_setup', 'agent2wp_ajax_magic_setup');
add_action('admin_notices', 'agent2wp_render_auto_setup_admin_notice');
add_action('admin_notices', 'agent2wp_render_setup_needed_admin_notice');
add_action('wp_dashboard_setup', 'agent2wp_register_easy_dashboard_widget');
