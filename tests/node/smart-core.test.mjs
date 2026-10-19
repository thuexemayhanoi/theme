import { readFileSync, existsSync } from 'node:fs';
import { test } from 'node:test';
import assert from 'node:assert/strict';

const root = new URL('../../plugins/nguyentu-smart-core/', import.meta.url);
const read = (path) => readFileSync(new URL(path, root), 'utf8');

test('Smart Core has a standalone WordPress bootstrap and all expected modules', () => {
  const main = read('nguyentu-smart-core.php');
  assert.match(main, /Plugin Name: Nguyễn Tú Smart Core/);
  assert.match(main, /Version: 1\.0\.0/);
  for (const path of ['includes/business.php', 'includes/schema.php', 'includes/admin.php', 'includes/audit.php', 'includes/rental.php', 'assets/rental.js', 'assets/rental.css', 'readme.txt', 'uninstall.php']) {
    assert.equal(existsSync(new URL(path, root)), true, path);
  }
});

test('no schema output may compete with existing Yoast or current theme', () => {
  const schema = read('includes/schema.php');
  assert.match(schema, /function_exists\( 'nt_yoast_enrich_organization' \)/);
  assert.match(schema, /'' !== self::seo_owner\(\)/);
  assert.match(schema, /wpseo_schema_organization/);
  assert.doesNotMatch(schema, /add_filter\( 'wpseo_canonical'/);
  assert.doesNotMatch(schema, /add_filter\( 'wp_robots'/);
  assert.doesNotMatch(schema, /aggregateRating/);
});

test('business facts are verified, scoped to the correct host and read only', () => {
  const business = read('includes/business.php');
  assert.match(business, /thuexemaynguyentu\.com/);
  assert.match(business, /112 Nguyễn Văn Cừ/);
  assert.match(business, /0942 467 674/);
  assert.match(business, /function_exists\( 'nt_business' \)/);
  assert.doesNotMatch(business, /update_option\s*\(/);
  assert.doesNotMatch(business, /wp_insert_post\s*\(/);
});

test('plugin shipping is separate from the active theme and does not touch Quick Call', () => {
  const attrs = readFileSync(new URL('../../.gitattributes', import.meta.url), 'utf8');
  assert.match(attrs, /^\/plugins export-ignore$/m);
  const main = read('nguyentu-smart-core.php');
  assert.doesNotMatch(main, /add_action\( 'wp_footer'/);
});

test('Smart Core has a bounded, non-destructive SEO audit and a calculator', () => {
  const audit = read('includes/audit.php');
  const rental = read('includes/rental.php');
  assert.match(audit, /posts_per_page'\s*=> 20/);
  assert.match(audit, /WP_HTML_Tag_Processor/);
  assert.doesNotMatch(audit, /wp_update_post|update_option|DELETE FROM/);
  assert.match(rental, /nguyentu_rental_calculator/);
  assert.match(rental, /nt_home_prices_after_table/);
  assert.doesNotMatch(rental, /wp_insert_post|wp_remote_post|wp_create_user/);
  const script = read('assets/rental.js');
  assert.doesNotMatch(script, /fetch\(|localStorage|XMLHttpRequest/);
});
