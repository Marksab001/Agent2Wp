<?php

// SPDX-FileCopyrightText: 2026 Taibur Rahaman <https://github.com/Taibur-Rahaman>
// SPDX-License-Identifier: LicenseRef-Agent2Wp-RSL-1.0 AND AGPL-3.0-or-later

declare(strict_types=1);

/**
 * Plugin Name: Agent2Wp
 * Plugin URI: https://github.com/Taibur-Rahaman/Agent2Wp
 * Description: WordPress MCP plugin — connect Claude, Cursor & AI agents. PHP, WP-CLI, files, posts, Elementor skills. Expert Suite included. Staging/dev only.
 * Version: 2.0.0
 * Requires at least: 6.9
 * Requires PHP: 8.0
 * Author: Taibur Rahaman
 * Author URI: https://github.com/Taibur-Rahaman
 * License: Agent2Wp-RSL-1.0 (see COPYRIGHT.md — do not copy without permission)
 * License URI: https://github.com/Taibur-Rahaman/Agent2Wp/blob/main/LICENSE
 * Text Domain: agent2wp
 *
 * Copyright (c) 2026 Taibur Rahaman. Unauthorized copying is prohibited.
 * See COPYRIGHT.md and LICENSE. Novamira-derived portions: AGPL-3.0-or-later
 * (LICENSE-AGPL-3.0.txt). This program is provided WITHOUT WARRANTY.
 */

if (!defined('ABSPATH')) {
    exit();
}

define(constant_name: 'AGENT2WP_VERSION', value: '2.0.0');
define(constant_name: 'AGENT2WP_MAX_EXECUTION_TIME', value: 30);
define('AGENT2WP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AGENT2WP_SANDBOX_DIR', WP_CONTENT_DIR . '/agent2wp-sandbox/');
define(constant_name: 'AGENT2WP_VENDOR_AUTOLOAD', value: __DIR__ . '/vendor/autoload_packages.php');
define(constant_name: 'AGENT2WP_MCP_ADAPTER_CLASS', value: 'WP\\MCP\\Core\\McpAdapter');

/**
 * Load bundled Composer dependencies and report the common source-ZIP install mistake clearly.
 *
 * @return WP_Error|null
 */
function agent2wp_load_bundled_dependencies()
{
    if (!file_exists(AGENT2WP_VENDOR_AUTOLOAD)) {
        return new WP_Error('agent2wp_missing_vendor', __(
            'Agent2Wp is installed without its bundled vendor directory. This usually means the GitHub/source ZIP was installed instead of the Agent2Wp release build ZIP. The MCP Adapter cannot load, so Agent2Wp will not register an MCP endpoint. Install the Agent2Wp release build ZIP before using Agent2Wp.',
            domain: 'agent2wp',
        ));
    }

    try {
        require_once AGENT2WP_VENDOR_AUTOLOAD;
    } catch (\Throwable $e) {
        return new WP_Error('agent2wp_autoload_failed', sprintf(
            __(
                'Agent2Wp could not load its bundled Composer dependencies. The MCP Adapter cannot load, so Agent2Wp will not register an MCP endpoint. Reinstall the Agent2Wp release build ZIP. Error: %s',
                domain: 'agent2wp',
            ),
            $e->getMessage(),
        ));
    }

    if (!class_exists(AGENT2WP_MCP_ADAPTER_CLASS)) {
        return new WP_Error('agent2wp_mcp_adapter_missing', sprintf(
            __(
                'Agent2Wp loaded its Composer autoloader, but the MCP Adapter class (%s) is not available. Agent2Wp will not register an MCP endpoint. Reinstall the Agent2Wp release build ZIP.',
                domain: 'agent2wp',
            ),
            AGENT2WP_MCP_ADAPTER_CLASS,
        ));
    }

    return null;
}

/**
 * Store a runtime MCP dependency error.
 */
function agent2wp_set_mcp_dependency_error(WP_Error $error): void
{
    agent2wp_mcp_dependency_error($error);
}

/**
 * Return the current MCP dependency error, if any.
 *
 * @return WP_Error|null
 */
