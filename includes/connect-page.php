<?php

// SPDX-FileCopyrightText: 2026 Taibur Rahaman <https://github.com/Taibur-Rahaman>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

/**
 * Dashboard connect page — creates application passwords and shows MCP config samples.
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Enable AI Abilities for the current site domain.
 */
function agent2wp_enable_ai_abilities(): bool
{
    if (function_exists('agent2wp_get_mcp_dependency_error') && agent2wp_get_mcp_dependency_error() !== null) {
        return false;
    }

    update_option(option: 'agent2wp_ai_abilities_enabled', value: '1');
    update_option(option: 'agent2wp_ai_abilities_domain', value: (string) wp_parse_url(home_url(), PHP_URL_HOST));
    return true;
}

/**
 * Disable AI Abilities and clear the domain lock.
 */
function agent2wp_disable_ai_abilities(): bool
{
    update_option(option: 'agent2wp_ai_abilities_enabled', value: '0');
    delete_option('agent2wp_ai_abilities_domain');
    return true;
}

/**
 * Handle the enable/disable AI Abilities toggle submission.
 * Returns true on save, null when no submission.
 */
function agent2wp_handle_toggle_enabled(): ?bool
{
    if (($_POST['agent2wp_submit'] ?? null) === null) {
        return null;
    }
    if (!agent2wp_current_user_can_manage()) {
        return null;
    }

    check_admin_referer('agent2wp_settings');

    $enabled = ($_POST['agent2wp_ai_abilities_enabled'] ?? null) !== null;
    return $enabled ? agent2wp_enable_ai_abilities() : agent2wp_disable_ai_abilities();
}

/**
 * Handle the admin-bar AI Abilities toggle.
 */
function agent2wp_handle_admin_bar_toggle(): void
{
    if (!agent2wp_current_user_can_manage()) {
        wp_die(esc_html__('You are not allowed to manage Agent2Wp settings.', domain: 'agent2wp'));
    }

    check_admin_referer('agent2wp_toggle_ai_abilities');

    $target = sanitize_key(agent2wp_admin_request_get_string('agent2wp_target'));
    $result = null;
    if ($target === 'on') {
        $result = agent2wp_enable_ai_abilities();
    }
    if ($target === 'off') {
        $result = agent2wp_disable_ai_abilities();
    }

    $redirect = wp_get_referer();
    if (!is_string($redirect) || $redirect === '') {
        $redirect = admin_url('admin.php?page=agent2wp-connect');
    }

    $redirect = add_query_arg([
        'agent2wp_toggle_result' => $result === true ? $target : 'failed',
    ], $redirect);

    wp_safe_redirect($redirect);
    exit();
}

function agent2wp_render_enable_toggle(): void
{
    $enabled = agent2wp_is_enabled();
    $dependency_error = function_exists('agent2wp_get_mcp_dependency_error')
        ? agent2wp_get_mcp_dependency_error()
        : null;
    $toggle_disabled = $dependency_error !== null && !$enabled;
    $submit_attributes = $toggle_disabled ? ['disabled' => 'disabled'] : [];
    $looks_production = agent2wp_looks_like_production();
    ?>
    <h2 class="agent2wp-step-heading">
        <span class="agent2wp-step-badge">1</span>
        <?php esc_html_e('Enable AI Abilities', domain: 'agent2wp'); ?>
    </h2>
    <form method="post" action="" id="agent2wp-settings-form" style="margin: 16px 0 0;">
        <?php wp_nonce_field('agent2wp_settings'); ?>
        <label style="display:flex; align-items:center; gap:10px; font-size:16px; font-weight:600; color:#1d2327; margin:0 0 12px;">
            <input type="checkbox" name="agent2wp_ai_abilities_enabled" value="1" id="agent2wp-enable-checkbox" style="width:18px; height:18px;" <?php checked(
                checked: $enabled,
                current: true,
            ); ?> <?php disabled($toggle_disabled); ?> />
            <span><?php esc_html_e('Turn on AI Abilities for this site', domain: 'agent2wp'); ?></span>
        </label>
        <p class="description" style="margin:0 0 8px;">
            <strong style="color:#d63638;"><?php esc_html_e('Security note:', domain: 'agent2wp'); ?></strong>
            <?php esc_html_e(
                'When enabled, AI agents can execute PHP code and perform filesystem operations on this site. For development and staging environments only. Always keep backups.',
                domain: 'agent2wp',
            ); ?>
        </p>
        <p class="description" style="margin:0 0 14px;">
            <?php esc_html_e(
                'Use Agent2Wp with a capable AI model and set your client to ask for confirmation before every action. Read what the agent is about to do before approving.',
                domain: 'agent2wp',
            ); ?>
        </p>
        <?php submit_button(
            text: __('Save Settings', domain: 'agent2wp'),
            type: 'primary',
            name: 'agent2wp_submit',
            wrap: false,
            other_attributes: $submit_attributes,
        ); ?>
    </form>
    <script>
    document.getElementById('agent2wp-settings-form').addEventListener('submit', function (e) {
        var cb = document.getElementById('agent2wp-enable-checkbox');
        if (cb.checked && !cb.defaultChecked) {
            var msg = <?php echo
                wp_json_encode(
                    $looks_production
                        ? __(
                            'This looks like a production site. The plugin can stay installed here, but AI Abilities are not meant for live sites: enable them only on a staging or development copy. Continue anyway?',
                            domain: 'agent2wp',
                        )
                        : __(
                            'AI agents will be able to execute PHP code and access the filesystem. For development and staging environments only. Continue?',
                            domain: 'agent2wp',
                        ),
                )
            ; ?>;
            if (!confirm(msg)) {
                e.preventDefault();
            }
        }
    });
    </script>
    <?php
}

/**
 * Render the production-site warning banner above the enable toggle.
 *
 * Shown only when: AI Abilities are currently enabled AND the site looks like production
 * AND the current user has not dismissed the warning.
 */
function agent2wp_render_production_warning(): void
{
    if (!agent2wp_is_enabled()) {
        return;
    }
    if (!agent2wp_looks_like_production()) {
        return;
    }
    if (agent2wp_production_warning_dismissed()) {
        return;
    }
    ?>
    <div class="agent2wp-production-warning" role="alert">
        <p>
            <strong><?php esc_html_e('⚠️ This looks like a production site.', domain: 'agent2wp'); ?></strong>
            <?php esc_html_e(
                'Keeping the plugin installed here is fine, but AI Abilities should only be active on a staging or development copy. Make your changes there, then deploy the result the regular way. On production, keep AI Abilities off.',
                domain: 'agent2wp',
            ); ?>
        </p>
        <form method="post" style="margin:0;">
            <?php wp_nonce_field('agent2wp_dismiss_production_warning'); ?>
            <button type="submit" name="agent2wp_dismiss_production_warning" class="button button-small">
                <?php esc_html_e('Dismiss', domain: 'agent2wp'); ?>
            </button>
        </form>
    </div>
    <?php
}

/**
 * Compute the default MCP server name from the current site host.
 *
 * Capped at 25 characters total ("agent2wp-" prefix + up to 16 chars of host slug)
 * because some MCP clients reject longer server names. Used as the placeholder default
 * when no name has been saved by the user.
 */
function agent2wp_get_mcp_server_name_default(): string
{
    /** @var string $site_host */
    $site_host = wp_parse_url(home_url(), PHP_URL_HOST) ?? 'wordpress';
    $site_slug = (string) preg_replace(pattern: '/^www\./', replacement: '', subject: $site_host);
    $site_slug = (string) preg_replace(pattern: '/[^a-z0-9-]+/', replacement: '-', subject: strtolower($site_slug));
    $site_slug = trim($site_slug, characters: '-');
    $site_slug = substr($site_slug, offset: 0, length: 16);
    $site_slug = rtrim($site_slug, characters: '-');
    return 'agent2wp-' . $site_slug;
}

/**
 * Handle the "use existing password" form submission.
 *
 * Returns the pasted plaintext value (only for the current request — never persisted),
 * a WP_Error on validation failure, or null when no submission.
 *
 * @return string|WP_Error|null
 */
function agent2wp_handle_use_existing_password()
{
    if (($_POST['agent2wp_use_existing_password'] ?? null) === null) {
        return null;
    }

    if (!agent2wp_current_user_can_manage()) {
        return new WP_Error('forbidden', __(
            'You do not have permission to use application passwords.',
            domain: 'agent2wp',
        ));
    }

    check_admin_referer('agent2wp_use_existing_password');

    $raw = $_POST['agent2wp_existing_password'] ?? '';
    $value = is_string($raw) ? trim($raw) : '';
    if ($value === '') {
        return new WP_Error('empty', __('Paste the application password value before submitting.', domain: 'agent2wp'));
    }
    if (strlen($value) < 16) {
        return new WP_Error('too_short', __(
            'That does not look like an application password. WordPress application passwords are at least 16 characters long.',
            domain: 'agent2wp',
        ));
    }
    return $value;
}

/**
 * Handle the create-password form submission.
 * Returns the plaintext password on success, a WP_Error on failure, or null when no submission.
 *
 * @return string|WP_Error|null
 */
