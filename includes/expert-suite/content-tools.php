<?php

// SPDX-FileCopyrightText: 2026 Taibur Rahaman <https://github.com/Taibur-Rahaman>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

/**
 * Expert Suite content tools — full WordPress post lifecycle for AI agents.
 */

if (!defined('ABSPATH')) {
    exit();
}

add_action('wp_abilities_api_init', static function (): void {
    if (!function_exists('wp_register_ability')) {
        return;
    }

    wp_register_ability('agent2wp/create-post', [
        'label' => __('Create Post or Page', domain: 'agent2wp'),
        'description' => __('Creates a WordPress post or page.', domain: 'agent2wp'),
        'category' => 'content',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'post_type' => ['type' => 'string', 'enum' => ['post', 'page'], 'default' => 'post'],
                'title' => ['type' => 'string', 'minLength' => 1],
                'content' => ['type' => 'string'],
                'status' => [
                    'type' => 'string',
                    'enum' => ['draft', 'publish', 'pending', 'private'],
                    'default' => 'draft',
                ],
                'slug' => ['type' => 'string'],
            ],
            'required' => ['title'],
            'additionalProperties' => false,
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'post_id' => ['type' => 'integer'],
                'edit_url' => ['type' => 'string'],
                'permalink' => ['type' => 'string'],
                'error' => ['type' => 'string'],
            ],
        ],
        'execute_callback' => 'agent2wp_content_create_post',
        'permission_callback' => 'agent2wp_permission_callback',
        'meta' => ['show_in_rest' => true, 'mcp' => ['public' => true]],
    ]);

    wp_register_ability('agent2wp/get-post', [
        'label' => __('Get Post or Page', domain: 'agent2wp'),
        'description' => __('Retrieves a WordPress post or page by ID.', domain: 'agent2wp'),
        'category' => 'content',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'post_id' => ['type' => 'integer', 'minimum' => 1],
            ],
            'required' => ['post_id'],
            'additionalProperties' => false,
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'post' => ['type' => 'object'],
                'error' => ['type' => 'string'],
            ],
        ],
        'execute_callback' => 'agent2wp_content_get_post',
        'permission_callback' => 'agent2wp_permission_callback',
        'meta' => ['show_in_rest' => true, 'mcp' => ['public' => true]],
    ]);

    wp_register_ability('agent2wp/list-posts', [
        'label' => __('List Posts or Pages', domain: 'agent2wp'),
        'description' => __('Lists WordPress posts or pages with optional filters.', domain: 'agent2wp'),
        'category' => 'content',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'post_type' => ['type' => 'string', 'default' => 'post'],
                'status' => ['type' => 'string', 'default' => 'any'],
                'search' => ['type' => 'string'],
                'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            ],
            'additionalProperties' => false,
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'posts' => ['type' => 'array'],
                'total' => ['type' => 'integer'],
                'error' => ['type' => 'string'],
            ],
        ],
        'execute_callback' => 'agent2wp_content_list_posts',
        'permission_callback' => 'agent2wp_permission_callback',
        'meta' => ['show_in_rest' => true, 'mcp' => ['public' => true]],
    ]);

    wp_register_ability('agent2wp/update-post', [
        'label' => __('Update Post or Page', domain: 'agent2wp'),
        'description' => __('Updates an existing WordPress post or page.', domain: 'agent2wp'),
        'category' => 'content',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'post_id' => ['type' => 'integer', 'minimum' => 1],
                'title' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'status' => ['type' => 'string', 'enum' => ['draft', 'publish', 'pending', 'private', 'trash']],
                'slug' => ['type' => 'string'],
            ],
            'required' => ['post_id'],
            'additionalProperties' => false,
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'post_id' => ['type' => 'integer'],
                'edit_url' => ['type' => 'string'],
                'permalink' => ['type' => 'string'],
                'error' => ['type' => 'string'],
            ],
        ],
        'execute_callback' => 'agent2wp_content_update_post',
        'permission_callback' => 'agent2wp_permission_callback',
        'meta' => ['show_in_rest' => true, 'mcp' => ['public' => true]],
    ]);

    wp_register_ability('agent2wp/delete-post', [
        'label' => __('Delete Post or Page', domain: 'agent2wp'),
        'description' => __('Deletes a WordPress post or page.', domain: 'agent2wp'),
        'category' => 'content',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'post_id' => ['type' => 'integer', 'minimum' => 1],
                'force' => ['type' => 'boolean', 'default' => false],
            ],
            'required' => ['post_id'],
            'additionalProperties' => false,
        ],
        'output_schema' => [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'post_id' => ['type' => 'integer'],
                'error' => ['type' => 'string'],
            ],
        ],
        'execute_callback' => 'agent2wp_content_delete_post',
        'permission_callback' => 'agent2wp_permission_callback',
        'meta' => ['show_in_rest' => true, 'mcp' => ['public' => true]],
    ]);
});