function agent2wp_get_mcp_dependency_error()
{
    return agent2wp_mcp_dependency_error();
}

/**
 * Shared storage for the current MCP dependency error.
 *
 * @return WP_Error|null
 */
function agent2wp_mcp_dependency_error(?WP_Error $error = null)
{
    static $current = null;

    if ($error !== null) {
        $current = $error;
    }

    return $current;
}

/**
 * Whether the bundled MCP Adapter is available for Agent2Wp to initialize.
 */
function agent2wp_is_mcp_adapter_available(): bool
{
    return agent2wp_get_mcp_dependency_error() === null && class_exists(AGENT2WP_MCP_ADAPTER_CLASS);
}

/**
 * Block activation when the distributable dependencies are missing.
 */
function agent2wp_activation_check(): void
{
    $error = agent2wp_get_mcp_dependency_error();
    if ($error === null) {
        agent2wp_mark_pending_auto_setup();
        return;
    }

    if (function_exists('deactivate_plugins')) {
        deactivate_plugins(plugin_basename(__FILE__));
    }

    wp_die(
        '<p>' . esc_html($error->get_error_message()) . '</p>',
        esc_html__('Agent2Wp installation is incomplete', domain: 'agent2wp'),
        ['back_link' => true],
    );
}

/**
 * Show a persistent admin error when Agent2Wp cannot expose MCP.
 */
function agent2wp_render_mcp_dependency_notice(): void
{
    if (!agent2wp_current_user_can_manage()) {
        return;
    }

    $page = agent2wp_admin_request_page();
    if (in_array($page, ['agent2wp-connect', 'agent2wp-abilities', 'agent2wp-sandbox'], strict: true)) {
        return;
    }

    $error = agent2wp_get_mcp_dependency_error();
    if ($error === null) {
        return;
    }

    wp_admin_notice(esc_html($error->get_error_message()), [
        'type' => 'error',
        'dismissible' => false,
    ]);
}

/**
 * Return a clear REST error at the MCP endpoint when the adapter cannot register its own route.
 */
function agent2wp_register_missing_mcp_endpoint(): void
{
    $error = agent2wp_get_mcp_dependency_error();
    if ($error === null) {
        return;
    }

    $routes = rest_get_server()->get_routes();
    $callback = static fn() => new WP_Error('agent2wp_mcp_adapter_unavailable', $error->get_error_message(), [
        'status' => 500,
    ]);

    foreach (['agent2wp', 'mcp-adapter-default-server'] as $route_slug) {
        if (array_key_exists('/mcp/' . $route_slug, $routes)) {
            continue;
        }
        register_rest_route('mcp', '/' . $route_slug, [
            'methods' => WP_REST_Server::ALLMETHODS,
            'callback' => $callback,
            'permission_callback' => '__return_true',
        ]);
    }
}

/**
 * Initialize the MCP Adapter and convert runtime failures into visible admin notices.
 */
function agent2wp_initialize_mcp_adapter(): bool
{
    if (!agent2wp_is_mcp_adapter_available()) {
        return false;
    }

    try {
        \WP\MCP\Core\McpAdapter::instance();
        return true;
    } catch (\Throwable $e) {
        agent2wp_set_mcp_dependency_error(
            new WP_Error('agent2wp_mcp_adapter_init_failed', sprintf(
                __(
                    'Agent2Wp found the MCP Adapter, but it failed during initialization. Agent2Wp will not register an MCP endpoint. Error: %s',
                    domain: 'agent2wp',
                ),
                $e->getMessage(),
            )),
        );
        return false;
    }
}

$agent2wp_dependency_error = agent2wp_load_bundled_dependencies();
if ($agent2wp_dependency_error !== null) {
    agent2wp_set_mcp_dependency_error($agent2wp_dependency_error);
}

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/licensing.php';
require_once __DIR__ . '/includes/easy-mode.php';
require_once __DIR__ . '/includes/connect-page.php';
require_once __DIR__ . '/includes/admin-page.php';
require_once __DIR__ . '/includes/expert-suite/bootstrap.php';
require_once __DIR__ . '/includes/upload-link.php';
require_once __DIR__ . '/includes/admin-access-link.php';
require_once __DIR__ . '/includes/skills/bootstrap.php';
require_once __DIR__ . '/includes/instructions-admin.php';