function agent2wp_handle_create_password()
{
    if (($_POST['agent2wp_create_password'] ?? null) === null) {
        return null;
    }

    if (!agent2wp_current_user_can_manage()) {
        return new WP_Error('forbidden', __(
            'You do not have permission to create application passwords.',
            domain: 'agent2wp',
        ));
    }

    check_admin_referer('agent2wp_create_password');

    $status = agent2wp_app_passwords_status();
    if (!$status['available']) {
        return new WP_Error('not_available', $status['message']);
    }

    $user_id = get_current_user_id();
    $raw_name = $_POST['agent2wp_password_name'] ?? '';
    $input_name = is_string($raw_name) ? trim($raw_name) : '';
    $app_name = $input_name !== '' ? 'Agent2Wp: ' . $input_name : 'Agent2Wp';

    // Avoid duplicate names — append a counter if one already exists.
    $existing = WP_Application_Passwords::get_user_application_passwords($user_id);
    $names = array_column($existing, 'name');
    if (in_array(needle: $app_name, haystack: $names, strict: true)) {
        $i = 2;
        while (in_array(needle: $app_name . ' ' . $i, haystack: $names, strict: true)) {
            $i++;
        }
        $app_name = $app_name . ' ' . $i;
    }

    $result = WP_Application_Passwords::create_new_application_password($user_id, ['name' => $app_name]);

    if (is_wp_error($result)) {
        return $result;
    }

    // $result[0] is the plaintext password.
    return $result[0];
}

/**
 * Handle the revoke-password form submission. Redirects on success.
 * Called from admin_init so headers have not been sent yet.
 */
function agent2wp_handle_revoke_password(): void
{
    if (($_POST['agent2wp_revoke_password'] ?? null) === null) {
        return;
    }

    if (!agent2wp_current_user_can_manage()) {
        return;
    }

    $uuid = sanitize_text_field(wp_unslash((string) ($_POST['agent2wp_revoke_uuid'] ?? '')));
    if ($uuid === '') {
        return;
    }

    check_admin_referer('agent2wp_revoke_password_' . $uuid);

    $user_id = get_current_user_id();
    WP_Application_Passwords::delete_application_password($user_id, $uuid);

    wp_safe_redirect(admin_url('admin.php?page=agent2wp-connect&agent2wp_result=revoked'));
    exit();
}

/**
 * Return all application passwords for the current user whose name begins with "Agent2Wp".
 *
 * @return array<int, array<string, mixed>>
 */
function agent2wp_get_mcp_passwords(): array
{
    $user_id = get_current_user_id();
    $all = WP_Application_Passwords::get_user_application_passwords($user_id);
    return array_values(array_filter($all, static fn($item) => str_starts_with($item['name'], 'Agent2Wp')));
}

/**
 * Render a single password row for the passwords table.
 *
 * @param array<string, mixed> $pw        Password item from WP_Application_Passwords.
 * @param string               $dt_format Date/time format string.
 */
function agent2wp_render_password_row(array $pw, string $dt_format): void
{
    $uuid = (string) ($pw['uuid'] ?? '');
    $name = (string) ($pw['name'] ?? '');
    $created_date = ($pw['created'] ?? null) !== null ? wp_date($dt_format, (int) $pw['created']) : false;
    $created = $created_date !== false ? $created_date : __('Unknown', domain: 'agent2wp');
    $last_used_date = ($pw['last_used'] ?? null) !== null ? wp_date($dt_format, (int) $pw['last_used']) : false;
    $last_used = $last_used_date !== false ? $last_used_date : __('Never', domain: 'agent2wp');
    $revoke_nonce = (string) wp_create_nonce('agent2wp_revoke_password_' . $uuid);
    ?>
    <tr>
        <td><strong><?php echo esc_html($name); ?></strong></td>
        <td><?php echo esc_html($created); ?></td>
        <td><?php echo esc_html($last_used); ?></td>
        <td>
            <form method="post" style="margin:0;" onsubmit="return confirm('<?php echo
                esc_js(__('Revoke this password? Any clients using it will lose access.', domain: 'agent2wp'))
            ; ?>');">
                <input type="hidden" name="agent2wp_revoke_uuid" value="<?php echo esc_attr($uuid); ?>" />
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($revoke_nonce); ?>" />
                <button type="submit" name="agent2wp_revoke_password" class="button button-small agent2wp-revoke-btn"><?php esc_html_e(
                    'Revoke',
                    domain: 'agent2wp',
                ); ?></button>
            </form>
        </td>
    </tr>
    <?php
}

/**
 * Render the "Step 2 — Application Password" card.
 *
 * Just the generate button (with a collapsible name input) and a success notice after generation.
 * The list of existing passwords lives in the separate manage section at the bottom of the page.
 */
