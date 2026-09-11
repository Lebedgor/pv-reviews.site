# Photo & Video Reviews for WooCommerce — Full Functional Inspection

> Internal technical reference (not public-facing). Plugin version: **1.1.0**. Inspection date: 2026-09-10.
> All statements verified against code. This file is excluded from build archives (build.sh EXCLUDES).

---

## 1. Product Overview

Photo & Video Reviews for WooCommerce (PVR) is a review system for WooCommerce that turns the standard review section into visual social proof: reviews with photos and videos, client-side media compression, verified-purchase badges, helpfulness voting, store replies, reviewer avatars, custom rating criteria (Pro), review rewards with coupons (Pro), automated review-request emails (Pro), and an all-reviews storefront page with SEO templates (Pro).

- **Distribution:** free version (WordPress.org) + Pro add-on; single codebase, Pro plugs in via the `pro/` folder loaded by a `file_exists()` bootstrap check.
- **Data model:** reviews are native WordPress comments (`comment_type = 'review'`) plus comment meta — fully compatible with WooCommerce and standard comment import/export tooling.
- **Requirements:** WordPress 6.0+, WooCommerce 7.0+, PHP 7+.
- **Locales:** 7 languages — en_US (source), ru_RU, es_ES, de_DE, fr_FR, pl_PL, pt_BR + POT.

## 2. Key Differentiators

1. **Client-side video compression — zero server load.** Videos are compressed in the customer's browser (MediaRecorder + Canvas): 60–80% smaller uploads, no ffmpeg, no server-side CPU spikes. No competing WooCommerce review plugin does this.
2. **Native storage.** Reviews are ordinary WP comments — migrations, exports and existing WooCommerce reviews keep working; deactivation never deletes data.
3. **A real review admin.** Purpose-built screens instead of the bare comments list: status/product/author/has-photo/has-video filters, bulk actions, a review editor with media lightbox, per-file size labels, verified-buyer check, and a coupon block.
4. **Branded emails.** Every plugin email shares one template: store logo, accent gradient (WCAG-checked accent-on-text contrast), store info block, plugin-only From name/address override.
5. **Anti-spam without CAPTCHA friction:** honeypot, per-IP and per-user daily limits (reviews, media count, media weight), optional reCAPTCHA v3.
6. **Byte-level upload progress with cancel:** XHR `upload.onprogress`, total progress computed from prepared (compressed) payloads, per-file progress bars, Cancel button, per-file failure isolation.
7. **Granular permissions:** who can review / reply / vote — everyone, registered users, or verified buyers.
8. **SEO:** Schema.org `Review` + `AggregateRating` (WooCommerce / Yoast / Rank Math), all-reviews page with per-filter `<title>`/meta-description templates and manual SEO landing pages.
9. **PHP upload-limit wizard:** detects SAPI, writes `.htaccess` or `.user.ini` safely (backups, server response verification, automatic rollback on 5xx).
10. **Self-update with integrity checks:** RSA-signed packages, SHA-256 checksums, automatic pre-update backups, Re-install button, backup restore UI.
11. **Developer-friendly:** 20+ `pvrev_*` hooks, conditional asset loading, `pvr-` CSS namespace, `filemtime()` asset cache-busting.

## 3. Free Features

### 3.1 Review form (modal on the product page)

- Custom UI: hero block, rating, fields, drag & drop upload zone, uploading state, thank-you screen.
- Fields: avatar, 5-star rating, name + email (guests), **phone** and **city** (optional), **Pros / Cons**, review text, privacy-policy consent (configurable URL).
- Form states: normal → `.pvr-form--uploading` (spinner + global percent progress + Cancel) → `.pvr-form--thankyou` (animated checkmark, auto-close after 5s).
- Progress is byte-level (`xhr.upload.onprogress`), totals computed from prepared (compressed) payloads, per-item progress bars, failed files do not cancel the batch, Cancel aborts the current XHR.
- Protection: honeypot field, optional reCAPTCHA v3 token, character limits.

### 3.2 Photo & video uploads

- Photos: JPEG, PNG, WebP, GIF. Videos: MP4, WebM, MOV. MIME lists configurable.
- Client-side image compression: target resolution (up to 4K) + quality.
- **Client-side video compression (signature feature):**
  - MediaRecorder + Canvas; resolution presets, bitrate presets 2500/5000/8000/16000 kbps, FPS cap, "compress above (MB)" threshold (default 50 MB);
  - compression modal with live preview, pause/resume, auto-pause on tab hide, mute, cancel;
  - target bitrate derived from the source (floor 300 kbps); falls back to the original file if compression is unsupported or pointless;
  - automatic video thumbnail generation.
