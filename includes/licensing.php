<?php

// SPDX-FileCopyrightText: 2026 Taibur Rahaman <https://github.com/Taibur-Rahaman>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

/**
 * Agent2Wp licensing — Expert Suite is bundled and always active.
 * No external license server, no activation keys, no tier gating.
 */

if (!defined('ABSPATH')) {
    exit();
}

const AGENT2WP_LICENSE_TIER = 'expert';

const AGENT2WP_LICENSE_STATUS = 'active';

if (!defined('AGENT2WP_EXPERT_VERSION')) {
    define('AGENT2WP_EXPERT_VERSION', AGENT2WP_VERSION);
}

/** @deprecated Alias kept for compatibility with legacy checks. */
if (!defined('AGENT2WP_PRO_VERSION')) {
    define('AGENT2WP_PRO_VERSION', AGENT2WP_EXPERT_VERSION);
}

/**
 * @return array{valid: bool, tier: string, status: string, expires: null, holder: string}
 */
function agent2wp_license_status(): array
{
    return [
        'valid' => true,
        'tier' => AGENT2WP_LICENSE_TIER,
        'status' => AGENT2WP_LICENSE_STATUS,
        'expires' => null,
        'holder' => (string) get_bloginfo('name'),
    ];
}

function agent2wp_license_is_valid(): bool
{
    return true;
}

function agent2wp_expert_is_active(): bool
{
    return true;
}

function agent2wp_pro_is_active(): bool
{
    return agent2wp_expert_is_active();
}

/**
 * Short-circuit any future license HTTP checks.
 *
 * @param mixed $response
 * @return mixed
 */
add_filter(
    'pre_http_request',
    static function ($response, array $args, string $url) {
        if (
            str_contains($url, 'license.dynamic.ooo')
            || str_contains($url, 'novamira.ai')
            || str_contains($url, 'agent2wp.ai/license')
        ) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body' => wp_json_encode([
                    'valid' => true,
                    'tier' => AGENT2WP_LICENSE_TIER,
                    'status' => AGENT2WP_LICENSE_STATUS,
                ]),
                'headers' => ['content-type' => 'application/json'],
            ];
        }
        return $response;
    },
    accepted_args: 3,
);

add_action(
    'admin_menu',
    static function (): void {
        add_submenu_page(
            parent_slug: 'agent2wp-connect',
            page_title: __('Expert Suite', domain: 'agent2wp'),
            menu_title: __('Expert Suite', domain: 'agent2wp'),
            capability: agent2wp_manage_capability(),
            menu_slug: 'agent2wp-expert',
            callback: 'agent2wp_render_expert_license_page',
        );
    },
    priority: 15,
);

function agent2wp_render_expert_license_page(): void
{
    if (!agent2wp_current_user_can_manage()) {
        wp_die(esc_html__('You are not allowed to view this page.', domain: 'agent2wp'));
    }

    $status = agent2wp_license_status();
    if (function_exists('agent2wp_render_admin_header')) {
        agent2wp_render_admin_header();
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Expert Suite', domain: 'agent2wp'); ?></h1>
        <div class="card" style="max-width:640px;padding:20px;border-left:4px solid #6366f1;">
            <p style="margin-top:0;font-size:15px;">
                <strong><?php esc_html_e('Status:', domain: 'agent2wp'); ?></strong>
                <?php esc_html_e('Active — lifetime', domain: 'agent2wp'); ?>
            </p>
            <p><?php esc_html_e(
                'All Expert Suite capabilities are unlocked: specializations, content tools, session memory, and MCP skills. No license key required.',
                domain: 'agent2wp',
            ); ?></p>
            <ul style="list-style:disc;margin-left:18px;">
                <li><?php esc_html_e(
                    'Plugin specializations (page builders, commerce, custom fields)',
                    domain: 'agent2wp',
                ); ?></li>
                <li><?php esc_html_e(
                    'WordPress content tools (create, read, update, delete)',
                    domain: 'agent2wp',
                ); ?></li>
                <li><?php esc_html_e('Persistent agent memory via Context', domain: 'agent2wp'); ?></li>
            </ul>
            <p style="margin-bottom:0;color:#646970;font-size:12px;">
                <?php

                printf(
                    /* translators: 1: tier name, 2: site name */
                    esc_html__('Tier: %1$s · Site: %2$s', domain: 'agent2wp'),
                    esc_html((string) $status['tier']),
                    esc_html((string) $status['holder']),
                );
                ?>
            </p>
        </div>
        <p style="color:#646970;">
            <?php

            echo
                wp_kses(
                    sprintf(
                        __(
                            'Developed by <a href="%s" target="_blank" rel="noopener">Taibur Rahaman</a>',
                            domain: 'agent2wp',
                        ),
                        'https://github.com/Taibur-Rahaman',
                    ),
                    ['a' => ['href' => true, 'target' => true, 'rel' => true]],
                )
            ;
            ?>
        </p>
    </div>
    <?php
}