// Complexity is inherent: this is a single HTML template whose branches (password availability,
// newly generated vs. pasted vs. no password, has-existing toggles, error notices) each gate a
// distinct piece of inline markup. Splitting them into helpers would fragment one cohesive view.
// @mago-expect lint:cyclomatic-complexity
function agent2wp_render_password_step(
    ?string $new_password,
    ?string $existing_password = null,
    ?WP_Error $existing_error = null,
): void {
    $pw_status = agent2wp_app_passwords_status();
    $has_existing = agent2wp_get_mcp_passwords() !== [];
    $existing_section_open = $existing_password !== null || $existing_error !== null;
    ?>
    <h2 class="agent2wp-step-heading">
        <span class="agent2wp-step-badge">2</span>
        <?php esc_html_e('Application Password', domain: 'agent2wp'); ?>
    </h2>
    <p class="description" style="margin:0 0 12px;">
        <?php esc_html_e(
            'Generate an application password that your AI client will use to authenticate with WordPress. The password is embedded into the connection text in step 3.',
            domain: 'agent2wp',
        ); ?>
    </p>

    <?php if (!$pw_status['available']): ?>
        <div class="notice notice-error inline" style="margin:12px 0 16px;">
            <p><strong><?php echo esc_html($pw_status['message']); ?></strong></p>
            <?php if ($pw_status['reason'] === 'unsupported' && agent2wp_likely_local_http()): ?>
                <p style="margin:8px 0 0;">
                    <?php esc_html_e(
                        'This site is on a local hostname over HTTP. Add this line to your wp-config.php (above the "/* That\'s all" comment), then reload:',
                        domain: 'agent2wp',
                    ); ?>
                </p>
                <pre style="background:#f6f7f7; border:1px solid #c3c4c7; padding:8px 12px; margin:6px 0 0; font-size:13px; border-radius:3px;">define('WP_ENVIRONMENT_TYPE', 'local');</pre>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($new_password !== null): ?>
        <div class="notice notice-success inline" style="margin:8px 0 16px;">
            <p style="margin:0 0 8px;"><?php esc_html_e(
                'Application password generated. It is now embedded in the connection text in step 3. Save it somewhere safe: it will not be shown in full again.',
                domain: 'agent2wp',
            ); ?></p>
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <code id="agent2wp-new-pw-value" style="font-size:14px; font-weight:600; padding:6px 10px; background:#fff; border:1px solid #c3c4c7; border-radius:3px;"><?php echo
                    esc_html($new_password)
                ; ?></code>
                <button type="button" class="button button-small" onclick="agent2wpCopy('agent2wp-new-pw-value', this)">
                    <?php esc_html_e('Copy password only', domain: 'agent2wp'); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($new_password === null && $existing_password !== null): ?>
        <div class="notice notice-success inline" style="margin:8px 0 16px;">
            <p style="margin:0;"><?php esc_html_e(
                'Password accepted. It is now embedded in the connection text in step 3.',
                domain: 'agent2wp',
            ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" style="margin: 0;">
        <?php wp_nonce_field('agent2wp_create_password'); ?>
        <?php if (!$has_existing): ?>
            <p style="margin:0 0 10px;">
                <button
                    type="button"
                    class="button-link"
                    id="agent2wp-password-name-toggle"
                    aria-expanded="false"
                    aria-controls="agent2wp-password-name-field"
                    onclick="agent2wpTogglePasswordName(this)"
                ><?php esc_html_e('Customize password name (optional)', domain: 'agent2wp'); ?></button>
            </p>
        <?php endif; ?>
        <div
            id="agent2wp-password-name-field"
            <?php echo $has_existing ? '' : 'hidden'; ?>
            style="margin: 0 0 12px; <?php echo $has_existing ? '' : 'display:none;'; ?>"
        >
            <label for="agent2wp-password-name" style="display:block; margin-bottom:4px;">
                <strong><?php esc_html_e('Name', domain: 'agent2wp'); ?></strong>
            </label>
            <input
                type="text"
                id="agent2wp-password-name"
                name="agent2wp_password_name"
                placeholder="<?php esc_attr_e('e.g. Cursor on laptop, Claude Desktop', domain: 'agent2wp'); ?>"
                style="width:300px;"
                class="regular-text"
                maxlength="70"
            />
            <p class="description" style="margin-top:4px;">
                <?php esc_html_e(
                    'A label to identify this credential later. Leave blank to use "Agent2Wp".',
                    domain: 'agent2wp',
                ); ?>
            </p>
        </div>
        <button
            type="submit"
            name="agent2wp_create_password"
            class="button button-primary"
            <?php echo !$pw_status['available'] ? 'disabled' : ''; ?>>
            <?php echo
                $has_existing
                    ? esc_html__('Generate another application password', domain: 'agent2wp')
                    : esc_html__('Generate application password', domain: 'agent2wp')
            ; ?>
        </button>
    </form>

    <p style="margin:14px 0 4px;">
        <button
            type="button"
            class="button-link"
            id="agent2wp-use-existing-toggle"
            aria-expanded="<?php echo $existing_section_open ? 'true' : 'false'; ?>"
            aria-controls="agent2wp-use-existing-field"
            onclick="agent2wpToggleUseExisting(this)"
        ><?php esc_html_e('I already have an application password', domain: 'agent2wp'); ?></button>
    </p>
    <div
        id="agent2wp-use-existing-field"
        <?php echo $existing_section_open ? '' : 'hidden'; ?>
        style="margin:6px 0 0; <?php echo $existing_section_open ? '' : 'display:none;'; ?>"
    >
        <form method="post" style="margin:0;">
            <?php wp_nonce_field('agent2wp_use_existing_password'); ?>
            <label for="agent2wp-existing-password" style="display:block; margin-bottom:4px;">
                <strong><?php esc_html_e('Paste the password value', domain: 'agent2wp'); ?></strong>
            </label>
            <input
                type="text"
                id="agent2wp-existing-password"
                name="agent2wp_existing_password"
                placeholder="xxxx xxxx xxxx xxxx xxxx xxxx"
                style="width:340px; font-family:monospace;"
                class="regular-text"
                autocomplete="off"
            />
            <button type="submit" name="agent2wp_use_existing_password" class="button">
                <?php esc_html_e('Use this password', domain: 'agent2wp'); ?>
            </button>
            <?php if ($existing_error !== null): ?>
                <div class="notice notice-error inline" style="margin:8px 0 0;">
                    <p style="margin:0;"><?php echo esc_html($existing_error->get_error_message()); ?></p>
                </div>
            <?php endif; ?>
            <p class="description" style="margin-top:4px;">
                <?php esc_html_e(
                    'For reusing an application password you already saved (e.g. from a password manager). It is used only to fill the connection text and never stored on this site.',
                    domain: 'agent2wp',
                ); ?>
            </p>
        </form>
    </div>
    <?php
}

/**
 * Render the "Manage existing application passwords" collapsible section at the bottom of the page.
 *
 * Only meaningful when at least one Agent2Wp-tagged password exists. Hosts the list with revoke
 * buttons. Used both when AI Abilities are enabled (revoke + create lives elsewhere) and when
 * disabled (revoke only).
 */
function agent2wp_render_manage_passwords_section(string $context = 'enabled'): void
{
    $mcp_passwords = agent2wp_get_mcp_passwords();
    if ($mcp_passwords === []) {
        return;
    }

    $dt_format = agent2wp_get_datetime_format('Y-m-d H:i');
    $count = count($mcp_passwords);
    $open_by_default = $count <= 3;
    /* translators: %d: count of existing application passwords */
    $summary = sprintf(
        _n(
            single: 'Manage existing application password (%d)',
            plural: 'Manage existing application passwords (%d)',
            number: $count,
            domain: 'agent2wp',
        ),
        $count,
    );
    ?>
    <details class="agent2wp-manage-passwords"<?php echo $open_by_default ? ' open' : ''; ?>>
        <summary class="agent2wp-manage-passwords-summary">
            <?php echo esc_html($summary); ?>
        </summary>
        <div class="agent2wp-manage-passwords-body">
            <?php if ($context === 'disabled'): ?>
                <p class="description" style="margin:0 0 12px;">
                    <?php esc_html_e(
                        'AI Abilities are disabled. These credentials remain valid for WordPress authentication, but the Agent2Wp MCP endpoint will reject requests until AI Abilities are turned back on.',
                        domain: 'agent2wp',
                    ); ?>
                </p>
            <?php endif; ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', domain: 'agent2wp'); ?></th>
                        <th style="width:180px;"><?php esc_html_e('Created', domain: 'agent2wp'); ?></th>
                        <th style="width:180px;"><?php esc_html_e('Last Used', domain: 'agent2wp'); ?></th>
                        <th style="width:80px;"><?php esc_html_e('Actions', domain: 'agent2wp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mcp_passwords as $pw): ?>
                        <?php agent2wp_render_password_row($pw, $dt_format); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </details>
    <?php
}

/**
 * Build the paste-to-agent paragraph displayed in Option A of the Connect section.
 *
 * Returns a plain-text block the user can copy and paste into their AI client / agent.
 * The MCP server name uses the same placeholder as the JSON snippets so the live JS
 * preview can swap it in without re-rendering the page.
 */
function agent2wp_build_paste_to_agent_paragraph(
    string $rest_url,
    string $username,
    string $display_password,
    string $name_placeholder = '__AGENT2WP_MCP_NAME__',
    ?string $password_placeholder = null,
): string {
    $password_value = $password_placeholder ?? $display_password;
    $lines = [
        'I want to add this WordPress site as an MCP server to this AI client.',
        '',
        'Connection details:',
        '- Server URL: ' . $rest_url,
        '- Username: ' . $username,
        '- Application password: ' . $password_value,
        '- Server name to use in the config: ' . $name_placeholder,
        '- Transport: @automattic/mcp-wordpress-remote via npx',
        '',
        'Setup rules:',
        '- Pass credentials ONLY as env vars: WP_API_URL, WP_API_USERNAME, WP_API_PASSWORD. Do NOT use CLI flags like --url or --password (the package ignores them).',
        '- args array must be exactly ["-y", "@automattic/mcp-wordpress-remote@latest"].'
            . (
                agent2wp_likely_self_signed_https()
                    ? "\n"
                    . '- Also set NODE_TLS_REJECT_UNAUTHORIZED="0" in env (this site uses a local self-signed TLS certificate).'
                    : ''
            ),
        '',
        'Don\'t ask me to confirm choices already specified above. After writing the config, restart or reload the MCP session (most clients require it), then verify by listing the server\'s tools. If it fails, show me the stderr from the npx process before proposing changes.',
        '',
        'If you cannot modify the config of this AI client from here, tell me to expand "Need the JSON config for a specific client?" on the Agent2Wp Configuration page and copy the snippet manually.',
    ];

    return implode("\n", $lines);
}

/**
 * Build the npx server config array shared across multiple MCP clients.
 *
 * @param string $rest_url        MCP REST endpoint URL.
 * @param string $username        Current WordPress username.
 * @param string $display_password Plaintext password or placeholder.
 * @return array{command: string, args: list<string>, env: array<string, string>}
 */
function agent2wp_build_npx_server(string $rest_url, string $username, string $display_password): array
{
    $env = [
        'WP_API_URL' => $rest_url,
        'WP_API_USERNAME' => $username,
        'WP_API_PASSWORD' => $display_password,
    ];
    if (agent2wp_likely_self_signed_https()) {
        $env['NODE_TLS_REJECT_UNAUTHORIZED'] = '0';
    }
    return [
        'command' => 'npx',
        'args' => ['-y', '@automattic/mcp-wordpress-remote@latest'],
        'env' => $env,
    ];
}

/**
 * Build the MCPB bundle manifest (manifest.json contents) for this site.
 *
 * The bundle wraps the same npx proxy used by the JSON snippets, with the
 * connection credentials embedded directly so it installs without further
 * prompts. The plaintext application password is therefore written into the
 * file — callers must warn the user before offering the download.
 *
 * @param string $rest_url        MCP REST endpoint URL.
 * @param string $username        WordPress username.
 * @param string $display_password Plaintext application password.
 * @return array<string, mixed>
 */
function agent2wp_build_mcpb_manifest(
    string $rest_url,
    string $username,
    string $display_password,
    string $mcp_name,
): array {
    $env = [
        'WP_API_URL' => $rest_url,
        'WP_API_USERNAME' => $username,
        'WP_API_PASSWORD' => $display_password,
    ];
    if (agent2wp_likely_self_signed_https()) {
        $env['NODE_TLS_REJECT_UNAUTHORIZED'] = '0';
    }

    $site_name = trim(get_bloginfo('name'));
    $display_name = $site_name !== '' ? 'Agent2Wp — ' . $site_name : 'Agent2Wp';

    return [
        'manifest_version' => '0.3',
        'name' => $mcp_name,
        'display_name' => $display_name,
        'version' => AGENT2WP_VERSION,
        'description' => __(
            'Full WordPress control for your AI agent. Runs real PHP, queries the database, edits files — on your dev or staging site.',
            domain: 'agent2wp',
        ),
        'author' => ['name' => 'Agent2Wp'],
        'server' => [
            // entry_point is required by the MCPB schema even though the server
            // is launched via mcp_config (npx); the bundled stub is never run.
            'type' => 'node',
            'entry_point' => 'server/index.js',
            'mcp_config' => [
                'command' => 'npx',
                'args' => ['-y', '@automattic/mcp-wordpress-remote@latest'],
                'env' => $env,
            ],
        ],
    ];
}

/**
 * Stream a downloadable .mcpb bundle for Claude Desktop. Hooked on admin_post.
 *
 * The bundle embeds the plaintext application password, which WordPress only
 * exposes right after creation — so the password is posted back from the
 * connect page (where it was just shown) rather than read from storage.
 */
function agent2wp_handle_download_mcpb(): void
{
    if (!agent2wp_current_user_can_manage()) {
        wp_die(esc_html__('You are not allowed to download this bundle.', domain: 'agent2wp'));
    }

    check_admin_referer('agent2wp_download_mcpb');

    if (!class_exists('ZipArchive')) {
        wp_die(esc_html__(
            'Cannot build the bundle: the PHP zip extension is not available on this server. Use the JSON config instead.',
            domain: 'agent2wp',
        ));
    }

    $raw_password = $_POST['agent2wp_mcpb_password'] ?? '';
    $password = is_string($raw_password) ? (string) preg_replace('/\s+/', replacement: '', subject: $raw_password) : '';
    if ($password === '') {
        wp_die(esc_html__('Missing application password for the bundle.', domain: 'agent2wp'));
    }

    $username = wp_get_current_user()->user_login;
    $rest_url = rest_url('mcp/agent2wp');

    $raw_name = $_POST['agent2wp_mcpb_name'] ?? '';
    $mcp_name = is_string($raw_name)
        ? (string) preg_replace('/[^a-z0-9-]/', replacement: '', subject: strtolower($raw_name))
        : '';
    if ($mcp_name === '' || strlen($mcp_name) > 25) {
        $mcp_name = agent2wp_get_mcp_server_name_default();
    }

    $manifest = agent2wp_build_mcpb_manifest($rest_url, $username, $password, $mcp_name);
    $manifest_json = (string) wp_json_encode(
        $manifest,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    );

    $stub =
        "// Placeholder entry point. The actual MCP server is launched via mcp_config\n"
        . "// (npx @automattic/mcp-wordpress-remote), so this file is never executed.\n"
        . "// It exists only to satisfy the manifest's required entry_point field.\n";

    $tmp = wp_tempnam('agent2wp-mcpb');
    $zip = new ZipArchive();
    if ($tmp === '' || $zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        wp_die(esc_html__('Could not create the bundle archive.', domain: 'agent2wp'));
    }
    $zip->addFromString('manifest.json', $manifest_json);
    $zip->addFromString('server/index.js', $stub);
    $zip->close();

    $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    $filename = 'agent2wp-' . sanitize_file_name($host !== '' ? $host : 'site') . '.mcpb';

    nocache_headers();
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string) filesize($tmp));
    readfile($tmp);
    wp_delete_file($tmp);
    exit();
}

