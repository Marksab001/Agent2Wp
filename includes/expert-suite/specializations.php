<?php

// SPDX-FileCopyrightText: 2026 Taibur Rahaman <https://github.com/Taibur-Rahaman>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Agent2Wp\ExpertSuite\Specializations;

use Agent2Wp\Skills\Parser;

if (!defined('ABSPATH')) {
    exit();
}

const SOURCE_ID = 'agent2wp-expert';

const SOURCE_LABEL = 'Expert Suite';

const SOURCE_PRIORITY = 5;

/**
 * @param array<string, array{id: string, priority: int, label: string, loader: callable(): list<array<string,mixed>>}> $sources
 * @return array<string, array{id: string, priority: int, label: string, loader: callable(): list<array<string,mixed>>}>
 */
function register(array $sources): array
{
    $sources[SOURCE_ID] = [
        'id' => SOURCE_ID,
        'priority' => SOURCE_PRIORITY,
        'label' => SOURCE_LABEL,
        'loader' => __NAMESPACE__ . '\\load',
    ];
    return $sources;
}

/**
 * @return array<string, callable(): bool>
 */
function integration_detectors(): array
{
    return [
        'wordpress-content' => static fn(): bool => true,
        'memory' => static fn(): bool => true,
        'site-rulesbook' => static fn(): bool => true,
        'elementor' => static fn(): bool => defined('ELEMENTOR_VERSION'),
        'xpro' => static fn(): bool => agent2wp_detect_xpro(),
        'wpforms' => static fn(): bool => defined('WPFORMS_VERSION') || function_exists('wpforms'),
        'bricks' => static fn(): bool => defined('BRICKS_VERSION'),
        'divi' => static fn(): bool => defined('ET_BUILDER_VERSION'),
        'breakdance' => static fn(): bool => function_exists('Breakdance\\Data\\get_global_option'),
        'wpbakery' => static fn(): bool => defined('WPB_VC_VERSION'),
        'etch' => static fn(): bool => class_exists('Etch\\Plugin'),
        'generatepress' => static fn(): bool => function_exists('generate_get_option'),
        'kadence' => static fn(): bool => class_exists('Kadence\\Theme'),
        'mosaic' => static fn(): bool => class_exists('Mosaic\\Database\\MosaicDB'),
        'acf' => static fn(): bool => class_exists('ACF'),
        'jetengine' => static fn(): bool => function_exists('jet_engine'),
        'meta-box' => static fn(): bool => defined('RWMB_VER'),
        'pods' => static fn(): bool => defined('PODS_VERSION'),
        'acpt' => static fn(): bool => defined('ACPT_PLUGIN_VERSION'),
        'ase' => static fn(): bool => defined('ASENHA_VERSION'),
        'woocommerce' => static fn(): bool => class_exists('WooCommerce'),
        'code-snippets' => static fn(): bool => defined('CODE_SNIPPETS_VERSION'),
    ];
}

/**
 * @return list<array{slug: string, name: string, description: string, content: string, enable_prompt: bool, enable_agentic: bool}>
 */
function load(): array
{
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }

    $detectors = integration_detectors();
    $result = [];
    $dir = __DIR__ . '/packs';
    $files = is_dir($dir) ? glob($dir . '/*.md') : false;

    if (is_array($files)) {
        sort($files);
        foreach ($files as $path) {
            $slug = Parser\normalize_slug(basename($path, suffix: '.md'));
            if ($slug === '') {
                continue;
            }

            $detect = $detectors[$slug] ?? null;
            if ($detect !== null && !$detect()) {
                continue;
            }

            $raw = file_get_contents($path);
            if ($raw === false) {
                continue;
            }

            $parsed = Parser\parse($raw);
            if ($parsed['parse_error'] !== null || trim($parsed['body']) === '') {
                continue;
            }

            $result[] = [
                'slug' => $slug,
                'name' => $parsed['name'] !== '' ? $parsed['name'] : $slug,
                'description' => $parsed['description'],
                'content' => $parsed['body'],
                'enable_prompt' => $parsed['enable_prompt'],
                'enable_agentic' => $parsed['enable_agentic'],
            ];
        }
    }

    $cached = $result;
    return $result;
}

add_filter('agent2wp_skill_lookup_sources', __NAMESPACE__ . '\\register');

/**
 * Detect XPRO header/footer builder (common constant/class patterns).
 */
function agent2wp_detect_xpro(): bool
{
    return (
        defined('XPRO_VERSION')
        || defined('XPRO_PLUGIN_VERSION')
        || class_exists('XPRO\\Plugin')
        || class_exists('Xpro\\Plugin')
        || function_exists('xpro_init')
    );
}