\Agent2Wp\Context\boot_context_admin();

register_activation_hook(__FILE__, callback: 'agent2wp_activation_check');
register_deactivation_hook(__FILE__, callback: 'agent2wp_unschedule_gutenberg_cron');
add_action('admin_notices', callback: 'agent2wp_render_mcp_dependency_notice');
add_action('network_admin_notices', callback: 'agent2wp_render_mcp_dependency_notice');
add_action('rest_api_init', callback: 'agent2wp_register_missing_mcp_endpoint', priority: 999);

add_action('admin_post_agent2wp_toggle_ai_abilities', callback: 'agent2wp_handle_admin_bar_toggle');
add_action('admin_post_agent2wp_download_mcpb', callback: 'agent2wp_handle_download_mcpb');

function agent2wp_unschedule_gutenberg_cron(): void
{
    require_once __DIR__ . '/includes/abilities/gutenberg/bootstrap.php';
    \Agent2Wp\Abilities\Gutenberg\unschedule_cleanup();
}

function agent2wp_load_gutenberg_runtime(): void
{
    require_once __DIR__ . '/includes/abilities/gutenberg/bootstrap.php';
    require_once __DIR__ . '/includes/abilities/gutenberg/runtime.php';
    require_once __DIR__ . '/includes/abilities/gutenberg/rest.php';
    require_once __DIR__ . '/includes/gutenberg-finalizer-admin.php';
    \Agent2Wp\GutenbergFinalizer\boot_gutenberg_finalizer_admin();
}

function agent2wp_load_gutenberg_abilities(): void
{
    $gutenberg_dir = __DIR__ . '/includes/abilities/gutenberg/';
    require_once $gutenberg_dir . 'bootstrap.php';
    require_once $gutenberg_dir . 'runtime.php';
    require_once $gutenberg_dir . 'get-finalizer-runtime.php';
    require_once $gutenberg_dir . 'get-content.php';
    require_once $gutenberg_dir . 'write-content.php';
    require_once $gutenberg_dir . 'create-pending-batch.php';
    require_once $gutenberg_dir . 'add-pending-change.php';
    require_once $gutenberg_dir . 'enable-batch-finalization.php';
    require_once $gutenberg_dir . 'get-pending-batch.php';
    require_once $gutenberg_dir . 'list-pending-batches.php';
    require_once $gutenberg_dir . 'delete-pending-batch.php';
    require_once $gutenberg_dir . 'delete-pending-change.php';
    require_once $gutenberg_dir . 'get-finalization-url.php';
}

function agent2wp_inject_custom_instructions(mixed $instructions): mixed
{
    if (!is_string($instructions)) {
        return $instructions;
    }

    if (\Agent2Wp\Context\instructions_custom_injection_suppressed()) {
        return $instructions;
    }

    if (!\Agent2Wp\Context\instructions_is_enabled()) {
        return $instructions;
    }

    $custom = \Agent2Wp\Context\instructions_get_content();
    if (trim($custom) === '') {
        return $instructions;
    }

    if (str_starts_with($instructions, $custom . "\n\n")) {
        return $instructions;
    }

    return $custom . "\n\n" . $instructions;
}

add_filter('agent2wp_discover_abilities_instructions', callback: 'agent2wp_inject_custom_instructions', priority: 6);

/**
 * Add the Agent2Wp AI Abilities status and toggle to the WordPress admin bar.
 */