/** @param array<string, mixed> $npx_server */
function agent2wp_build_zed_json(string $mcp_name, array $npx_server, int $opts): string
{
    return (string) json_encode([
        'context_servers' => [
            $mcp_name => array_merge([
                'source' => 'custom',
                'enabled' => true,
            ], $npx_server),
        ],
    ], $opts);
}

function agent2wp_build_opencode_json(
    string $mcp_name,
    string $rest_url,
    string $username,
    string $display_password,
    int $opts,
): string {
    $environment = [
        'WP_API_URL' => $rest_url,
        'WP_API_USERNAME' => $username,
        'WP_API_PASSWORD' => $display_password,
    ];
    if (agent2wp_likely_self_signed_https()) {
        $environment['NODE_TLS_REJECT_UNAUTHORIZED'] = '0';
    }
    return (string) json_encode([
        'mcp' => [
            $mcp_name => [
                'type' => 'local',
                'command' => ['npx', '-y', '@automattic/mcp-wordpress-remote@latest'],
                'environment' => $environment,
            ],
        ],
    ], $opts);
}

function agent2wp_build_codex_toml(
    string $mcp_name,
    string $rest_url,
    string $username,
    string $display_password,
): string {
    $esc = static fn(string $v): string => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"';

    $lines = [
        '[mcp_servers.' . $mcp_name . ']',
        'command = "npx"',
        'args = ["-y", "@automattic/mcp-wordpress-remote@latest"]',
        '',
        '[mcp_servers.' . $mcp_name . '.env]',
        'WP_API_URL = ' . $esc($rest_url),
        'WP_API_USERNAME = ' . $esc($username),
        'WP_API_PASSWORD = ' . $esc($display_password),
    ];
    if (agent2wp_likely_self_signed_https()) {
        $lines[] = 'NODE_TLS_REJECT_UNAUTHORIZED = "0"';
    }
    return implode("\n", $lines);
}

function agent2wp_build_claude_code_cmd(
    string $mcp_name,
    string $rest_url,
    string $username,
    string $display_password,
): string {
    $sq = static fn(string $v): string => "'" . str_replace(search: "'", replace: "'\\''", subject: $v) . "'";

    $parts = [
        'claude mcp add ' . $sq($mcp_name),
        '--env WP_API_URL=' . $sq($rest_url),
        '--env WP_API_USERNAME=' . $sq($username),
        '--env WP_API_PASSWORD=' . $sq($display_password),
    ];
    if (agent2wp_likely_self_signed_https()) {
        $parts[] = '--env NODE_TLS_REJECT_UNAUTHORIZED=' . $sq('0');
    }
    $parts[] = '-- npx -y @automattic/mcp-wordpress-remote@latest';

    return implode(" \\\n  ", $parts);
}

/**
 * Build all per-client, per-transport config entries.
 *
 * @param string $rest_url        MCP REST endpoint URL.
 * @param string $username        Current WordPress username.
 * @param string $display_password Plaintext password or placeholder.
 * @param string $mcp_name        MCP server name used as the config key.
 * @return array<string, array{code: string, hint: string, paths: array<string, string>, isShell: bool}>
 */
function agent2wp_build_configs(string $rest_url, string $username, string $display_password, string $mcp_name): array
{
    $opts = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
    $npx_server = agent2wp_build_npx_server($rest_url, $username, $display_password);
    $mcp_servers_json = (string) json_encode(['mcpServers' => [$mcp_name => $npx_server]], $opts);
    $vscode_servers_json = (string) json_encode(['servers' => [$mcp_name => $npx_server]], $opts);

    /* translators: %s: config file name wrapped in <code> tags */
    $add_to = __('Add to %s.', domain: 'agent2wp');

    $special = [
        'claude-code' => [
            'code' => agent2wp_build_claude_code_cmd($mcp_name, $rest_url, $username, $display_password),
            'hint' => __('Run in your terminal.', domain: 'agent2wp'),
            'paths' => [],
            'isShell' => true,
        ],
        'codex' => [
            'code' => agent2wp_build_codex_toml($mcp_name, $rest_url, $username, $display_password),
            'hint' => sprintf($add_to, '<code>config.toml</code>'),
            'paths' => [
                'macOS / Linux' => '~/.codex/config.toml',
                'Windows' => '%USERPROFILE%\\.codex\\config.toml',
            ],
            'isShell' => false,
        ],
        'zed' => [
            'code' => agent2wp_build_zed_json($mcp_name, $npx_server, $opts),
            'hint' => sprintf($add_to, '<code>settings.json</code>'),
            'paths' => ['macOS / Linux' => '~/.config/zed/settings.json'],
            'isShell' => false,
        ],
        'opencode' => [
            'code' => agent2wp_build_opencode_json($mcp_name, $rest_url, $username, $display_password, $opts),
            'hint' => sprintf($add_to, '<code>opencode.json</code>'),
            'paths' => [
                __('Project', domain: 'agent2wp') => 'opencode.json',
                __('Global', domain: 'agent2wp') => '~/.config/opencode/opencode.json',
            ],
            'isShell' => false,
        ],
    ];

    return array_merge(agent2wp_build_standard_configs($mcp_servers_json, $vscode_servers_json), $special);
}

/**
 * Build per-client config entries that reuse the standard mcpServers/servers JSON payloads.
 *
 * @return array<string, array{code: string, hint: string, paths: array<string, string>, isShell: bool}>
 */
function agent2wp_build_standard_configs(string $mcp_servers_json, string $vscode_servers_json): array
{
    /* translators: %s: config file name wrapped in <code> tags */
    $add_to = __('Add to %s.', domain: 'agent2wp');

    return [
        'claude-desktop' => [
            'code' => $mcp_servers_json,
            'hint' => sprintf($add_to, '<code>claude_desktop_config.json</code>'),
            'paths' => [
                'macOS' => '~/Library/Application Support/Claude/claude_desktop_config.json',
                'Windows' => '%APPDATA%\\Claude\\claude_desktop_config.json',
            ],
            'isShell' => false,
        ],
        'cursor' => [
            'code' => $mcp_servers_json,
            'hint' => sprintf($add_to, '<code>mcp.json</code>'),
            'paths' => [
                __('Global', domain: 'agent2wp') => '~/.cursor/mcp.json',
                __('Project', domain: 'agent2wp') => '.cursor/mcp.json',
            ],
            'isShell' => false,
        ],
        'vscode' => [
            'code' => $vscode_servers_json,
            'hint' => sprintf($add_to, '<code>mcp.json</code>'),
            'paths' => [
                __('Workspace', domain: 'agent2wp') => '.vscode/mcp.json',
                __('User', domain: 'agent2wp') => __(
                    'Run: MCP: Open User Configuration (command palette)',
                    domain: 'agent2wp',
                ),
            ],
            'isShell' => false,
        ],
        'windsurf' => [
            'code' => $mcp_servers_json,
            'hint' => sprintf($add_to, '<code>mcp_config.json</code>'),
            'paths' => [
                'macOS / Linux' => '~/.codeium/windsurf/mcp_config.json',
                'Windows' => '%USERPROFILE%\\.codeium\\windsurf\\mcp_config.json',
            ],
            'isShell' => false,
        ],
        'cline' => [
            'code' => $mcp_servers_json,
            'hint' => sprintf($add_to, '<code>cline_mcp_settings.json</code>'),
            'paths' => [
                __('Via UI', domain: 'agent2wp') => __(
                    'Cline sidebar → MCP Servers → Configure MCP Servers',
                    domain: 'agent2wp',
                ),
            ],
            'isShell' => false,
        ],
        'roo-code' => [
            'code' => $mcp_servers_json,
            'hint' => sprintf($add_to, '<code>mcp.json</code>'),
            'paths' => [
                __('Project', domain: 'agent2wp') => '.roo/mcp.json',
                __('Via UI', domain: 'agent2wp') => __(
                    'Roo Code sidebar → MCP Servers → Configure MCP Servers',
                    domain: 'agent2wp',
                ),
            ],
            'isShell' => false,
        ],
        'kilo-code' => [
            'code' => $mcp_servers_json,
            'hint' => sprintf($add_to, '<code>mcp.json</code>'),
            'paths' => [
                __('Project', domain: 'agent2wp') => '.kilocode/mcp.json',
                __('Via UI', domain: 'agent2wp') => __(
                    'Kilo Code sidebar → MCP Servers → Configure MCP Servers',
                    domain: 'agent2wp',
                ),
            ],
            'isShell' => false,
        ],
        'github-copilot' => [
            'code' => $vscode_servers_json,
            'hint' => sprintf($add_to, '<code>mcp.json</code>'),
            'paths' => [
                __('Project', domain: 'agent2wp') => '.github/copilot/mcp.json',
            ],
            'isShell' => false,
        ],
        'amazon-q' => [
            'code' => $mcp_servers_json,
            'hint' => sprintf($add_to, '<code>mcp.json</code>'),
            'paths' => [
                __('Global', domain: 'agent2wp') => '~/.aws/amazonq/mcp.json',
                __('Project', domain: 'agent2wp') => '.amazonq/mcp.json',
            ],
            'isShell' => false,
        ],
        'gemini-cli' => [
            'code' => $mcp_servers_json,
            'hint' => sprintf($add_to, '<code>settings.json</code>'),
            'paths' => [
                __('Global', domain: 'agent2wp') => '~/.gemini/settings.json',
                __('Project', domain: 'agent2wp') => '.gemini/settings.json',
            ],
            'isShell' => false,
        ],
        'antigravity' => [
            'code' => $mcp_servers_json,
            'hint' => sprintf($add_to, '<code>mcp_config.json</code>'),
            'paths' => [
                'macOS / Linux' => '~/.gemini/antigravity/mcp_config.json',
                'Windows' => '%USERPROFILE%\\.gemini\\antigravity\\mcp_config.json',
            ],
            'isShell' => false,
        ],
    ];
}

