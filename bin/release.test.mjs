/**
 * Unit tests for release versioning and rollover logic.
 * Run: node --test bin/release.test.mjs
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { getNextVersion, isValidVersion, getCurrentVersion, updateVersionFiles } from './release.mjs';

test('getNextVersion: increments patch standardly', () => {
  assert.equal(getNextVersion('0.0.1'), '0.0.2');
  assert.equal(getNextVersion('0.0.5'), '0.0.6');
  assert.equal(getNextVersion('3.14.5'), '3.14.6');
  assert.equal(getNextVersion('1.2.3'), '1.2.4');
});

test('getNextVersion: rolls over 0.0.9 to 0.1.0', () => {
  assert.equal(getNextVersion('0.0.9'), '0.1.0');
});

test('getNextVersion: rolls over 3.14.9 to 3.15.0', () => {
  assert.equal(getNextVersion('3.14.9'), '3.15.0');
});

test('getNextVersion: rolls over multi-digit minor properly', () => {
  assert.equal(getNextVersion('0.1.9'), '0.2.0');
  assert.equal(getNextVersion('0.9.9'), '0.10.0');
  assert.equal(getNextVersion('1.9.9'), '1.10.0');
  assert.equal(getNextVersion('3.99.9'), '3.100.0');
});

test('getNextVersion: throws on invalid format', () => {
  assert.throws(() => getNextVersion('invalid'), /Invalid SemVer format/);
  assert.throws(() => getNextVersion('1.0'), /Invalid SemVer format/);
  assert.throws(() => getNextVersion('v1.0.0'), /Invalid SemVer format/);
});

test('isValidVersion: validates version strings', () => {
  assert.equal(isValidVersion('3.14.5'), true);
  assert.equal(isValidVersion('0.0.9'), true);
  assert.equal(isValidVersion('0.1.0'), true);
  assert.equal(isValidVersion('v3.14.5'), false);
  assert.equal(isValidVersion('3.14'), false);
  assert.equal(isValidVersion(''), false);
});

test('updateVersionFiles: correctly updates mock plugin and readme files', () => {
  const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'emcp-release-test-'));

  const mockPhp = `<?php
/**
 * Plugin Name: Test Plugin
 * Version:     3.14.5
 */
define( 'EMCP_TOOLS_VERSION', '3.14.5' );
`;
  const mockReadme = `=== Test Plugin ===
Stable tag: 3.14.5
`;

  fs.writeFileSync(path.join(tmpDir, 'emcp-tools.php'), mockPhp, 'utf8');
  fs.writeFileSync(path.join(tmpDir, 'readme.txt'), mockReadme, 'utf8');

  assert.equal(getCurrentVersion(tmpDir), '3.14.5');

  // Test dry run first
  updateVersionFiles(tmpDir, '3.14.6', true);
  assert.equal(fs.readFileSync(path.join(tmpDir, 'emcp-tools.php'), 'utf8'), mockPhp);

  // Test real update
  const { filesUpdated } = updateVersionFiles(tmpDir, '3.14.6', false);
  assert.equal(filesUpdated.length, 2);

  const updatedPhp = fs.readFileSync(path.join(tmpDir, 'emcp-tools.php'), 'utf8');
  assert.match(updatedPhp, /Version:\s*3\.14\.6/);
  assert.match(updatedPhp, /define\(\s*'EMCP_TOOLS_VERSION',\s*'3\.14\.6'\s*\);/);

  const updatedReadme = fs.readFileSync(path.join(tmpDir, 'readme.txt'), 'utf8');
  assert.match(updatedReadme, /Stable tag:\s*3\.14\.6/);

  // Clean up
  fs.rmSync(tmpDir, { recursive: true, force: true });
});
