---
name: "Comic Easel Webcomic Manager"
description: "Build, import, and manage Comic Easel webcomics, multi-image strips, Twitter/X scrapers, chapters, and characters"
---
# Comic Easel Webcomic Manager

Teach AI agents how to manage, publish, and automate webcomics using Comic Easel and the EMCP tools on WordPress.

## Architecture & Data Model

Comic Easel uses the custom post type `comic` (or custom slug defined in settings) and organizes comics chronologically and by story arcs.

### 1. Multi-Image Strip Architecture
In Comic Easel:
- **Page 1**: Stored as the standard WordPress **Featured Image** (`post_thumbnail`).
- **Pages 2..N**: Stored as standard HTML `<img>` tags inside the postmeta key `comic-html-below` (and mirrored to `ceo_html_below_comic` for compatibility).
- **Responsive Markup Standard**:
  ```html
  <img src="https://example.com/wp-content/uploads/2026/09/page-2.jpg" alt="" width="2048" height="1447" class="alignnone size-full wp-image-15908" />
  ```
  Attributes required:
  - `src`: Full attachment URL.
  - `class`: Must include `wp-image-<attachment_id>` and `size-full alignnone`.
  - `width` and `height`: Exact pixel dimensions of the image.

### 2. Social Media & Twitter / X Ingestion Workflow
When importing comic strips from external feeds or archives:
1. **Idempotency Verification**: Always check first using `find-by-source`:
   ```json
   {
     "operation": "find-by-source",
     "arguments": { "source_tweet_id": "2090121136998601017" }
   }
   ```
   If `found: true`, skip or update instead of creating a duplicate.
2. **Media Sideloading**: Sideload external images into the local Media Library using `emcp-tools/sideload-image`:
   - Returns an `attachment_id` and local `url`.
3. **Publication Timestamp Matching**:
   - Always pass the original Twitter UTC timestamp (e.g. `2026-08-19T16:58:06.000Z` or `2026-08-19 16:58:06`).
   - The MCP engine automatically computes `post_date` in site local time and `post_date_gmt` in UTC with `edit_date = true`, locking the date so chronological storylines place the strip accurately.
4. **Artist & Chapter Mapping**:
   - Assign post author directly via `author` or `author_id` (e.g. User ID `147`).
   - Assign the chapter term (e.g. `["dyriuck_kaos-archive"]`).
5. **Draft Moderation**:
   - Default imported strips to `"status": "draft"` for editorial review.

### 3. Dispatcher Tools

#### `emcp-tools/comic-read`
- `get-comic`: Get comic post, all pages array, source metadata, and navigation.
- `list-comics`: Filter by chapter, character, search, or status.
- `find-by-source`: Look up comic by `source_tweet_id` or `source_url`.
- `get-navigation`: Retrieve first, previous, next, latest, and in-chapter comic IDs.
- `list-chapters`: List chapters hierarchy and post counts.
- `list-characters`: List character terms.
- `get-settings`: Inspect Comic Easel active post type and options.

#### `emcp-tools/comic-write`
- `create-comic`: Create new comic with title, content, date, status, `featured_media_id`, `additional_images`, chapters, author, and source tracking.
- `update-comic`: Update title, content, status, date, or append additional images (`append_images: true`).
- `delete-comic`: Trash or permanently delete comic (`confirm: true` required).
- `create-chapter`: Create story arc with optional `parent` and `menu_order`.
- `update-chapter`: Update chapter name, slug, parent, or sort order.
- `delete-chapter`: Delete chapter term (`confirm: true` required).
- `create-character`: Create character tag.
- `set-source`: Attach `source_tweet_id` and `source_url` to an existing comic.