/** @param array<string, mixed> $input */
function agent2wp_content_create_post(array $input): array
{
    $title = sanitize_text_field((string) ($input['title'] ?? ''));
    if ($title === '') {
        return ['success' => false, 'error' => __('Title is required.', domain: 'agent2wp')];
    }

    $post_type = in_array($input['post_type'] ?? 'post', ['post', 'page'], strict: true)
        ? (string) $input['post_type']
        : 'post';

    $postarr = [
        'post_type' => $post_type,
        'post_title' => $title,
        'post_content' => wp_kses_post((string) ($input['content'] ?? '')),
        'post_status' => agent2wp_content_sanitize_status((string) ($input['status'] ?? 'draft')),
    ];

    if (isset($input['slug']) && is_string($input['slug']) && $input['slug'] !== '') {
        $postarr['post_name'] = sanitize_title($input['slug']);
    }

    $post_id = wp_insert_post($postarr, true);
    if (is_wp_error($post_id)) {
        return ['success' => false, 'error' => $post_id->get_error_message()];
    }

    return agent2wp_content_post_result((int) $post_id);
}

/** @param array<string, mixed> $input */
function agent2wp_content_get_post(array $input): array
{
    $post_id = (int) ($input['post_id'] ?? 0);
    $post = get_post($post_id);
    if ($post === null) {
        return ['success' => false, 'error' => __('Post not found.', domain: 'agent2wp')];
    }

    return [
        'success' => true,
        'post' => [
            'id' => $post->ID,
            'type' => $post->post_type,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'status' => $post->post_status,
            'slug' => $post->post_name,
            'modified' => $post->post_modified_gmt,
            'permalink' => (string) get_permalink($post),
        ],
    ];
}

/** @param array<string, mixed> $input */
function agent2wp_content_list_posts(array $input): array
{
    $query = new WP_Query([
        'post_type' => sanitize_key((string) ($input['post_type'] ?? 'post')),
        'post_status' => sanitize_key((string) ($input['status'] ?? 'any')),
        's' => sanitize_text_field((string) ($input['search'] ?? '')),
        'posts_per_page' => min(100, max(1, (int) ($input['per_page'] ?? 20))),
        'no_found_rows' => false,
    ]);

    $posts = [];
    foreach ($query->posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }
        $posts[] = [
            'id' => $post->ID,
            'type' => $post->post_type,
            'title' => $post->post_title,
            'status' => $post->post_status,
            'slug' => $post->post_name,
            'modified' => $post->post_modified_gmt,
        ];
    }

    return ['success' => true, 'posts' => $posts, 'total' => (int) $query->found_posts];
}

/** @param array<string, mixed> $input */
function agent2wp_content_update_post(array $input): array
{
    $post_id = (int) ($input['post_id'] ?? 0);
    if ($post_id <= 0 || get_post($post_id) === null) {
        return ['success' => false, 'error' => __('Post not found.', domain: 'agent2wp')];
    }

    $postarr = ['ID' => $post_id];
    if (isset($input['title'])) {
        $postarr['post_title'] = sanitize_text_field((string) $input['title']);
    }
    if (isset($input['content'])) {
        $postarr['post_content'] = wp_kses_post((string) $input['content']);
    }
    if (isset($input['status'])) {
        $postarr['post_status'] = agent2wp_content_sanitize_status((string) $input['status']);
    }
    if (isset($input['slug']) && is_string($input['slug']) && $input['slug'] !== '') {
        $postarr['post_name'] = sanitize_title($input['slug']);
    }

    $result = wp_update_post($postarr, true);
    if (is_wp_error($result)) {
        return ['success' => false, 'error' => $result->get_error_message()];
    }

    return agent2wp_content_post_result($post_id);
}

/** @param array<string, mixed> $input */
function agent2wp_content_delete_post(array $input): array
{
    $post_id = (int) ($input['post_id'] ?? 0);
    if ($post_id <= 0 || get_post($post_id) === null) {
        return ['success' => false, 'error' => __('Post not found.', domain: 'agent2wp')];
    }

    $force = filter_var($input['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $deleted = wp_delete_post($post_id, $force);
    if ($deleted === false || $deleted === null) {
        return ['success' => false, 'error' => __('Could not delete post.', domain: 'agent2wp')];
    }

    return ['success' => true, 'post_id' => $post_id];
}

function agent2wp_content_sanitize_status(string $status): string
{
    $allowed = ['draft', 'publish', 'pending', 'private', 'trash'];
    return in_array($status, $allowed, strict: true) ? $status : 'draft';
}

/** @return array<string, mixed> */
function agent2wp_content_post_result(int $post_id): array
{
    return [
        'success' => true,
        'post_id' => $post_id,
        'edit_url' => (string) get_edit_post_link($post_id, context: 'raw'),
        'permalink' => (string) get_permalink($post_id),
    ];
}