/**
 * Informational notice above the connect prompt: pasting the prompt hands the
 * application password to the AI agent. Links to the manual configuration,
 * which reaches the same result without exposing the password to the AI.
 */
function agent2wp_render_prompt_password_notice(): void
{ ?>
    <div id="agent2wp-prompt-password-notice" class="notice notice-info inline" style="margin:0 0 12px;">
        <p style="margin:0;">
            <strong><?php esc_html_e(
                'This prompt shares your application password with your AI agent.',
                domain: 'agent2wp',
            ); ?></strong>
            <?php printf(
                /* translators: %s: link that opens the manual configuration section */
                esc_html__(
                    'Prefer to keep it private? Use the %s and paste the snippet into the config file yourself.',
                    domain: 'agent2wp',
                ),
                '<button type="button" class="button-link" onclick="agent2wpOpenManualConfig()">'
                . esc_html__('manual configuration', domain: 'agent2wp')
                . '</button>',
            ); ?>
        </p>
    </div>
    <?php }

/**
 * Render the "download .mcpb bundle" option (shown only for the Claude Desktop
 * tab via JS). Hidden when no real password is available, since the bundle must
 * embed the plaintext password. Warns the user that the file carries it.
 */
function agent2wp_render_mcpb_download(string $display_password, string $mcp_name): void
{
    // Without the zip extension the download handler can't build the bundle, so
    // omit the option entirely rather than send the user to an error page.
    if (!class_exists('ZipArchive')) {
        return;
    }
    $confirm_msg = wp_json_encode(__(
        'This bundle contains your password. The .mcpb file embeds your application password in plaintext so it installs without prompts. Anyone who gets the file can control this site — don\'t share it, and delete it after installing.',
        domain: 'agent2wp',
    ));
    $confirm_msg = $confirm_msg !== false ? $confirm_msg : '""';
    ?>
    <div id="agent2wp-mcpb-download" style="display:none; margin-top:20px; margin-bottom:4px;">
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
            <input type="hidden" name="action" value="agent2wp_download_mcpb">
            <?php wp_nonce_field('agent2wp_download_mcpb'); ?>
            <input type="hidden" name="agent2wp_mcpb_password" value="<?php echo esc_attr($display_password); ?>">
            <input type="hidden" name="agent2wp_mcpb_name" id="agent2wp-mcpb-name" value="<?php echo
                esc_attr($mcp_name)
            ; ?>">
            <button
                type="submit"
                class="button button-primary"
                style="display:inline-flex; flex-direction:column; align-items:flex-start; width:auto; padding:12px 24px; height:auto; gap:3px;"
                onclick="return confirm(<?php echo esc_attr($confirm_msg); ?>)"
            ><span style="font-size:15px; line-height:1.2;"><?php esc_html_e(
                'Download .mcpb bundle',
                domain: 'agent2wp',
            ); ?></span><span style="font-size:12px; font-weight:400; opacity:0.88; line-height:1.2;"><?php esc_html_e(
                'Open it with Claude Desktop to install in 1 click',
                domain: 'agent2wp',
            ); ?></span></button>
        </form>
        <p style="margin:8px 0 4px;">
            <button
                type="button"
                class="button-link"
                onclick="agent2wpShowPromptForDesktop(this)"
            ><?php esc_html_e('Use the prompt for Claude Desktop instead', domain: 'agent2wp'); ?></button>
        </p>
    </div>
    <?php
}

/** Render the JSON config block. */
function agent2wp_render_json_config_block(): void
{ ?>
    <div class="agent2wp-tab-content" style="border-radius:4px;">
        <div class="agent2wp-config-block">
            <pre id="agent2wp-config-code"></pre>
            <button type="button" class="button agent2wp-copy-btn" onclick="agent2wpCopyConfig(this)"><?php esc_html_e(
                'Copy',
                domain: 'agent2wp',
            ); ?></button>
        </div>
        <div id="agent2wp-config-footer" style="font-size:13px; color:#666; border-top: 1px solid #c3c4c7;">
            <div id="agent2wp-config-hint" style="padding: 10px 16px;"></div>
            <div id="agent2wp-config-paths" style="padding: 0 16px 10px;"></div>
        </div>
    </div>
    <?php }

/**
 * Render the tabbed MCP client config section.
 *
 * @param string $rest_url        MCP REST endpoint URL.
 * @param string $username        Current WordPress username.
 * @param string $display_password Plaintext password or placeholder.
 */
