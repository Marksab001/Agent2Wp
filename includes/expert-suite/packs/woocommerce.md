---
name: WooCommerce
description: Manage WooCommerce products, orders, and store settings on this site.
enable_prompt: true
enable_agentic: true
---

## WooCommerce on this site

WooCommerce is active. Use WooCommerce APIs and CRUD objects instead of raw SQL.

## Products

- Create products with `WC_Product_Simple` or appropriate product class.
- Use `$product->save()` after setting props (name, regular_price, stock_status, etc.).
- Product meta and attributes: use `$product->set_attributes()` and `$product->update_meta_data()`.

## Orders

- Read orders with `wc_get_order( $id )`.
- Never delete orders unless the user explicitly requests it.

## Settings

- Store options via `update_option` only for known WooCommerce option keys.
- Prefer `WC_Admin_Settings` patterns for structured settings changes.

Test checkout on staging after catalog or pricing changes.
