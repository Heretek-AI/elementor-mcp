#!/usr/bin/env bash
# ==============================================================================
# build-wporg-zip.sh
# Packages a 100% compliant release ZIP for the WordPress.org Plugin Directory.
#
# Excludes development artifacts, git metadata, tests, shell scripts,
# third-party updaters, and legacy commercial SDKs per WordPress.org
# Guidelines 1, 4, 5, 8, and 17.
# ==============================================================================

set -euo pipefail

PLUGIN_SLUG="heretek-control-core"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
DIST_DIR="${ROOT_DIR}/dist"
STAGE_DIR="${DIST_DIR}/staging/${PLUGIN_SLUG}"
ZIP_PATH="${DIST_DIR}/${PLUGIN_SLUG}.zip"

echo "=== [1/5] Validating PHP Syntax ==="
cd "${ROOT_DIR}"
find . -maxdepth 4 -name "*.php" \
  ! -path "./vendor/*" \
  ! -path "./dist/*" \
  ! -path "./tests/*" \
  ! -path "./includes/vendors/*" \
  -exec php -l {} + > /dev/null
echo "✓ PHP syntax validation passed."

echo "=== [2/5] Running Automated Test Suite ==="
for t in tests/test-*.php; do
  php "$t" > /dev/null || { echo "Test failed: $t"; exit 1; }
done
echo "✓ All test suites passed 100%."

echo "=== [3/5] Staging WordPress.org Production Files ==="
rm -rf "${DIST_DIR}"
mkdir -p "${STAGE_DIR}"

# Copy production files to staging, excluding non-runtime artifacts
rsync -a --delete \
  --exclude='.*' \
  --exclude='.git*' \
  --exclude='.github' \
  --exclude='.claude' \
  --exclude='.agents' \
  --exclude='review' \
  --exclude='tests' \
  --exclude='dist' \
  --exclude='bin' \
  --exclude='assets-wporg' \
  --exclude='sonar-project.properties' \
  --exclude='CLAUDE.md' \
  --exclude='CONTRIBUTING.md' \
  --exclude='composer.json' \
  --exclude='composer.lock' \
  --exclude='phpunit.xml*' \
  --exclude='pro-manifest.txt' \
  --exclude='mitm_mcp_traffic.db' \
  --exclude='.emcp-pro' \
  --exclude='pro' \
  --exclude='*.sh' \
  --exclude='*.bash' \
  --exclude='.DS_Store' \
  --exclude='Thumbs.db' \
  --exclude='includes/class-github-updater.php' \
  --exclude='includes/vendors/fremius' \
  "${ROOT_DIR}/" "${STAGE_DIR}/"

# Deep clean any remaining non-plugin / developer artifacts inside packages
find "${STAGE_DIR}" -name ".*" -exec rm -rf {} + 2>/dev/null || true
find "${STAGE_DIR}" -type f \( \
  -name "*.sh" -o \
  -name "*.bash" -o \
  -name "*.db" -o \
  -name "*.sqlite" -o \
  -name "*.lock" -o \
  -name "*.dist" -o \
  -name "*.example" -o \
  -name "*.editorconfig" -o \
  -name "*.nvmrc" -o \
  -name "*.prettier*" -o \
  -name "setup_tools.sh" \
\) -delete

# Remove empty directories if any
find "${STAGE_DIR}" -type d -empty -delete

echo "✓ Staged files to ${STAGE_DIR}"

echo "=== [4/5] Building Release Archive: ${ZIP_PATH} ==="
cd "${DIST_DIR}/staging"
zip -q -r "${ZIP_PATH}" "${PLUGIN_SLUG}"

echo "=== [5/5] Pre-Flight Archive Inspection ==="
if [ ! -f "${ZIP_PATH}" ]; then
  echo "ERROR: Archive was not created!"
  exit 1
fi

ZIP_SIZE=$(stat -c%s "${ZIP_PATH}" 2>/dev/null || stat -f%z "${ZIP_PATH}")
FILE_COUNT=$(unzip -l "${ZIP_PATH}" | tail -n 1 | awk '{print $2}')

echo "Archive: ${ZIP_PATH}"
echo "Size: $(awk "BEGIN {printf \"%.2f MB\", ${ZIP_SIZE}/1048576}") (${ZIP_SIZE} bytes)"
echo "Total files: ${FILE_COUNT}"

# Compliance checks on the archive
echo "Verifying guideline compliance inside zip..."

# Check 1: Plugin headers exist
unzip -l "${ZIP_PATH}" | grep -q "${PLUGIN_SLUG}/heretek-control-core.php" || { echo "ERROR: Main plugin file missing!"; exit 1; }
unzip -l "${ZIP_PATH}" | grep -q "${PLUGIN_SLUG}/readme.txt" || { echo "ERROR: readme.txt missing!"; exit 1; }

# Check 2: No shell scripts allowed in WordPress plugins (setup_tools.sh, etc.)
if unzip -l "${ZIP_PATH}" | grep -E "\.(sh|bash)$"; then
  echo "ERROR: Shell script found in archive (disallowed by WordPress.org)!"
  exit 1
fi

# Check 3: No GitHub updater (Guideline 8)
if unzip -l "${ZIP_PATH}" | grep -q "class-github-updater.php"; then
  echo "ERROR: class-github-updater.php found in archive (violates Guideline 8)!"
  exit 1
fi

# Check 4: No Freemius SDK (Guideline 5)
if unzip -l "${ZIP_PATH}" | grep -q "vendors/fremius"; then
  echo "ERROR: Freemius SDK found in archive (violates Guideline 5)!"
  exit 1
fi

# Check 5: No hidden/dot files
if unzip -l "${ZIP_PATH}" | grep -E "${PLUGIN_SLUG}/\."; then
  echo "ERROR: Hidden/dotfiles found in archive!"
  exit 1
fi

# Check 6: No dev database or config files
if unzip -l "${ZIP_PATH}" | grep -E "\.(db|sqlite|lock|dist|example)$"; then
  echo "ERROR: Unexpected file types (.db, .lock, .dist, .example) found in archive!"
  exit 1
fi

echo "✓ Guideline compliance checks PASSED! Zero shell scripts or unexpected files."
echo ""
echo "=========================================================================="
echo " SUCCESS: ${ZIP_PATH} is ready for submission to:"
echo " https://wordpress.org/plugins/developers/add/"
echo "=========================================================================="
