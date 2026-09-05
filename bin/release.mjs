#!/usr/bin/env node
/**
 * Release version management for Elementor MCP Tools.
 * Handles decimal version incrementing with rollover (+0.0.1 where 0.0.9 -> 0.1.0).
 *
 * Usage:
 *   node bin/release.mjs --current
 *   node bin/release.mjs --next
 *   node bin/release.mjs --bump
 *   node bin/release.mjs --bump --version=3.15.0
 *   node bin/release.mjs --bump --dry-run
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT_DIR = path.resolve(__dirname, '..');

/**
 * Computes the next version by incrementing patch by 1.
 * If patch reaches 10, it rolls over to 0 and increments minor by 1 (e.g. 0.0.9 -> 0.1.0).
 *
 * @param {string} current Current SemVer string (e.g. "3.14.5" or "0.0.9")
 * @returns {string} The incremented version string
 */
export function getNextVersion(current) {
  const match = String(current).trim().match(/^(\d+)\.(\d+)\.(\d+)$/);
  if (!match) {
    throw new Error(`Invalid SemVer format: "${current}". Expected major.minor.patch (e.g. 3.14.5)`);
  }
  let major = parseInt(match[1], 10);
  let minor = parseInt(match[2], 10);
  let patch = parseInt(match[3], 10);

  patch += 1;
  if (patch >= 10) {
    patch = 0;
    minor += 1;
  }

  return `${major}.${minor}.${patch}`;
}

/**
 * Validates a version string format.
 *
 * @param {string} version
 * @returns {boolean}
 */
export function isValidVersion(version) {
  return /^\d+\.\d+\.\d+$/.test(String(version).trim());
}

/**
 * Locates the canonical main plugin file.
 *
 * @param {string} rootDir
 * @returns {string} Path to the plugin file
 */
export function getPluginFile(rootDir = ROOT_DIR) {
  const candidates = [
    path.join(rootDir, 'heretek-control-core.php'),
    path.join(rootDir, 'emcp-tools.php')
  ];
  const found = candidates.find((f) => fs.existsSync(f));
  if (!found) {
    throw new Error(`Plugin file not found. Checked: ${candidates.join(', ')}`);
  }
  return found;
}

/**
 * Reads the current version from the main plugin file.
 *
 * @param {string} rootDir Root directory of the repository
 * @returns {string} Current version string
 */
export function getCurrentVersion(rootDir = ROOT_DIR) {
  const pluginFile = getPluginFile(rootDir);
  const content = fs.readFileSync(pluginFile, 'utf8');
  const match = content.match(/define\(\s*['"]EMCP_TOOLS_VERSION['"]\s*,\s*['"]([^'"]+)['"]\s*\);/);
  if (!match) {
    throw new Error(`EMCP_TOOLS_VERSION constant not found in ${path.basename(pluginFile)}`);
  }
  return match[1];
}

/**
 * Updates version references in the plugin file and readme.txt.
 *
 * @param {string} rootDir Root directory of the repository
 * @param {string} newVersion Target version string
 * @param {boolean} dryRun If true, does not write changes to disk
 * @returns {{ filesUpdated: string[] }}
 */
export function updateVersionFiles(rootDir = ROOT_DIR, newVersion, dryRun = false) {
  if (!isValidVersion(newVersion)) {
    throw new Error(`Invalid version format to set: "${newVersion}"`);
  }

  const filesUpdated = [];

  // 1. Update plugin bootstrap files
  const pluginFiles = [
    path.join(rootDir, 'heretek-control-core.php'),
    path.join(rootDir, 'emcp-tools.php')
  ].filter((f) => fs.existsSync(f));

  for (const pluginFile of pluginFiles) {
    let content = fs.readFileSync(pluginFile, 'utf8');
    let changed = false;

    // Update constant define( 'EMCP_TOOLS_VERSION', 'X.Y.Z' );
    if (content.includes('EMCP_TOOLS_VERSION')) {
      content = content.replace(
        /(define\(\s*['"]EMCP_TOOLS_VERSION['"]\s*,\s*['"])[^'"]+(['"]\s*\);)/,
        `$1${newVersion}$2`
      );
      changed = true;
    }

    // Update docblock header * Version: X.Y.Z
    if (/(\*\s*Version:\s*)([0-9A-Za-z.-]+)/.test(content)) {
      content = content.replace(
        /(\*\s*Version:\s*)([0-9A-Za-z.-]+)/,
        `$1${newVersion}`
      );
      changed = true;
    }

    if (changed) {
      if (!dryRun) {
        fs.writeFileSync(pluginFile, content, 'utf8');
      }
      filesUpdated.push(pluginFile);
    }
  }

  // 2. Update readme.txt
  const readmeFile = path.join(rootDir, 'readme.txt');
  if (fs.existsSync(readmeFile)) {
    let content = fs.readFileSync(readmeFile, 'utf8');

    // Update Stable tag: X.Y.Z
    content = content.replace(
      /(Stable tag:\s*)([0-9A-Za-z.-]+)/,
      `$1${newVersion}`
    );

    if (!dryRun) {
      fs.writeFileSync(readmeFile, content, 'utf8');
    }
    filesUpdated.push(readmeFile);
  }

  return { filesUpdated };
}

// CLI Execution Entrypoint
if (process.argv[1] && path.resolve(process.argv[1]) === path.resolve(__filename)) {
  const args = process.argv.slice(2);
  const isBump = args.includes('--bump');
  const isCurrent = args.includes('--current');
  const isNext = args.includes('--next');
  const isDryRun = args.includes('--dry-run');

  const customVerArg = args.find((a) => a.startsWith('--version='));
  const customVersion = customVerArg ? customVerArg.split('=')[1].trim() : null;

  try {
    const current = getCurrentVersion(ROOT_DIR);

    if (isCurrent) {
      console.log(current);
      process.exit(0);
    }

    const next = customVersion || getNextVersion(current);

    if (isNext) {
      console.log(next);
      process.exit(0);
    }

    if (isBump) {
      if (customVersion && !isValidVersion(customVersion)) {
        console.error(`Error: Custom version "${customVersion}" is not valid SemVer (X.Y.Z)`);
        process.exit(1);
      }

      console.log(`Current version: ${current}`);
      console.log(`Target version:  ${next}${isDryRun ? ' (DRY RUN)' : ''}`);

      const { filesUpdated } = updateVersionFiles(ROOT_DIR, next, isDryRun);
      for (const file of filesUpdated) {
        console.log(`Updated: ${path.relative(ROOT_DIR, file)}`);
      }
      console.log(`Successfully bumped to ${next}!`);
      process.exit(0);
    }

    console.log('Usage:');
    console.log('  node bin/release.mjs --current');
    console.log('  node bin/release.mjs --next');
    console.log('  node bin/release.mjs --bump');
    console.log('  node bin/release.mjs --bump --version=3.15.0');
    console.log('  node bin/release.mjs --bump --dry-run');
  } catch (err) {
    console.error(`Error: ${err.message}`);
    process.exit(1);
  }
}