function agent2wp_render_config_section(string $rest_url, string $username, string $display_password): void
{
    $default_name = agent2wp_get_mcp_server_name_default();
    $name_placeholder = '__AGENT2WP_MCP_NAME__';
    $pw_slot = '__AGENT2WP_PW_SLOT__';
    $password_is_placeholder = hash_equals('YOUR-APP-PASSWORD', $display_password);
    $configs = agent2wp_build_configs($rest_url, $username, $display_password, $name_placeholder);
    $configs_json = (string) wp_json_encode($configs);

    $clients = [
        'claude-code' => 'Claude Code',
        'claude-desktop' => 'Claude Desktop',
        'codex' => 'Codex',
        'antigravity' => 'Antigravity',
        'cursor' => 'Cursor',
        'vscode' => 'VS Code',
        'github-copilot' => 'GitHub Copilot',
        'windsurf' => 'Windsurf',
        'cline' => 'Cline',
        'gemini-cli' => 'Gemini CLI',
        'roo-code' => 'Roo Code',
        'amazon-q' => 'Amazon Q',
        'zed' => 'Zed',
        'kilo-code' => 'Kilo Code',
        'opencode' => 'OpenCode',
    ];

    $copied_label = esc_js(__('Copied!', domain: 'agent2wp'));
    $paste_paragraph_initial = agent2wp_build_paste_to_agent_paragraph(
        $rest_url,
        $username,
        $display_password,
        $default_name,
    );
    $paste_paragraph_template = agent2wp_build_paste_to_agent_paragraph(
        $rest_url,
        $username,
        $display_password,
        $name_placeholder,
        $pw_slot,
    );
    ?>
    <h2 class="agent2wp-step-heading">
        <span class="agent2wp-step-badge">3</span>
        <?php esc_html_e('Connect Your AI Client', domain: 'agent2wp'); ?>
    </h2>

    <div class="agent2wp-client-tabs" style="gap:8px; margin-top:16px; margin-bottom:0;">
    <?php foreach ($clients as $key => $label): ?>
        <button
            type="button"
            class="agent2wp-client-tab agent2wp-top-client-tab"
            onclick="agent2wpSetClient('<?php echo esc_js($key); ?>', this)"
        ><?php echo esc_html($label); ?></button>
    <?php endforeach; ?>
    </div>

    <div id="agent2wp-connect-content" style="display:none; margin-top:16px;">

    <?php if (agent2wp_likely_self_signed_https()): ?>
        <div class="notice notice-warning inline" style="margin:0 0 12px;">
            <p style="margin:0;">
                <strong><?php esc_html_e('Local HTTPS detected.', domain: 'agent2wp'); ?></strong>
                <?php esc_html_e(
                    'Your site uses HTTPS with a certificate that is not publicly trusted (normal for local development). The snippets below include a small flag so your AI client can connect anyway.',
                    domain: 'agent2wp',
                ); ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (!$password_is_placeholder) {
        agent2wp_render_mcpb_download($display_password, $default_name);
    } ?>

    <?php agent2wp_render_prompt_password_notice(); ?>

    <div class="agent2wp-paste-block" id="agent2wp-paste-block" style="display:none;">
        <div class="agent2wp-paste-content" id="agent2wp-paste-content">
            <pre id="agent2wp-paste-text"><?php echo esc_html($paste_paragraph_initial); ?></pre>
        </div>
        <div class="agent2wp-paste-actions">
            <button
                type="button"
                class="button-link"
                id="agent2wp-paste-expand"
                onclick="agent2wpToggleExpandPaste(this)"
                aria-expanded="false"
                aria-controls="agent2wp-paste-content"
            ><?php esc_html_e('Show full text', domain: 'agent2wp'); ?></button>
            <button
                type="button"
                class="button button-primary"
                onclick="agent2wpCopyPaste(this)"
            ><?php esc_html_e('Copy prompt', domain: 'agent2wp'); ?></button>
            <p
                id="agent2wp-paste-copied-warning"
                style="display:none; margin:0; color:#d63638; font-size:13px; font-weight:600;"
            >
                <?php esc_html_e(
                    "Don't share with anyone: it contains an application password that grants access to this WordPress site.",
                    domain: 'agent2wp',
                ); ?>
            </p>
        </div>
    </div>

    <p style="margin:6px 0 4px;">
        <button
            type="button"
            class="button-link"
            id="agent2wp-server-name-toggle"
            aria-expanded="false"
            aria-controls="agent2wp-server-name-field"
            onclick="agent2wpToggleServerName(this)"
        ><?php esc_html_e('Change server name (optional)', domain: 'agent2wp'); ?></button>
    </p>
    <div id="agent2wp-server-name-field" hidden style="display:none; margin: 6px 0 14px;">
        <input
            type="text"
            id="agent2wp-mcp-name"
            value="<?php echo esc_attr($default_name); ?>"
            placeholder="<?php echo esc_attr($default_name); ?>"
            maxlength="25"
            style="width:220px;"
            oninput="agent2wpUpdateName(this.value)"
        >
        <p class="description" style="margin:6px 0 0;">
            <?php esc_html_e(
                'Editing here updates the connection text and JSON snippets below in real time. Each AI client config keeps its own name once saved on its side.',
                domain: 'agent2wp',
            ); ?>
        </p>
        <div id="agent2wp-name-warning" class="notice notice-warning inline" style="display:none; margin:8px 0 0;">
            <p style="margin:0;">
                <?php esc_html_e(
                    'Maximum 25 characters reached. Required for client compatibility.',
                    domain: 'agent2wp',
                ); ?>
            </p>
        </div>
        <div id="agent2wp-name-suggestion" class="notice notice-warning inline" style="display:none; margin:8px 0 0;">
            <p style="margin:0;">
                <?php esc_html_e(
                    'Tip: keep "agent2wp" in the name so you (and your AI agent) can tell this MCP server apart from others.',
                    domain: 'agent2wp',
                ); ?>
            </p>
        </div>
    </div>

    <div id="agent2wp-manual-btn-wrap" style="display:none;">
        <hr style="border:none; border-top:1px solid #dcdcde; margin:12px 0 8px;">
        <button
            type="button"
            class="button button-secondary"
            id="agent2wp-manual-toggle"
            aria-expanded="false"
            aria-controls="agent2wp-manual-config"
            onclick="agent2wpToggleManualConfig(this)"
        ><?php esc_html_e('Manual setup for your AI client', domain: 'agent2wp'); ?></button>
    </div>

    <div id="agent2wp-manual-config" hidden style="display:none; margin-top:14px;">
        <?php agent2wp_render_json_config_block(); ?>
        <p style="margin:10px 0 4px;">
            <button
                type="button"
                class="button-link"
                id="agent2wp-npxless-toggle"
                aria-expanded="false"
                aria-controls="agent2wp-npxless-config"
                onclick="agent2wpToggleNpxlessConfig(this)"
            ><?php esc_html_e(
                'Configs above not working? Try this npx-free alternative.',
                domain: 'agent2wp',
            ); ?></button>
        </p>
    </div>

    <div id="agent2wp-npxless-config" hidden style="display:none;">
        <p class="description" style="margin:0 0 12px;">
            <?php esc_html_e(
                'Copy this configuration snippet to connect using direct HTTP (no Node/npx required).',
                domain: 'agent2wp',
            ); ?>
        </p>

        <div class="agent2wp-client-tabs">
            <button
                type="button"
                class="agent2wp-client-tab agent2wp-npxless-client-tab active"
                onclick="agent2wpSetNpxlessClient('claude', this)"
            ><?php esc_html_e('Claude Code', domain: 'agent2wp'); ?></button>
            <button
                type="button"
                class="agent2wp-client-tab agent2wp-npxless-client-tab"
                onclick="agent2wpSetNpxlessClient('codex', this)"
            ><?php esc_html_e('Codex', domain: 'agent2wp'); ?></button>
        </div>

        <div class="agent2wp-tab-content" style="border-radius:4px;">
            <div class="agent2wp-config-block">
                <pre id="agent2wp-npxless-code"></pre>
                <button type="button" class="button agent2wp-copy-btn" onclick="agent2wpCopyNpxlessConfig(this)"><?php esc_html_e(
                    'Copy',
                    domain: 'agent2wp',
                ); ?></button>
            </div>
            <div id="agent2wp-npxless-footer" style="font-size:13px; color:#666; border-top: 1px solid #c3c4c7;">
                <div id="agent2wp-npxless-hint" style="padding: 10px 16px;">
                    <?php esc_html_e('Add to your project’s .mcp.json file.', domain: 'agent2wp'); ?>
                </div>
                <div id="agent2wp-npxless-paths" style="padding: 0 16px 10px;"></div>
            </div>
        </div>
    </div>

    </div><!-- #agent2wp-connect-content -->

    <script>
    (function () {
        var configs = <?php echo $configs_json; ?>;
        var clientLabels = <?php echo wp_json_encode($clients); ?>;
        var client = '';
        var defaultName = <?php echo wp_json_encode($default_name); ?>;
        var pasteTemplate = <?php echo wp_json_encode($paste_paragraph_template); ?>;
        var mcpName = <?php echo wp_json_encode($default_name); ?>;
        var npxlessClient = 'claude';
        var namePlaceholder = <?php echo wp_json_encode($name_placeholder); ?>;
        var passwordSentinel = <?php echo wp_json_encode($pw_slot); ?>;
        var passwordValue = <?php echo wp_json_encode($display_password); ?>;
        var passwordIsPlaceholder = <?php echo wp_json_encode($password_is_placeholder); ?>;
        var usernameValue = <?php echo wp_json_encode($username); ?>;

        function renderPaste() {
            var text = pasteTemplate.split(namePlaceholder).join(mcpName);
            var container = document.getElementById('agent2wp-paste-text');
            container.textContent = '';
            var idx = text.indexOf(passwordSentinel);
            if (idx === -1) {
                container.appendChild(document.createTextNode(text));
                return;
            }
            container.appendChild(document.createTextNode(text.substring(0, idx)));
            if (passwordIsPlaceholder) {
                var span = document.createElement('span');
                span.className = 'agent2wp-placeholder';
                span.textContent = 'YOUR-APP-PASSWORD';
                container.appendChild(span);
            } else {
                container.appendChild(document.createTextNode(passwordValue));
            }
            container.appendChild(document.createTextNode(text.substring(idx + passwordSentinel.length)));
        }

        function render() {
            renderConfig();
            renderPaste();
            renderNpxlessConfig();
        }

        function renderConfig() {
            if (!client) { return; }
            var cfg = configs[client];
            if (!cfg) { return; }

            var code = cfg.code.split(namePlaceholder).join(mcpName);
            var codeEl = document.getElementById('agent2wp-config-code');
            codeEl.textContent = code;
            if (code.indexOf('YOUR-APP-PASSWORD') !== -1) {
                codeEl.innerHTML = codeEl.innerHTML.replace(
                    /YOUR-APP-PASSWORD/g,
                    '<span class="agent2wp-placeholder">YOUR-APP-PASSWORD</span>'
                );
            }
            document.getElementById('agent2wp-config-hint').innerHTML = cfg.hint;

            var isDesktop = client === 'claude-desktop';
            var mcpbEl = document.getElementById('agent2wp-mcpb-download');
            if (mcpbEl) { mcpbEl.style.display = isDesktop ? '' : 'none'; }
            var pasteBlock = document.getElementById('agent2wp-paste-block');
            if (pasteBlock) { pasteBlock.style.display = isDesktop ? 'none' : ''; }
            var pwNotice = document.getElementById('agent2wp-prompt-password-notice');
            if (pwNotice) { pwNotice.style.display = isDesktop ? 'none' : ''; }
            var manualBtnWrap = document.getElementById('agent2wp-manual-btn-wrap');
            if (manualBtnWrap) { manualBtnWrap.style.display = ''; }
            var npxlessToggle = document.getElementById('agent2wp-npxless-toggle');
            if (npxlessToggle) {
                var showNpxless = client === 'claude-code' || client === 'codex';
                npxlessToggle.parentElement.style.display = showNpxless ? '' : 'none';
                if (!showNpxless) {
                    var npxlessConfig = document.getElementById('agent2wp-npxless-config');
                    if (npxlessConfig) { npxlessConfig.style.display = 'none'; npxlessConfig.hidden = true; }
                    npxlessToggle.setAttribute('aria-expanded', 'false');
                }
            }

            var pathsEl = document.getElementById('agent2wp-config-paths');
            var keys = Object.keys(cfg.paths);
            if (keys.length > 0) {
                var html = '<ul style="margin:4px 0 0; padding-left:20px;">';
                keys.forEach(function (label) {
                    html += '<li><strong>' + label + '</strong>: <code>' + cfg.paths[label] + '</code></li>';
                });
                html += '</ul>';
                pathsEl.innerHTML = html;
                pathsEl.style.display = '';
            } else {
                pathsEl.innerHTML = '';
                pathsEl.style.display = 'none';
            }
        }

        window.agent2wpSetClient = function (key, btn) {
            client = key;
            document.querySelectorAll('.agent2wp-top-client-tab').forEach(function (t) { t.classList.remove('active'); });
            btn.classList.add('active');
            var content = document.getElementById('agent2wp-connect-content');
            if (content) { content.style.display = ''; }
            var manualToggle = document.getElementById('agent2wp-manual-toggle');
            if (manualToggle && clientLabels[key]) {
                manualToggle.textContent = <?php echo
                    wp_json_encode(__('Manual setup for', domain: 'agent2wp'))
                ; ?> + ' ' + clientLabels[key];
            }
            renderConfig();
        };

        window.agent2wpShowPromptForDesktop = function (btn) {
            var mcpbEl = document.getElementById('agent2wp-mcpb-download');
            if (mcpbEl) { mcpbEl.style.display = 'none'; }
            var pasteBlock = document.getElementById('agent2wp-paste-block');
            if (pasteBlock) { pasteBlock.style.display = ''; }
            var pwNotice = document.getElementById('agent2wp-prompt-password-notice');
            if (pwNotice) { pwNotice.style.display = ''; }
        };

        window.agent2wpSetNpxlessClient = function (key, btn) {
            npxlessClient = key;
            document.querySelectorAll('.agent2wp-npxless-client-tab').forEach(function (t) { t.classList.remove('active'); });
            btn.classList.add('active');
            renderNpxlessConfig();
        };

        function updateNameWarning(value) {
            var warning = document.getElementById('agent2wp-name-warning');
            warning.style.display = value.length >= 25 ? 'block' : 'none';

            var suggestion = document.getElementById('agent2wp-name-suggestion');
            var trimmed = value.trim();
            var missingAgent2Wp = trimmed.length > 0 && trimmed.toLowerCase().indexOf('agent2wp') === -1;
            suggestion.style.display = missingAgent2Wp ? 'block' : 'none';
        }

        window.agent2wpUpdateName = function (value) {
            mcpName = value.trim() || defaultName;
            var nameField = document.getElementById('agent2wp-mcpb-name');
            if (nameField) { nameField.value = mcpName; }
            updateNameWarning(value);
            render();
        };

        window.agent2wpToggleServerName = function (btn) {
            var field = document.getElementById('agent2wp-server-name-field');
            var expanded = btn.getAttribute('aria-expanded') === 'true';
            if (expanded) {
                field.style.display = 'none';
                field.hidden = true;
                btn.setAttribute('aria-expanded', 'false');
            } else {
                field.style.display = 'block';
                field.hidden = false;
                btn.setAttribute('aria-expanded', 'true');
                var input = document.getElementById('agent2wp-mcp-name');
                if (input) { input.focus(); }
            }
        };

        window.agent2wpToggleManualConfig = function (btn) {
            var panel = document.getElementById('agent2wp-manual-config');
            var expanded = btn.getAttribute('aria-expanded') === 'true';
            if (expanded) {
                panel.style.display = 'none';
                panel.hidden = true;
                btn.setAttribute('aria-expanded', 'false');
            } else {
                panel.style.display = '';
                panel.hidden = false;
                btn.setAttribute('aria-expanded', 'true');
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        };

        // Open the manual-config section (never closes it) and scroll to it.
        // Used by the "manual configuration" link in the password notice.
        window.agent2wpOpenManualConfig = function () {
            var panel = document.getElementById('agent2wp-manual-config');
            if (panel === null) {
                return;
            }
            panel.style.display = '';
            panel.hidden = false;
            var toggle = document.getElementById('agent2wp-manual-toggle');
            if (toggle !== null) {
                toggle.setAttribute('aria-expanded', 'true');
            }
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        };

        window.agent2wpToggleExpandPaste = function (btn) {
            var content = document.getElementById('agent2wp-paste-content');
            var expanded = btn.getAttribute('aria-expanded') === 'true';
            if (expanded) {
                content.classList.remove('is-expanded');
                btn.setAttribute('aria-expanded', 'false');
                btn.textContent = <?php echo wp_json_encode(__('Show full text', domain: 'agent2wp')); ?>;
            } else {
                content.classList.add('is-expanded');
                btn.setAttribute('aria-expanded', 'true');
                btn.textContent = <?php echo wp_json_encode(__('Show less', domain: 'agent2wp')); ?>;
            }
        };

        window.agent2wpCopyPaste = function (btn) {
            navigator.clipboard.writeText(document.getElementById('agent2wp-paste-text').textContent).then(function () {
                var orig = btn.textContent;
                btn.textContent = '<?php echo $copied_label; ?>';
                var warning = document.getElementById('agent2wp-paste-copied-warning');
                if (warning) { warning.style.display = 'block'; }
                setTimeout(function () {
                    btn.textContent = orig;
                    if (warning) { warning.style.display = 'none'; }
                }, 4000);
            });
        };

        window.agent2wpCopyConfig = function (btn) {
            navigator.clipboard.writeText(document.getElementById('agent2wp-config-code').textContent).then(function () {
                var orig = btn.textContent;
                btn.textContent = '<?php echo $copied_label; ?>';
                setTimeout(function () { btn.textContent = orig; }, 1500);
            });
        };

        window.agent2wpToggleNpxlessConfig = function (btn) {
            var panel = document.getElementById('agent2wp-npxless-config');
            var expanded = btn.getAttribute('aria-expanded') === 'true';
            if (expanded) {
                panel.style.display = 'none';
                panel.hidden = true;
                btn.setAttribute('aria-expanded', 'false');
            } else {
                panel.style.display = '';
                panel.hidden = false;
                btn.setAttribute('aria-expanded', 'true');
            }
        };

        window.agent2wpCopyNpxlessConfig = function (btn) {
            navigator.clipboard.writeText(document.getElementById('agent2wp-npxless-code').textContent).then(function () {
                var orig = btn.textContent;
                btn.textContent = '<?php echo $copied_label; ?>';
                setTimeout(function () { btn.textContent = orig; }, 1500);
            });
        };

        function renderNpxlessConfig() {
            var npxlessCodeEl = document.getElementById('agent2wp-npxless-code');
            if (!npxlessCodeEl) { return; }

            var serverName = mcpName;
            var url = <?php echo wp_json_encode($rest_url); ?>;
            var username = usernameValue;

            var authHeaderValue;
            if (passwordIsPlaceholder) {
                authHeaderValue = 'Basic <span class="agent2wp-placeholder">BASE64_ENCODED_CREDENTIALS</span>';
            } else {
                var pwClean = passwordValue.replace(/\s+/g, '');
                var encoded = window.btoa(username + ':' + pwClean);
                authHeaderValue = 'Basic ' + encoded;
            }

            var indent = '  ';
            var hintEl = document.getElementById('agent2wp-npxless-hint');
            var pathsEl = document.getElementById('agent2wp-npxless-paths');
            var placeholder = 'BASE64_ENCODED_CREDENTIALS';
            var jsonQuote = function (value) {
                return JSON.stringify(value);
            };
            var tomlQuote = function (value) {
                return '"' + value.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"';
            };
            var code;

            if (npxlessClient === 'codex') {
                code = '[mcp_servers.' + serverName + ']\n' +
                    'url = ' + tomlQuote(url) + '\n' +
                    'http_headers = { Authorization = ' + tomlQuote(authHeaderValue.replace(/<[^>]+>/g, '')) + ' }';
                hintEl.textContent = <?php echo
                    wp_json_encode(__('Add to your project’s .codex/config.toml file.', domain: 'agent2wp'))
                ; ?>;
                pathsEl.innerHTML = '<ul style="margin:4px 0 0; padding-left:20px;">' +
                    '<li><strong><?php echo
                        esc_js(__('Project', domain: 'agent2wp'))
                    ; ?></strong>: <code>.codex/config.toml</code></li>' +
                    '<li><strong><?php echo
                        esc_js(__('Global', domain: 'agent2wp'))
                    ; ?></strong>: <code>~/.codex/config.toml</code></li>' +
                    '</ul>';
            } else {
                code = '{\n' +
                    indent + '"mcpServers": {\n' +
                    indent + indent + jsonQuote(serverName) + ': {\n' +
                    indent + indent + indent + '"type": "http",\n' +
                    indent + indent + indent + '"url": ' + jsonQuote(url) + ',\n' +
                    indent + indent + indent + '"headers": {\n' +
                    indent + indent + indent + indent + '"Authorization": ' + jsonQuote(authHeaderValue.replace(/<[^>]+>/g, '')) + '\n' +
                    indent + indent + indent + '}\n' +
                    indent + indent + '}\n' +
                    indent + '}\n' +
                    '}';
                hintEl.textContent = <?php echo
                    wp_json_encode(__('Add to your project’s .mcp.json file.', domain: 'agent2wp'))
                ; ?>;
                pathsEl.innerHTML = '<ul style="margin:4px 0 0; padding-left:20px;">' +
                    '<li><strong><?php echo
                        esc_js(__('Project', domain: 'agent2wp'))
                    ; ?></strong>: <code>.mcp.json</code></li>' +
                    '</ul>';
            }

            npxlessCodeEl.textContent = code;
            if (passwordIsPlaceholder) {
                npxlessCodeEl.innerHTML = npxlessCodeEl.innerHTML.replace(
                    placeholder,
                    '<span class="agent2wp-placeholder">' + placeholder + '</span>'
                );
            }
        }

        render();
    }());
    </script>
    <?php
}

function agent2wp_render_mcp_dependency_inline_notice(?WP_Error $dependency_error): void
{
    if ($dependency_error === null) {
        return;
    }

    ?>
    <div class="agent2wp-mcp-error-panel" role="alert">
        <h2><?php esc_html_e('Agent2Wp cannot expose MCP', domain: 'agent2wp'); ?></h2>
        <p><?php echo esc_html($dependency_error->get_error_message()); ?></p>
    </div>
    <?php
}

function agent2wp_render_enable_prompt(?WP_Error $dependency_error): void
{
    if (agent2wp_is_enabled() || $dependency_error !== null) {
        return;
    }

    ?>
    <p style="color:#666; font-size:14px;">
        <?php esc_html_e(
            'Enable AI Abilities above to create application passwords and connect an MCP client.',
            domain: 'agent2wp',
        ); ?>
    </p>
    <?php
}

/**
 * Render the connect / setup dashboard page.
 */
// Complexity is inherent: this is the top-level admin page template that orchestrates request
// handling (toggle/create/use-existing) and then conditionally emits each section (dependency
// notice, save notice, password step, config block, disabled-state manage list) inline. The
// branches map one-to-one onto template regions, so extracting them would not reduce real
// complexity, only scatter the page layout across helpers.
// @mago-expect lint:cyclomatic-complexity
function agent2wp_render_connect_page(): void
{
    if (!agent2wp_current_user_can_manage()) {
        return;
    }

    if (agent2wp_easy_mode_enabled() && !agent2wp_admin_request_flag('agent2wp_advanced')) {
        agent2wp_render_easy_connect_page();
        return;
    }

    $mcp_dependency_error = agent2wp_get_mcp_dependency_error();
    $toggle_saved = agent2wp_handle_toggle_enabled();
    $enabled = agent2wp_is_enabled();
    $mcp_ready = $enabled && $mcp_dependency_error === null;

    $password_result = $mcp_ready ? agent2wp_handle_create_password() : null;
    $create_error = is_wp_error($password_result) ? $password_result : null;
    $new_password = is_string($password_result) ? $password_result : null;

    $existing_result = $mcp_ready ? agent2wp_handle_use_existing_password() : null;
    $existing_error = is_wp_error($existing_result) ? $existing_result : null;
    $existing_password = is_string($existing_result) ? $existing_result : null;

    $result_message = match (agent2wp_admin_request_get_string('agent2wp_result')) {
        'revoked' => __('Application password revoked.', domain: 'agent2wp'),
        default => null,
    };

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $rest_url = rest_url('mcp/agent2wp');
    $display_password = $new_password ?? $existing_password ?? 'YOUR-APP-PASSWORD';

    $copied_label = esc_js(__('Copied!', domain: 'agent2wp'));

    ?>
    <style>
        .agent2wp-connect-section {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 6px;
            padding: 20px 24px;
            margin: 0 0 20px;
            box-shadow: 0 1px 1px rgba(0, 0, 0, 0.03);
        }
        .agent2wp-step-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0 0 12px;
            font-size: 18px;
            font-weight: 600;
            color: #1d2327;
        }
        .agent2wp-step-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #1d2327;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            flex: 0 0 auto;
        }
        .agent2wp-config-block { position: relative; }
        .agent2wp-config-block pre {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 16px;
            border-radius: 0 4px 0 0;
            overflow-x: auto;
            font-size: 13px;
            line-height: 1.5;
            margin: 0;
        }
        .agent2wp-copy-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            padding: 4px 10px;
            font-size: 12px;
            cursor: pointer;
            background: #f6f7f7 !important;
            border-color: #8c8f94 !important;
            color: #1d2327 !important;
        }
        .agent2wp-password-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fff8e1;
            border: 1px solid #f0c040;
            border-radius: 4px;
            padding: 12px 16px;
            margin: 12px 0;
        }
        .agent2wp-password-value {
            font-family: monospace;
            font-size: 18px;
            letter-spacing: 1px;
            font-weight: bold;
        }
        .agent2wp-client-tabs { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
        .agent2wp-client-tab {
            padding: 5px 14px;
            border: 1px solid #c3c4c7;
            background: #f6f7f7;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            color: #1d2327;
        }
        .agent2wp-client-tab.active {
            background: var(--wp-admin-theme-color, #2271b1);
            color: #fff;
            border-color: var(--wp-admin-theme-color, #2271b1);
            font-weight: 600;
        }
        .agent2wp-top-client-tab {
            padding: 9px 20px;
            font-size: 14px;
        }
        .agent2wp-tab-content { border: 1px solid #c3c4c7; border-radius: 4px; }
        .agent2wp-revoke-btn { color: #d63638 !important; border-color: #d63638 !important; }
        .agent2wp-placeholder { background: #d63638; color: #fff; padding: 1px 4px; border-radius: 3px; }
        .agent2wp-mcp-error-panel {
            background: #fff;
            border-left: 4px solid #d63638;
            box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
            margin: 16px 0 24px;
            padding: 12px 16px;
        }
        .agent2wp-mcp-error-panel h2 {
            color: #1d2327;
            font-size: 16px;
            line-height: 1.4;
            margin: 0 0 8px;
        }
        .agent2wp-mcp-error-panel p {
            font-size: 14px;
            margin: 0;
        }
        .agent2wp-production-warning {
            background: #fff8e1;
            border-left: 4px solid #f0c040;
            padding: 12px 16px;
            margin: 12px 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .agent2wp-production-warning p {
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
            flex: 1 1 auto;
        }
        .agent2wp-paste-block {
            margin: 12px 0;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            overflow: hidden;
        }
        .agent2wp-paste-header {
            background: #1d2327;
            color: #fff;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }
        .agent2wp-paste-content {
            position: relative;
            background: #f6f7f7;
        }
        .agent2wp-paste-content pre {
            background: transparent;
            color: #1d2327;
            padding: 16px;
            border: none;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 13px;
            line-height: 1.6;
            margin: 0;
            max-height: 6.5em;
            overflow: hidden;
        }
        .agent2wp-paste-content.is-expanded pre {
            max-height: none;
            overflow: visible;
        }
        .agent2wp-paste-content:not(.is-expanded)::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 32px;
            background: linear-gradient(to bottom, rgba(246, 247, 247, 0), rgba(246, 247, 247, 1));
            pointer-events: none;
        }
        .agent2wp-paste-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
            padding: 10px 14px 14px;
            background: #fff;
            border-top: 1px solid #c3c4c7;
        }
        .agent2wp-manage-passwords {
            margin: 20px 0 0;
            border-top: 1px solid #e0e0e0;
            padding-top: 16px;
        }
        .agent2wp-manage-passwords-summary {
            font-weight: 600;
            cursor: pointer;
            list-style: none;
            color: #1d2327;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 0;
        }
        .agent2wp-manage-passwords-summary::-webkit-details-marker { display: none; }
        .agent2wp-manage-passwords-summary::before {
            content: '▸';
            color: #646970;
            transition: transform 0.15s;
        }
        .agent2wp-manage-passwords[open] .agent2wp-manage-passwords-summary::before {
            transform: rotate(90deg);
        }
        .agent2wp-manage-passwords-body {
            padding-top: 12px;
        }
    </style>

    <?php agent2wp_render_admin_header(); ?>
    <div class="wrap">
        <p style="margin:12px 0;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=agent2wp-connect')); ?>" class="button">
                <?php esc_html_e('← Back to simple mode', domain: 'agent2wp'); ?>
            </a>
        </p>
        <h1><?php esc_html_e('Advanced configuration', domain: 'agent2wp'); ?></h1>

        <?php agent2wp_render_mcp_dependency_inline_notice($mcp_dependency_error); ?>

        <?php if ($toggle_saved === true): ?>
            <div class="notice notice-success is-dismissible"><p><?php

            esc_html_e('Settings saved.', domain: 'agent2wp');
            ?></p></div>
        <?php endif; ?>

        <?php agent2wp_render_production_warning(); ?>

        <div class="agent2wp-connect-section">
            <?php agent2wp_render_enable_toggle(); ?>
        </div>

        <?php agent2wp_render_enable_prompt($mcp_dependency_error); ?>
        <?php if ($mcp_ready): ?>
            <?php if ($create_error !== null): ?>
                <div class="notice notice-error"><p><?php

                echo esc_html($create_error->get_error_message());
                ?></p></div>
            <?php endif; ?>

            <?php if ($result_message !== null): ?>
                <div class="notice notice-success is-dismissible"><p><?php

                echo esc_html($result_message);
                ?></p></div>
            <?php endif; ?>

            <div class="agent2wp-connect-section">
                <?php agent2wp_render_password_step($new_password, $existing_password, $existing_error); ?>
                <?php agent2wp_render_manage_passwords_section(); ?>
            </div>

            <?php if ($new_password !== null || $existing_password !== null): ?>
                <div class="agent2wp-connect-section">
                    <?php agent2wp_render_config_section($rest_url, $username, $display_password); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (!$mcp_ready && agent2wp_get_mcp_passwords() !== []): ?>
            <?php agent2wp_render_manage_passwords_section(context: 'disabled'); ?>
        <?php endif; ?>

    </div>

    <script>
    function agent2wpCopy(id, btn) {
        var text = document.getElementById(id).textContent;
        navigator.clipboard.writeText(text).then(function() {
            var orig = btn.textContent;
            btn.textContent = '<?php echo $copied_label; ?>';
            setTimeout(function() { btn.textContent = orig; }, 1500);
        });
    }
    function agent2wpTogglePasswordName(btn) {
        var field = document.getElementById('agent2wp-password-name-field');
        var expanded = btn.getAttribute('aria-expanded') === 'true';
        if (expanded) {
            field.style.display = 'none';
            field.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
        } else {
            field.style.display = 'block';
            field.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
            var input = document.getElementById('agent2wp-password-name');
            if (input) { input.focus(); }
        }
    }
    function agent2wpToggleUseExisting(btn) {
        var field = document.getElementById('agent2wp-use-existing-field');
        var expanded = btn.getAttribute('aria-expanded') === 'true';
        if (expanded) {
            field.style.display = 'none';
            field.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
        } else {
            field.style.display = 'block';
            field.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
            var input = document.getElementById('agent2wp-existing-password');
            if (input) { input.focus(); }
        }
    }
    </script>
    <?php
}