function agent2wp_register_admin_bar_toggle(\WP_Admin_Bar $wp_admin_bar): void
{
    if (!agent2wp_current_user_can_manage()) {
        return;
    }

    $dependency_error = agent2wp_get_mcp_dependency_error();
    $configured_enabled = agent2wp_is_enabled();
    $active = $configured_enabled && $dependency_error === null;
    $can_enable = $configured_enabled || $dependency_error === null;
    $target = $configured_enabled ? 'off' : 'on';
    $toggle_url = wp_nonce_url(
        admin_url('admin-post.php?action=agent2wp_toggle_ai_abilities&agent2wp_target=' . $target),
        action: 'agent2wp_toggle_ai_abilities',
    );

    $easy_ready =
        function_exists('agent2wp_is_setup_complete') && agent2wp_easy_mode_enabled() && agent2wp_is_setup_complete();
    $easy_url = function_exists('agent2wp_easy_connect_url')
        ? agent2wp_easy_connect_url($easy_ready ? ['agent2wp_autocopy' => '1'] : [])
        : admin_url('admin.php?page=agent2wp-connect');

    $wp_admin_bar->add_node([
        'id' => 'agent2wp-mcp-status',
        'title' => match (true) {
            $easy_ready => esc_html__('Agent2Wp ✓', domain: 'agent2wp'),
            $active => esc_html__('Agent2Wp ON', domain: 'agent2wp'),
            $configured_enabled => esc_html__('Agent2Wp ERROR', domain: 'agent2wp'),
            default => esc_html__('Agent2Wp', domain: 'agent2wp'),
        },
        'href' => $easy_url,
        'meta' => [
            'class' => match (true) {
                $active => 'agent2wp-mcp-on',
                $configured_enabled => 'agent2wp-mcp-error',
                default => 'agent2wp-mcp-off',
            },
        ],
    ]);

    $wp_admin_bar->add_node([
        'id' => 'agent2wp-mcp-status-label',
        'parent' => 'agent2wp-mcp-status',
        'title' => match (true) {
            $active => esc_html__('AI Abilities: On', domain: 'agent2wp'),
            $configured_enabled => esc_html__('AI Abilities: Error', domain: 'agent2wp'),
            default => esc_html__('AI Abilities: Off', domain: 'agent2wp'),
        },
    ]);

    if (!$can_enable) {
        $wp_admin_bar->add_node([
            'id' => 'agent2wp-mcp-unavailable',
            'parent' => 'agent2wp-mcp-status',
            'title' => esc_html__('AI Abilities unavailable', domain: 'agent2wp'),
            'href' => $easy_url,
        ]);
    }

    if ($easy_ready) {
        $wp_admin_bar->add_node([
            'id' => 'agent2wp-mcp-copy',
            'parent' => 'agent2wp-mcp-status',
            'title' => esc_html__('Copy for AI', domain: 'agent2wp'),
            'href' => agent2wp_easy_connect_url(['agent2wp_autocopy' => '1']),
        ]);
    } elseif ($can_enable) {
        $wp_admin_bar->add_node([
            'id' => 'agent2wp-mcp-toggle',
            'parent' => 'agent2wp-mcp-status',
            'title' => $configured_enabled
                ? esc_html__('Turn Off AI Abilities', domain: 'agent2wp')
                : esc_html__('Turn On AI Abilities', domain: 'agent2wp'),
            'href' => $toggle_url,
            'meta' => [
                'class' => $configured_enabled ? 'agent2wp-mcp-toggle-off' : 'agent2wp-mcp-toggle-on',
            ],
        ]);
    }

    $wp_admin_bar->add_node([
        'id' => 'agent2wp-mcp-config',
        'parent' => 'agent2wp-mcp-status',
        'title' => agent2wp_easy_mode_enabled()
            ? esc_html__('Open Agent2Wp', domain: 'agent2wp')
            : esc_html__('Configuration', domain: 'agent2wp'),
        'href' => $easy_url,
    ]);
}

/**
 * Style the admin-bar status chip and require confirmation before enabling from the dropdown.
 */