- Photo rotation before submit; automatic video previews.
- **Three-layer validation:** JS `validateFile()` (type, count, size limits, server `upload_max_filesize`) → JS `queueFile()` re-check → PHP (type, min/max size, daily IP limits, `file_size === 0` truncation check).
- **Watermarks:** image, 5 positions + center, width percent.
- Media stored with **relative paths** (portable across domains); `file_size` persisted and shown in admin.

### 3.3 Review list on the product page

- Replaces the WooCommerce `comments_template` on products.
- Rating summary: average + 5→1 distribution with bars.
- "Customer Reviews" header + "Write a review" button (permission-aware).
- **Reward banner (Pro, §4.2)** as a standalone block right below the header.
- "Customer Media" strip + "Show all media" → media grid modal.
- Review cards: avatar (or initial placeholder), Verified Purchase badge, city, stars, date, title line, Pros/Cons, criteria dots (Pro), store replies, helpful vote buttons.
- **Media modal:** desktop 2/3 media + 1/3 aside (cloned review block with voting); mobile ≤900px bottom slide; custom video player (play/pause/mute + custom fullscreen separate from native controls).
- Pagination: AJAX "Show more" and/or numbered pages (`?cpage`).
- Scroll-lock of all modals centrally synchronized (`syncBodyModalClass()`).

### 3.4 Store replies

- Threaded replies: store owner (badge) and customers; guests reply with name + email.
- Moderation queue (default status `hold`), configurable auto-approval.
- Reply notification email to the review author; anti-spam limits apply.
- Reply from the frontend (reply modal) or from the admin.

### 3.5 Verified Purchase badge

- Automatic detection via `wc_customer_bought_product` (WooCommerce orders).
- Shown on review cards, in the admin notification email; per-review re-check button in the editor.

### 3.6 Reviewer avatars

- Upload in the review form and in My Account → Avatar.
- Crop modal, target size (default 500px), size limit (default 5 MB).
- Avatar is bound to the user; every upload writes a **unique filename** (`avatar-{random}.jpg`) and URLs are versioned (`?v={filemtime}`) so browser caches never show a stale avatar.

### 3.7 Schema.org / SEO

- JSON-LD: `review` (latest 10 approved, with `Person`, `reviewRating`, `datePublished`) and `aggregateRating` injected into product markup.
- Filters WooCommerce native output plus Yoast (`wpseo_schema_product`) and Rank Math (`rank_math/schema/product`).

### 3.8 Email notifications (free)

| Email | Trigger | Recipient | Dedup |
|---|---|---|---|
| Admin: New review — status badges, product card, rating + Verified buyer, author card (email, city, phone), Pros/Cons, review quote, media grid, "Moderate review"/"View on site" buttons; subject includes rating | `comment_post` + media finalization, toggle `pvr_notify_admin_new_review` | `pvr_admin_email` | `pvr_notify_admin_sent` |
| Admin: New media on review (auto-approved reviews only) | `pvrev_review_media_uploaded` (priority 20) | `pvr_admin_email` | `pvr_notify_admin_media_sent` |
| Author: Confirmation — product card, live/pending badge, quoted own review, "View your review" | on approval (auto-approve AND manual `wp_set_comment_status`; listener accepts `'approve'` and `'1'` for bulk) | review author | `pvr_notify_author_sent` |
| Author: Reply notification (single builder for store/customer variants) | reply added, toggle `pvr_notify_author_on_reply` | review author | — |

- WooCommerce's own new-review email is disabled to avoid duplicates.
- Deduplication via comment meta — no duplicates on manual, single, or bulk approval (WP core fires the status hook only on an actual change).
- **Branding:** logo (Media Manager → theme custom logo fallback), store name/phone/email (→ WooCommerce defaults), From name/address applied only to plugin emails, accent gradient `pvr_email_accent_from/_to/_text` with live preview; accent-as-text auto-darkened to WCAG ≥ 3.0 contrast (`get_accent_on_light()`).

### 3.9 My Account pages

- **Avatar** — upload + crop.
- **My Reviews** — the customer's reviews with statuses, media, lightbox; paginated.

### 3.10 Admin

