import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const editorial = readFileSync('template-parts/home/editorial.php', 'utf8');
const cardImage = readFileSync('inc/template-tags.php', 'utf8');
const cardsCss = readFileSync('assets/css/main.css', 'utf8');
const homeCss = readFileSync('assets/css/home-editorial.css', 'utf8');

// These ids were verified read-only against the live WP post/thumbnail association
// on 23 October 2026. They are not image filenames or made-up article URLs.
const expected = [4895, 4893, 4890, 4894, 4827, 4898, 4892, 4788, 4786, 4787, 4897, 4887, 4896, 4891];

test('home keeps 14 distinct, curated published post IDs rather than always swapping in newest posts', () => {
	const definition = editorial.match(/\$nt_curated\s*=\s*array\(([\s\S]*?)\n\);/);
	assert.ok(definition, 'curated mapping exists');
	const groups = Object.fromEntries([...definition[1].matchAll(/'([^']+)'\s*=>\s*array\(\s*([\d,\s]+)\)/g)]
		.map(([, name, ids]) => [name, ids.split(',').map(x => Number(x.trim())).filter(Boolean)]));
	assert.deepEqual(Object.keys(groups).sort(), ['featured', 'chia-se', 'danh-gia-so-sanh-xe', 'more'].sort());
	assert.deepEqual(groups.featured.length, 4);
	assert.deepEqual(groups['chia-se'].length, 3);
	assert.deepEqual(groups['danh-gia-so-sanh-xe'].length, 3);
	assert.deepEqual(groups.more.length, 4);
	const actual = Object.values(groups).flat();
	assert.deepEqual(actual, expected);
	assert.equal(new Set(actual).size, 14, 'no repeat images/posts in curated cards');
	assert.match(editorial, /'orderby'\s*=>\s*'post__in'/);
	assert.match(editorial, /'post_status'\s*=>\s*'publish'/);
	assert.match(editorial, /if\s*\(\s*\$selected->have_posts\(\)\s*\)/, 'validated post fallback');
});

test('new posts remain visible as light text-only homepage links', () => {
	assert.match(editorial, /nt-home-mag__fresh-list/);
	assert.match(editorial, /Bài viết mới nhất/);
	assert.match(editorial, /'posts_per_page'\s*=>\s*5/);
	assert.match(editorial, /\$nt_shown\[\]\s*=\s*\(int\) get_the_ID\(\)/);
	assert.match(homeCss, /\.nt-home-mag__fresh-list\s*\{/);
});

test('missing featured photos use truthful no-request editorial tiles', () => {
	assert.match(cardImage, /nt-card__placeholder--editorial/);
	assert.match(cardImage, /nt_topic_terms\(\s*\$post_id\s*\)/);
	assert.match(cardImage, /esc_html\(\s*\$topic_label\s*\)/);
	assert.match(cardImage, /nt_icon\(\s*'scooter'/);
	assert.match(cardsCss, /\.nt-card__placeholder--editorial\s*\{/);
	assert.match(homeCss, /\.nt-home-mag__featured-grid\s*>\s*li:not\(:first-child\)\s*\.nt-card__placeholder-topic/);
});