function agent2wp_render_admin_bar_toggle_assets(): void
{
    if (!agent2wp_current_user_can_manage() || !is_admin_bar_showing()) {
        return;
    }

    $looks_production = agent2wp_looks_like_production();
    $confirm_message = $looks_production
        ? __(
            'This looks like a production site. AI Abilities are intended for staging or development sites. Continue anyway?',
            domain: 'agent2wp',
        )
        : __('AI agents will be able to execute PHP code and access the filesystem. Continue?', domain: 'agent2wp');
    ?>
    <style>
    #wp-admin-bar-agent2wp-mcp-status.agent2wp-mcp-on > .ab-item {
        background: #c00 !important;
        color: #fff !important;
    }
    #wp-admin-bar-agent2wp-mcp-status.agent2wp-mcp-error > .ab-item {
        background: #996800 !important;
        color: #fff !important;
    }
    #wp-admin-bar-agent2wp-mcp-status-label > .ab-item {
        cursor: default;
        font-weight: 600;
    }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.querySelector('#wp-admin-bar-agent2wp-mcp-toggle.agent2wp-mcp-toggle-on > .ab-item');
        if (!toggle) {
            return;
        }
        toggle.addEventListener('click', function (event) {
            if (!window.confirm(<?php echo wp_json_encode($confirm_message); ?>)) {
                event.preventDefault();
            }
        });
    });
    </script>
    <?php
}

add_action('admin_bar_menu', callback: 'agent2wp_register_admin_bar_toggle', priority: 999);
add_action('admin_head', callback: 'agent2wp_render_admin_bar_toggle_assets');
add_action('wp_head', callback: 'agent2wp_render_admin_bar_toggle_assets');

// Suppress noisy admin notices on the Configuration page via CSS: hide notices that are not
// emitted by Agent2Wp. Cheap and side-effect free, unlike iterating $wp_filter
// with Reflection (which causes memory blow-ups when Query Monitor captures every remove_action).
add_action('admin_head', static function () {
    if (agent2wp_admin_request_page() !== 'agent2wp-connect') {
        return;
    }
    ?>
    <style id="agent2wp-suppress-foreign-notices">
        .wrap > .notice:not(.agent2wp-notice):not(.agent2wp-keep),
        #wpbody-content > .notice:not(.agent2wp-notice):not(.agent2wp-keep),
        #wpbody-content > .updated:not(.agent2wp-keep),
        #wpbody-content > .error:not(.agent2wp-keep) {
            display: none !important;
        }
    </style>
    <?php
});

// Handle form actions early (before headers are sent) for PRG redirect.
add_action('admin_init', static function () {
    $page = agent2wp_admin_request_page();
    if ($page === 'agent2wp-sandbox') {
        agent2wp_handle_sandbox_actions();
    }
    if ($page === 'agent2wp-connect') {
        agent2wp_handle_revoke_password();
        agent2wp_handle_dismiss_production_warning();
    }
    if ($page === 'agent2wp-abilities') {
        agent2wp_handle_ability_hub_actions();
    }
});

// Single-row toggle over AJAX so the page state (open sections) is preserved.
add_action('wp_ajax_agent2wp_toggle_ability', callback: 'agent2wp_handle_ability_toggle_ajax');

// Admin page stylesheets — card layouts matching Skills.
add_action('admin_enqueue_scripts', static function (string $hook): void {
    if ($hook === 'agent2wp_page_agent2wp-abilities') {
        wp_enqueue_style(
            'agent2wp-hub-admin',
            (string) AGENT2WP_PLUGIN_URL . 'includes/assets/hub.css',
            [],
            AGENT2WP_VERSION,
        );
        wp_enqueue_script(
            'agent2wp-hub-admin',
            (string) AGENT2WP_PLUGIN_URL . 'includes/assets/hub.js',
            [],
            AGENT2WP_VERSION,
            args: true,
        );
    }

    if ($hook === 'agent2wp_page_agent2wp-sandbox') {
        wp_enqueue_style(
            'agent2wp-sandbox-admin',
            (string) AGENT2WP_PLUGIN_URL . 'includes/assets/sandbox.css',
            [],
            AGENT2WP_VERSION,
        );
    }
});