- Menu **PV Reviews**: Reviews (list + editor), Comments (replies), Settings; unread badges on menu items.
- **Reviews list:** AJAX table, 20/page; filters: status (all/approve/hold), product, author (name/email), product title, has photo/has video; search; bulk approve/hold/delete (cascade deletes media, meta, replies).
- **Review editor:** full fields, media thumbnails with file-size labels, media lightbox, import images from the WP media library, verified-buyer check, product/category/user search, status change (triggers author notification), media deletion, coupon block at the bottom (Pro hook).
- **Settings** — tabbed UI (§5), AJAX save, toasts.
- **Import / Export:** settings export/import (JSON), reset to defaults, "Sync Database Structure" (idempotent schema migration), Plugin Updates card (check, install, Re-install, changelog, backups create/download/restore).
- **System Info:** server environment + PHP Upload Limits wizard (§8.1).

### 3.11 Permissions & moderation

- `pvr_can_review`, `pvr_can_reply`, `pvr_can_vote`: `all` / `registered` / `buyers` (verified purchase, product-scoped for reply/vote).
- Auto-approve reviews: none / buyers / registered / everyone.
- Reply moderation default: `hold`.
- One review per product per user (toggle).

## 4. Pro Features

### 4.1 Custom rating criteria (PVREV_Criteria)

- Unlimited criteria (slug, scale 1–5, required flag, active/inactive), multilingual titles/descriptions (WPML / Polylang / locale prefix).
- Category mapping with upward inheritance through the category tree + global fallback.
- AJAX reordering; deletion blocked once ratings exist.
- Frontend: criteria replace the generic star rating in the form; overall rating = criteria average; per-criteria dots on review cards; criteria averages summary above the list (`pvrev_review_list_before_items`).
- Display toggle `pvr_criteria_show_list` (default `yes`).

### 4.2 Review rewards — coupons (PVREV_Coupons)

