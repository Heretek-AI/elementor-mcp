---
name: "Performance Analyzer & Optimizer"
description: "Diagnose WordPress performance bottlenecks, slow queries, autoloaded options, asset bloat, and image optimization"
---
# Performance Analyzer & Optimizer

This skill guides AI agents on auditing and optimizing WordPress site performance using EMCP tools.

## Key Tool: `emcp-tools/analyze-performance`

Call `emcp-tools/analyze-performance` to run a deep scan of the runtime environment:
- **Server & PHP Environment**: Checks PHP version, memory limits, opcode caching, and execution time.
- **Database Health**:
  - Checks total database size, largest tables, and unindexed meta queries.
  - Measures total autoloaded options size (alert if autoload exceeds 800 KB).
  - Flags transient accumulation and orphaned post revisions.
- **Active Plugins Profile**:
  - Highlights resource-heavy plugins.
  - Detects duplicate caching or optimization plugins.
- **Asset Diagnostics**:
  - Measures total enqueued scripts and stylesheets on the frontend.
  - Identifies render-blocking CSS/JS.
  - Detects Google Fonts overhead and external network dependencies.

## Remediation Playbook

1. **Autoloaded Options Cleanup**:
   - Query largest autoloaded options via `emcp-tools/query`:
     ```sql
     SELECT option_name, LENGTH(option_value) AS size_bytes FROM wp_options WHERE autoload = 'yes' ORDER BY size_bytes DESC LIMIT 20;
     ```
   - Identify obsolete plugin transients or orphaned cache arrays and delete or convert to `autoload = 'no'`.

2. **Database Pruning**:
   - Clean expired transients and prune post revisions beyond 5 revisions per post.
   - Clean orphaned `postmeta` rows where `post_id NOT IN (SELECT ID FROM wp_posts)`.

3. **Image Optimization**:
   - Verify uploaded images use modern formats (WebP/AVIF).
   - Ensure explicit `width` and `height` attributes on all `<img>` tags to avoid Cumulative Layout Shift (CLS).
   - Verify native lazy loading `loading="lazy"` is present on below-the-fold images.