// Register admin menus.
add_action('admin_menu', static function () {
    // Top-level menu item (shows the Connect page).
    add_menu_page(
        page_title: __('Agent2Wp', domain: 'agent2wp'),
        menu_title: 'Agent2Wp',
        capability: agent2wp_manage_capability(),
        menu_slug: 'agent2wp-connect',
        callback: 'agent2wp_render_connect_page',
        icon_url: 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PHBhdGggZmlsbD0iIzYzNjZmMSIgZD0iTTUgNGg2LjVsOS41IDE2LjVWNEgyN3YyNGgtNi41TDExIDExLjVWMjhINVY0WiIvPjwvc3ZnPg==',
        position: 3,
    );

    add_submenu_page(
        parent_slug: 'agent2wp-connect',
        page_title: __('Agent2Wp', domain: 'agent2wp'),
        menu_title: __('Start', domain: 'agent2wp'),
        capability: agent2wp_manage_capability(),
        menu_slug: 'agent2wp-connect',
        callback: 'agent2wp_render_connect_page',
    );

    // Abilities Hub sub-page.
    add_submenu_page(
        parent_slug: 'agent2wp-connect',
        page_title: __('Abilities Hub', domain: 'agent2wp'),
        menu_title: __('Abilities Hub', domain: 'agent2wp'),
        capability: agent2wp_manage_capability(),
        menu_slug: 'agent2wp-abilities',
        callback: 'agent2wp_render_settings_page',
    );

    // Sandbox sub-page.
    add_submenu_page(
        parent_slug: 'agent2wp-connect',
        page_title: __('Sandbox', domain: 'agent2wp'),
        menu_title: __('Sandbox', domain: 'agent2wp'),
        capability: agent2wp_manage_capability(),
        menu_slug: 'agent2wp-sandbox',
        callback: 'agent2wp_render_sandbox_page',
    );
});

$is_enabled = agent2wp_is_enabled();

if (!$is_enabled && agent2wp_is_domain_mismatch()) {
    add_action('admin_notices', static function () {
        if (!agent2wp_current_user_can_manage()) {
            return;
        }
        /** @var string $locked */
        $locked = get_option('agent2wp_ai_abilities_domain', default_value: '');
        wp_admin_notice(
            sprintf(
                esc_html__(
                    'Agent2Wp AI Abilities were disabled because the site domain changed (enabled on %s). Re-enable them from the Configuration page if this is intentional.',
                    domain: 'agent2wp',
                ),
                '<code>' . esc_html($locked) . '</code>',
            ),
            ['type' => 'warning', 'dismissible' => true],
        );
    });
}

if ($is_enabled) {
    agent2wp_load_gutenberg_runtime();

    // Brand the default MCP server. Usage instructions are returned from the
    // discover-abilities tool instead of the initialize handshake.
    add_filter('mcp_adapter_default_server_config', static function (mixed $config): mixed {
        if (!is_array($config)) {
            return $config;
        }
        $config['server_id'] = 'agent2wp';
        $config['server_route'] = 'agent2wp';
        $config['server_name'] = 'Agent2Wp';
        return $config;
    });

    // Register a legacy alias server at the old slug so configs that still point at
    // /wp-json/mcp/mcp-adapter-default-server keep working after the rename.
    add_action('mcp_adapter_init', callback: 'agent2wp_register_legacy_mcp_server', priority: 20);

    // Initialize bundled MCP Adapter — its default server exposes our abilities automatically.
    if (!agent2wp_initialize_mcp_adapter()) {
        $is_enabled = false;
    }
}

/**
 * Register a legacy alias of the canonical Agent2Wp MCP server at the pre-rename slug.
 *
 * The canonical server is registered under `/mcp/agent2wp`. Older client configs may still
 * point at `/mcp/mcp-adapter-default-server` from before the rename — this alias keeps them
 * working with identical behavior (same tools, same auto-discovered resources and prompts).
 */