- On approval, a WooCommerce coupon `REVIEW-XXXXXXXX` is issued automatically (usage limit 1, restricted to the author's email, individual use).
- Separate rewards for text / photo / video reviews (percent or fixed discount), expiry days, minimum order amount.
- **Deferred issue:** if media is not yet attached at approval time, a WP-Cron single event (+10 min) issues the reward later; text-only reviews with minimum content length (`pvr_coupon_min_content_len`) are covered by the same deferred path.
- **Reward banner** (`.pvr-reward-banner`): standalone block below the "Customer Reviews" header via `pvrev_review_list_after_header`; icon + "Earn a coupon for your review" + admin text with `{text_amount}` `{photo_amount}` `{video_amount}` `{max_amount}` placeholders; hidden from visitors who cannot review (`comments_open()` + `PVREV_Hooks::check_permission('review')`).
- **Coupon email:** the admin text IS the HTML body; tags `{coupon_ticket}` (accent dashed ticket: code, discount, valid-until, min order + View Product button), `{product}` (photo + linked name), `{site_link}`; scalars `{customer_name}`, `{coupon_code}`, `{coupon_amount}`, `{expiry_days}`, `{site_name}`, `{shop_url}`; Send Test Email with demo code `PREVIEW-XXXXXXXX`; legacy default messages get backward-compatible enrichment.
- **Admin coupon block** (hook `pvrev_admin_review_edit_bottom`): issue / delete / resend email; reward-type override (Auto/Text/Photo/Video — stored in `pvr_coupon_type_override`, resolved before the media lookup); delete removes the WC coupon + meta + unschedules the deferred event + decrements statistics.
- Usage restrictions: exclude sale items, include/exclude products and categories (AJAX multi-select pickers).
- Statistics: issued / used counters (tracking via `woocommerce_coupon_used` for `REVIEW-` codes).
- Dedup: `pvr_coupon_sent`; deferred event unscheduled per-comment on issue.

### 4.3 Email reminders (PVREV_Email_Reminders)

- Queue table `pvr_email_queue`: order `completed` → reminder after N days (default 7), optional second reminder (default +14 days), up to 3 delivery retries.
- Cron: queue processing twice daily; weekly cleanup of entries older than 90 days.
- Two configurable letters (subject/heading/message): product cards, "Leave a Review" button, UTM campaign tags.
- Placeholders: `{site_name}`, `{customer_name}`, `{order_number}`, `{coupon_amount_text/photo/video}` (from the Coupons module), conditional block `{coupon_block}…{/coupon_block}` (hidden when rewards are off).
- Category exclusions; anti-spam limits apply.
- **Unsubscribe** via signed link `?pvr_unsubscribe=1&email=…&token=…` handled on `init`.
- Controls: manual "Send Reminders", "Send Test Email" (demo order), "Restore Default Texts" (deletes the 6 template options).
- Statistics sub-tab: queue state by status.

### 4.4 Grid & carousel widgets (PVREV_Widgets)

- `[pvr_reviews_grid]` — responsive card grid: count, product/category filter, media-only, ordering (date/rating/helpful), scope (current product/children/all), 5 breakpoint column configs, avatar/thumbnail sizes.
- `[pvr_reviews_carousel]` — carousel: autoplay + delay, inertia power/slides, per-breakpoint navigation and pagination (scrollbar/dots).
- Shared media/review modals; 1-hour query cache (`PVREV_Cache`).

### 4.5 Analytics (PVREV_Analytics)

- Dashboard with 7/30/90/all ranges: total reviews, average rating, rating distribution, pending, media stats (photos/videos, % reviews with media), verified, votes, reply rate, **top-10 products**, **products needing attention** (avg ≤ 3 with ≥ 3 reviews), daily trend, monthly breakdown, coupons issued/used.

### 4.6 All-reviews page (PVREV_Reviews_Page)

- `[pvr_all_reviews]` — storefront review hub.
- Filters: category (`product_cat`) and manufacturer (`product_brand`, native WooCommerce Brands); interdependent lists (only non-empty options shown); sorting; media-only filter.
- Media showcase strip between filters and the list ("Show all media" + `PVREV.refreshProductMediaGallery()` / `PVREV.appendProductMediaGallery()` exports).
- "Show more" + numbered pagination.
- **SEO:** H1, `<title>` (`pre_get_document_title`) and meta description templates with `%category%`, `%manufacturer%`, `%count%` per filter combination; **manual SEO landing pages** (table `pvr_reviews_page_seo`: h1, seo title, description, body, active flag); BreadcrumbList JSON-LD; page title/H1/description settings.

### 4.7 License & updates

- **Licensing model:** a single perpetual license (`l_expires = 'never'`) with updates and support included forever. There is NO separate updates subscription (`l_updates_expires` is legacy, always 0); package downloads are gated only by the active main license.
- Key activation, server-signed local key (RSA, legacy HMAC fallback), rolling revalidation deadline (≤14 days, from the license-server method) + grace period (`grace_period_days`, configurable per method on the license server), re-activation from the admin.
- **Grace counts from the first failed activation, not from the subscription end:** when a key's deadline passes, the client revalidates; on a definitive server rejection (`isDefinitiveRejection()`) the moment is recorded in `pvr_license_grace_started` and Pro keeps working until `grace_started + grace_period_days*86400`. After that the local key is cleared — Pro off, free features keep working. Network failures/backoff do not start the clock (offline fallback: `local_key_expires + grace`). `attestation_valid()` mirrors this (`grace_end = max(deadline, grace_started) + grace`). The counter resets on any successful key write.
- **License tab:** perpetual license → "License type: Lifetime" (the revalidation date is never shown); dated (future annual) → "Expires: <date>". A **Renew** row appears ONLY after a recorded failed renewal (`grace_started > 0`): orange "renew to keep Pro features" while in grace, red "renew to restore Pro features and updates" once Pro is off. Deep link: `https://pv-reviews.site/renew?license=<activation_key>`.
- **Free→Pro upgrade** from the admin: download bootstrap → validate key → download Pro → install, with backup + rollback.
- **Self-update:** server update check, RSA package signature + SHA-256 checksum, pre-update backups (max 5) in `uploads/pvr-backups` with restore/download, **Re-install** flow (`reinstall=1` — server returns the package even at equal versions; cache always carries the download URL, gated by `has_update`).
- **Server-side purchase dedup:** `provision.php create` matches an existing license by exact `license_key`, then by normalized domain (wildcard-aware, statuses 0/1) — a repeat purchase extends the existing license (status `renewed`, same key) instead of creating a duplicate; `renew` extends `l_expires` and reactivates expired licenses.

## 5. Settings Reference

Admin: **PV Reviews → Settings**. Tabs: General, Limits & Anti-spam, Security, Media (Image / Video / Thumbnail), Emails & Notifications, Import / Export, System Info + Pro: Criteria, Reviews Page, Coupon Rewards, Reminders (Pro), Widgets (Pro), Analytics (Pro), License. Pro tabs render lockd previews without an active license; saving is blocked.

### 5.1 General
| Option | Description | Default |
|---|---|---|
| `pvr_auto_approve_mode` | Auto-approve reviews: none/buyers/registered/all | `none` |
| `pvr_reply_status_default` | Reply moderation: approved/hold | `hold` |
| `pvr_one_review_per_user` | One review per product per user | `yes` |
| `pvr_can_review` | Who can review: all/registered/buyers | `all` |
| `pvr_can_reply` | Who can reply | `all` |
| `pvr_can_vote` | Who can vote | `all` |
| `pvr_show_pros_cons` | Pros & Cons fields | `yes` |
| `pvr_show_phone` | Phone field | `no` |
| `pvr_show_city` | City field | `no` |
| `pvr_default_admin_name` | Manager name shown in replies/editor | `Manager` |
| `pvr_privacy_policy_url` | Privacy policy URL | `''` |
| `pvr_reviews_per_page` | Reviews per page | `10` |
| `pvr_reviews_show_more` | AJAX "Show more" button | `yes` |
| `pvr_reviews_pagination` | Numbered pagination | `yes` |

### 5.2 Limits & Anti-spam (character limits)
`pvr_min_review_len`=10 / `pvr_max_review_len`=5000 · `pvr_min_name_len`=3 / `pvr_max_name_len`=64 · `pvr_min_reply_len`=10 / `pvr_max_reply_len`=2000 · `pvr_min_pros_cons_len`=0 / `pvr_max_pros_cons_len`=500

### 5.3 Security
| Option | Description | Default |
|---|---|---|
| `pvr_ip_limit_reviews_day` | Reviews per IP per day | `10` |
| `pvr_user_limit_reviews_day` | Reviews per user per day | `20` |
| `pvr_ip_limit_media_count_day` | Media files per IP per day | `12` |
| `pvr_ip_limit_media_weight_day` | Media weight (MB) per IP per day | `1500` |
| `pvr_honeypot_enabled` | Hidden honeypot field | `yes` |
| `pvr_captcha_enabled` | reCAPTCHA v3 | `no` |
| `pvr_recaptcha_site_key` / `pvr_recaptcha_secret_key` | reCAPTCHA keys | `''` |
| `pvr_recaptcha_threshold` | reCAPTCHA score threshold (0–1) | `0.5` |
| `pvr_allowed_photo_types` | Allowed photo MIME types | jpeg, png, webp, gif |
| `pvr_allowed_video_types` | Allowed video MIME types | mp4, webm, quicktime |

### 5.4 Media — Image
| Option | Description | Default |
|---|---|---|
| `pvr_image_target_resolution` | Target photo resolution (up to 4K) | `1920x1080` |
| `pvr_image_compress_quality` | Compression quality (40–100) | `85` |
| `pvr_max_photos` | Max photos per review | `5` |
| `pvr_min_photo_size_mb` / `pvr_max_photo_size_mb` | Photo size (MB) | `0.01` / `8` |
| `pvr_watermark_image_url` / `pvr_watermark_position` / `pvr_watermark_width_percent` | Watermark file / position / width % | `''` / off / `20` |
| `pvr_max_avatar_size_mb` | Max avatar size | `5` |
| `pvr_avatar_target_size_px` | Avatar target size (px) | `500` |

### 5.5 Media — Video
| Option | Description | Default |
|---|---|---|
| `pvr_video_target_resolution` | Compression target resolution | `1280x720` |
| `pvr_video_bitrate_kbps` | Target bitrate | `5000` |
| `pvr_video_max_fps` | FPS cap | `30` |
| `pvr_video_compress_above_mb` | Compress only above (MB) | `50` |
| `pvr_max_videos` | Max videos per review | `3` |
| `pvr_min_video_size_mb` / `pvr_max_video_size_mb` | Video size (MB) | `0.1` / `500` |
| `pvr_min_video_duration_sec` / `pvr_max_video_duration_sec` | Duration (sec) | `3` / `120` |

### 5.6 Media — Thumbnails
`pvr_thumb_max_width/height`=100×100 (grid) · `pvr_large_thumb_max_width/height`=400×400 (modal preview) · `pvr_popup_max_width/height`=1920×1080 (lightbox) · `pvr_thumb_compress_quality`=100 · `pvr_large_thumb_compress_quality`=100 · `pvr_popup_compress_quality`=90 + "Clear Image Cache" button.

### 5.7 Emails & Notifications
| Option | Description | Default |
|---|---|---|
| `pvr_email_logo` | Email logo (Media Manager) | `''` |
| `pvr_email_accent_from` / `pvr_email_accent_to` / `pvr_email_accent_text` | Accent gradient + text color on accent surfaces | `#667eea` / `#764ba2` / `#ffffff` |
| `pvr_email_store_name` / `pvr_email_store_phone` / `pvr_email_store_email` | Store block in emails | → WP/Woo defaults |
| `pvr_admin_email` | Admin notification recipient | `admin_email` |
| `pvr_email_from_name` / `pvr_email_from_address` | From header (plugin emails only) | blog name / admin_email |
| `pvr_email_subject_confirmation` | Confirmation email subject | built-in default |
| `pvr_notify_admin_new_review` | Notify admin on new review | `yes` |
| `pvr_notify_author_received` | Author confirmation email | `yes` |
| `pvr_notify_author_on_reply` | Reply notification email | `yes` |

### 5.8 Import / Export
Settings export/import (JSON), reset to defaults, "Sync Database Structure" (idempotent table creation/migrations), Plugin Updates card: update check, install, Re-install, changelog, backups (create/download/restore).

### 5.9 System Info
Server environment (PHP/WP/WC/MySQL/SAPI) + PHP Upload Limits wizard: recommended `upload_max_filesize=500M`, `post_max_size=550M`, `memory_limit=600M`, `max_execution_time=300`, `max_input_time=300`; SAPI routing (Apache/LiteSpeed → `.htaccess`; FPM/CGI → `.user.ini` with deferred ~5-min verification via WP-Cron); config backups (max 3), server response verification after write, **automatic rollback on 5xx**; pending/applied/failed status; manual recipes for Nginx/shared hosting.

### 5.10 Coupon Rewards (Pro) — nested sub-tabs
- **General:** enable (`pvr_coupon_reward_enabled`), discount type percent/fixed_cart, amounts text/photo/video (5/10/15), expiry 30 days, minimum order amount, minimum review length for text rewards (50 chars).
- **Usage Restrictions:** exclude sale items; include/exclude products and categories (AJAX multi-select pickers).
- **Email Template:** subject + HTML body with `{coupon_ticket}`, `{product}`, `{site_link}` tags and scalar placeholders; Send Test Email (`PREVIEW-XXXXXXXX`).
- **Frontend Display:** reward banner above the review list + banner text with amount placeholders.

### 5.11 Reminders (Pro) — nested sub-tabs
- **General:** enable, delay 1–90 days (7), second reminder (14 days), category exclusions.
- **Email:** subject/heading/message for letters #1 and #2, "Restore Default Texts".
- **Statistics:** queue state; Send Reminders and Send Test Email buttons.

### 5.12 Widgets (Pro) — Grid/Carousel sub-tabs
Full option sets `pvr_grid_*` (23) and `pvr_carousel_*` (30+): sources, ordering, per-breakpoint column/slide configs, navigation/pagination, inertia, preview sizes.

### 5.13 Reviews Page (Pro)
`pvrev_reviews_page_title/h1/description/per_page(12)/pagination/show_more` + SEO templates for three filter combinations (category / manufacturer / both) using `%category%`, `%manufacturer%`, `%count%` + manual landing pages.

## 6. Shortcodes

| Shortcode | Renders |
|---|---|
| `[pvr_all_reviews]` | All-reviews hub: category/manufacturer filters, sorting, media-only filter, SEO templates, landing pages, media showcase |
| `[pvr_reviews_grid]` | Responsive review grid (breakpoint columns, product/category source, media-only, ordering) |
| `[pvr_reviews_carousel]` | Review carousel (autoplay, inertia, per-breakpoint navigation/pagination) |

## 7. Developer Hooks (main)

- **Actions:** `pvrev_review_form_fields`, `pvrev_review_form_before_fields`, `pvrev_after_review_saved`, `pvrev_after_media_uploaded`, `pvrev_review_approved` (fired on auto-approve AND manual/bulk approval), `pvrev_review_media_uploaded` (fired by `finish_uploads` once all media is attached), `pvrev_review_list_before_items` (criteria summary), `pvrev_review_list_after_header` (reward banner), `pvrev_review_item_data`, `pvrev_admin_review_edit_bottom`, `pvrev_init_hooks`, `pvrev_after_modules_loaded`, `pvrev_settings_tabs`, `pvrev_settings_tab_content_{key}`, `pvrev_save_settings_{key}`, `pvrev_coupon_deferred_reward`, `pvrev_verify_upload_limits`.
- **Filters:** `pvrev_frontend_data` (JS config), `pvrev_installer_defaults` (free defaults), `pvrev_review_normalize_rating`, `pvrev_review_form_use_criteria`.

## 8. Technical Details

### 8.1 Upload Limits (`PVREV_Upload_Limits`)
SAPI detection (`apache2handler`/`litespeed` → `.htaccess` block with `php_value` directives, no `<IfModule>` wrapper; `fpm-fcgi`/`cgi-fcgi` → `.user.ini` with deferred verification); `.user.ini` verification deferred via WP-Cron single event `pvrev_verify_upload_limits` (+360s); recommended `upload_max_filesize=500M`, `post_max_size=550M`, `memory_limit=600M`, `max_execution_time=300`, `max_input_time=300` (derived from `pvr_max_video_size_mb`); config backups (max 3, `*.pvr-backup-YYYY-MM-DD-His`), WP_Filesystem writes, post-write server response verification, automatic rollback on 5xx, operation log (`pvr_limits_log`, 50 entries); UI status pending/applied/failed.

### 8.2 Self-update
- **`PVREV_Updater`:** server `https://admin.pv-reviews.site/api.php` (`pvr_check_update`), RSA signature + SHA-256 checksum, options `pvr_update_info` / `pvr_last_update_check`, check throttle transient, backups in `uploads/pvr-backups` (max 5) with create/list/restore/download AJAX, **Re-install** (`check_for_update(true)` sends `reinstall=1`; cache always contains the download URL; `has_pending_update()` gated on `has_update`), cron check piggybacked on `pvrev_license_check` + admin notice banner. Free distribution does not self-update (wp.org policy).
- **`PVREV_Pro_Updater`:** Free→Pro upgrade: `pvr_download_protect` (bootstrap ~35KB) → key validation → `download_pro` (ZIP in `uploads/pvr-upgrade/`) → `install_pro` (unpack + backup/rollback).

### 8.3 WP-Cron
`pvrev_cleanup_orphans` (daily) · `pvrev_license_check` (daily, only with a local key) · `pvrev_verify_upload_limits` (single, +360s) · `pvrev_cron_send_reminders` (twicedaily, Pro) · `pvrev_cron_cleanup_reminder_queue` (weekly, Pro) · `pvrev_coupon_deferred_reward` (single +10 min, Pro, per-comment). Deactivation clears only the cron hooks.

### 8.4 Database
Native WP comments + prefixed tables: `pvr_review_meta` (verified, helpful counts, source, avatar_url), `pvr_media` (type, relative paths, file_size, compressed, sort_order), `pvr_votes` (unique per user/IP hash), `pvr_replies` (threads, statuses), `pvr_email_queue` (reminders with retry), `pvr_reviews_page_seo` (landing pages). Migrations are additive; deactivation never deletes data. Pro criteria data lives in `pvr_criteria`, `pvr_criteria_meta`, `pvr_criteria_category`, `pvr_criteria_ratings` (see §10.1 — not created by the current installer).

### 8.5 AJAX endpoints (all nonced)
- Frontend: `pvrev_submit_review`, `pvrev_upload_media`, `pvrev_upload_avatar`, `pvrev_finish_uploads`, `pvrev_vote`, `pvrev_load_more_product_reviews`, `pvrev_add_reply` (all with nopriv variants).
- Admin: `pvrev_admin_get_reviews`, `pvrev_admin_get_review`, `pvrev_admin_save_review`, `pvrev_admin_update_status`, `pvrev_admin_delete_review`, `pvrev_admin_bulk_action`, `pvrev_admin_delete_media`, `pvrev_admin_check_verified_buyer`, `pvrev_admin_import_wp_media`, `pvrev_admin_search_products`, `pvrev_admin_search_categories`, `pvrev_admin_search_users`, replies CRUD (`pvrev_admin_get_replies/get_reply/save_reply/delete_reply`) — nonce `pvrev_admin`, capability `manage_woocommerce`.
- Settings/system: `pvrev_get_settings_tab`, `pvrev_save_settings_tab`, `pvrev_clear_image_cache`, `pvrev_apply_system_limits`, `pvrev_check_limits_status`, `pvrev_admin_check_update`, `pvrev_admin_install_update` (supports `force=1` Re-install), `pvrev_admin_create_backup`, `pvrev_admin_list_backups`, `pvrev_admin_restore_backup`, `pvrev_admin_download_backup`.
- Pro: coupons (`pvrev_admin_coupon_get/issue/delete/resend` nonce `pvrev_admin`, `pvr_coupon_test_email`), reminders (`pvr_manual_send_reminders`, `pvr_test_reminder`, `pvr_reminders_reset_templates`), reviews page (`pvrev_load_more_reviews`, `pvrev_filter_reviews`, `pvrev_landing_list`, `pvrev_landing_toggle`), criteria (`pvrev_save_criteria_order`), license (`pvrev_admin_check_license`, `pvrev_admin_upgrade_pro`, `pvrev_admin_upgrade_step`, `pvrev_admin_reactivate_license`).
- Reminder unsubscribe is NOT admin-ajax: `?pvr_unsubscribe=1&email=…&token=…` handled on `init`.

### 8.6 Security
Nonce on every AJAX/admin request, `manage_woocommerce` capability checks, input sanitization + output escaping per WP standards, `$wpdb->prepare` for dynamic SQL, permission checks per action (who can review/reply/vote), three-layer upload validation, honeypot + reCAPTCHA v3.

### 8.7 Performance
- Media compression runs entirely on the client; the server only receives prepared files.
- Conditional asset loading only on surfaces that use the plugin.
- CSS/JS cache-busting via `filemtime()` (fresh assets after updates).
- Widget query cache (1h), analytics cache, generated image-size cache (thumbnail/modal/lightbox).
- Indexed custom tables, direct `$wpdb` queries without ORM.

### 8.8 i18n
7 languages: en_US (source), ru_RU, es_ES, de_DE, fr_FR, pl_PL, pt_BR; POT file; runtime JSON fallback (`PVREV_I18n`) for en/ru; JS strings via `wp_localize_script` (`PVREV`, `PVREV_Admin`, `PVREV.i18n`).

## 9. Free vs Pro Comparison

| Capability | Free | Pro |
|---|---|---|
| Photo/video reviews, client-side compression | ✅ | ✅ |
| Verified purchase, helpful voting, replies, avatars | ✅ | ✅ |
| Media modal + custom video player, lightbox | ✅ | ✅ |
| Schema.org (Woo/Yoast/Rank Math) | ✅ | ✅ |
| Email notifications (4 letters, branding, accents) | ✅ | ✅ |
| Review admin (filters, bulk actions, editor) | ✅ | ✅ |
| My Account: Avatar + My Reviews | ✅ | ✅ |
| Anti-spam (honeypot, limits, reCAPTCHA v3) | ✅ | ✅ |
| Watermark, Import/Export, PHP limits wizard, self-update | ✅ | ✅ |
| Custom rating criteria (multilingual, per-category) | — | ✅ |
| Review rewards — coupons (banner, ticket email, admin block) | — | ✅ |
| Email reminders (2 letters, queue, unsubscribe) | — | ✅ |
| Grid & carousel widgets (shortcodes) | — | ✅ |
| Analytics dashboard | — | ✅ |
| All-reviews page with SEO landing pages | — | ✅ |

## 10. Internal Notes (NOT for public articles)

1. **Criteria tables are not created by the installer.** `pvr_criteria`, `pvr_criteria_meta`, `pvr_criteria_category`, `pvr_criteria_ratings` are registered in `$wpdb` but their `dbDelta` block was removed from `PVREV_Installer::create_tables()` — fresh installs will hit missing tables in the Criteria (Pro) module. AGENTS.md claims otherwise; the installer needs the block restored in both `create_tables()` and `sync_database_structure()`.
2. `pvrev_installer_defaults` is not subscribed to by any Pro module; Pro defaults live only as `get_option()` fallbacks. Coupon options are marked `programmatic` in the settings schema and are NOT exported by Import/Export; Reminders/Widgets/Reviews Page options are absent from the export schema entirely.
3. Social Proof popup (`render_social_proof()`, `pvr_sp_enabled`, `pro/templates/review-social-proof.php`) is dead code — not wired to any hook.
4. Legacy options without UI: `pvr_auto_approve_all`, `pvr_auto_approve_registered`, `pvr_reminders_email_logo`, `pvr_reminders_email_store_name/phone/email` (kept as branding fallback chain).
5. Legacy option `pvr_max_video_size_no_compress_mb` is migrated to `pvr_video_compress_above_mb` at read time.