function agent2wp_register_legacy_mcp_server(mixed $adapter): void
{
    if (!$adapter instanceof \WP\MCP\Core\McpAdapter) {
        return;
    }

    if ($adapter->get_server('agent2wp') === null) {
        return;
    }

    $adapter->create_server(
        'mcp-adapter-default-server',
        'mcp',
        'mcp-adapter-default-server',
        'Agent2Wp (legacy alias)',
        'Legacy alias for the Agent2Wp MCP server. New client configurations should use /wp-json/mcp/agent2wp.',
        'v1.0.0',
        [\WP\MCP\Transport\HttpTransport::class],
        \WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
        \WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class,
        [
            'mcp-adapter/discover-abilities',
            'mcp-adapter/get-ability-info',
            'mcp-adapter/execute-ability',
        ],
        agent2wp_discover_public_abilities('resource'),
        agent2wp_discover_public_abilities('prompt'),
    );
}

/**
 * Replicate DefaultServerFactory::discover_abilities_by_type for reuse on the legacy alias.
 *
 * @return list<string>
 */
function agent2wp_discover_public_abilities(string $type): array
{
    if (!function_exists('wp_get_abilities')) {
        return [];
    }

    $abilities = wp_get_abilities();
    $filtered = [];
    foreach ($abilities as $ability) {
        $meta = $ability->get_meta();
        if (!($meta['mcp']['public'] ?? false)) {
            continue;
        }
        $ability_type = (string) ($meta['mcp']['type'] ?? 'tool');
        if ($ability_type !== $type) {
            continue;
        }
        $filtered[] = $ability->get_name();
    }

    return $filtered;
}

if ($is_enabled) {
    // The `mcp-adapter/execute-ability` dispatcher wraps every ability return in
    // `{ success: true, data: <inner> }`. When the inner value is itself
    // `{ success: false, error: "..." }` the outer `success: true` masks a real
    // logical failure, and agents that check the top-level flag — a very
    // reasonable default — silently march past the error. Unwrap that shape
    // here so the adapter's backward-compat path (ToolsHandler) turns it into a
    // proper `isError: true` CallToolResult.
    //
    // ToolsHandler::create_error_result flattens the response to a bare
    // `content: [text(error)], structuredContent: null, isError: true` — every
    // sibling field on the ability's return is discarded. Validators attach
    // structured repair hints (`invalid_values`, `unknown_properties`,
    // `collision_paths`, `suggested_name`, `failed_paths`, `overwritten_paths`,
    // `errors`, `schemas`, `style_errors`, `dynamic_tag_errors`, `dropped_keys`,
    // `schema`, …) that the agent needs to self-correct without a
    // round-trip — so embed whatever else the ability returned as a JSON
    // suffix on the error message. The suffix rides inside the string and
    // survives the downstream flatten.
    add_filter(
        'mcp_adapter_tool_call_result',
        static function (mixed $result, array $args, string $tool_name): mixed {
            // Tool names are MCP-sanitized from ability slugs — `/` becomes `-`.
            if ($tool_name !== 'mcp-adapter-execute-ability') {
                return $result;
            }
            if (!is_array($result) || ($result['success'] ?? null) !== true) {
                return $result;
            }
            /** @var array<array-key, mixed>|null $data */
            $data = $result['data'] ?? null;
            if (!is_array($data) || ($data['success'] ?? null) !== false) {
                return $result;
            }
            /** @var string|null $error */
            $error = $data['error'] ?? null;
            if (!is_string($error) || trim($error) === '') {
                return $result;
            }

            $detail = $data;
            unset($detail['success'], $detail['error']);
            if ($detail !== []) {
                $encoded = wp_json_encode($detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (is_string($encoded)) {
                    $data['error'] = $error . "\n\nStructured detail (JSON):\n" . $encoded;
                }
            }

            return $data;
        },
        priority: 10,
        accepted_args: 3,
    );

    // Fix empty "properties" in JSON Schema: PHP json_encode outputs [] instead of {}.
    // MCP clients reject tools with invalid schemas, so we fix this in the REST response.
    add_filter('rest_pre_echo_response', static function (mixed $result): mixed {
        if (!is_array($result)) {
            return $result;
        }
        /** @var \stdClass|null $resultObj */
        $resultObj = $result['result'] ?? null;
        if (!$resultObj instanceof \stdClass) {
            return $result;
        }
        /** @var list<array<string, mixed>>|null $tools */
        $tools = $resultObj->tools ?? null;
        if (!is_array($tools)) {
            return $result;
        }
        foreach ($tools as &$tool) {
            foreach (['inputSchema', 'outputSchema'] as $key) {
                /** @var array<string, mixed>|null $schema */
                $schema = $tool[$key] ?? null;
                if (!is_array($schema) || ($schema['properties'] ?? null) !== []) {
                    continue;
                }
                $schema['properties'] = new \stdClass();
                $tool[$key] = $schema;
            }
        }
        $resultObj->tools = $tools;
        return $result;
    });

    // Info notice if the standalone MCP Adapter plugin is still active.
    if (function_exists('is_plugin_active') && is_plugin_active('mcp-adapter/mcp-adapter.php')) {
        add_action('admin_notices', static function () {
            if (!agent2wp_current_user_can_manage()) {
                return;
            }
            wp_admin_notice(
                esc_html__(
                    'Agent2Wp bundles the MCP Adapter. You can safely deactivate the standalone MCP Adapter plugin.',
                    domain: 'agent2wp',
                ),
                [
                    'type' => 'info',
                    'dismissible' => true,
                ],
            );
        });
    }

    // Register ability categories.
    add_action('wp_abilities_api_categories_init', static function () {
        wp_register_ability_category('code-execution', [
            'label' => __('Code Execution', domain: 'agent2wp'),
            'description' => __('Abilities that execute code on the WordPress server.', domain: 'agent2wp'),
        ]);

        wp_register_ability_category('filesystem', [
            'label' => __('Filesystem', domain: 'agent2wp'),
            'description' => __('Server filesystem operations.', domain: 'agent2wp'),
        ]);

        wp_register_ability_category('admin-access', [
            'label' => __('Admin Access', domain: 'agent2wp'),
            'description' => __('Temporary browser access to WordPress admin.', domain: 'agent2wp'),
        ]);

        if (wp_get_ability_category('mcp-adapter') === null) {
            wp_register_ability_category('mcp-adapter', [
                'label' => __('MCP Adapter', domain: 'agent2wp'),
                'description' => __('Meta-abilities for MCP protocol bridging.', domain: 'agent2wp'),
            ]);
        }

        wp_register_ability_category('gutenberg', [
            'label' => __('Gutenberg', domain: 'agent2wp'),
            'description' => __(
                'Gutenberg content abilities, including the Block Editor Queue for native/static blocks that need browser JS finalization. At the start of Gutenberg work, check the queue runtime and ask the user to keep the Block Editor Queue page open when static/native blocks may be queued.',
                domain: 'agent2wp',
            ),
        ]);

        wp_register_ability_category('content', [
            'label' => __('Content', domain: 'agent2wp'),
            'description' => __('WordPress post and page lifecycle operations.', domain: 'agent2wp'),
        ]);
    });

    // Register abilities.
    add_action('wp_abilities_api_init', static function () {
        $dir = __DIR__ . '/includes/abilities/';
        require_once $dir . 'execute-php.php';
        require_once $dir . 'read-file.php';
        require_once $dir . 'write-file.php';
        require_once $dir . 'edit-file.php';
        require_once $dir . 'delete-file.php';
        require_once $dir . 'create-upload-link.php';
        require_once $dir . 'create-admin-access-link.php';
        require_once $dir . 'disable-file.php';
        require_once $dir . 'enable-file.php';
        require_once $dir . 'list-directory.php';
        require_once $dir . 'discover-abilities.php';
        require_once $dir . 'run-wp-cli.php';
        agent2wp_load_gutenberg_abilities();
    });
}

add_action('wp_abilities_api_init', callback: 'agent2wp_apply_ability_policy', priority: PHP_INT_MAX);

// Ensure sandbox directory exists.
wp_mkdir_p(AGENT2WP_SANDBOX_DIR);

// Load sandbox plugins.
require_once __DIR__ . '/includes/sandbox-loader.php';
