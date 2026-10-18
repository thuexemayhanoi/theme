/** Minimal, Yoast-shaped HTML pages for the SEO tooling tests (synthetic, not production). */
export const graph = (extra = []) =>
	JSON.stringify({
		'@context': 'https://schema.org',
		'@graph': [
			{ '@type': 'WebPage', '@id': 'https://example.test/x/#webpage', url: 'https://example.test/x/' },
			{ '@type': ['Organization', 'LocalBusiness'], '@id': 'https://example.test/#organization', name: 'Cho Thuê Xe Máy Nguyễn Tú', telephone: '+84942467674', address: { '@type': 'PostalAddress', streetAddress: '112 Nguyễn Văn Cừ' } },
			...extra,
		],
	});

export function page({
	title = 'Cho thuê xe máy Hà Nội | Nguyễn Tú',
	description = 'Thuê xe máy tại Long Biên.',
	canonical = 'https://example.test/x/',
	robots = 'index, follow',
	h1 = ['Cho Thuê Xe Máy Hà Nội'],
	body = '',
	ld = graph(),
	lang = 'vi',
	main = 1,
	extraHead = '',
} = {}) {
	const mains = Array.from({ length: main }, () => `<main id="main">${h1.map((h) => `<h1>${h}</h1>`).join('')}<h2>Giá</h2><p>${body}</p><img src="a.jpg" alt="xe" width="10" height="10"></main>`).join('');
	return `<!doctype html><html lang="${lang}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>${title}</title>
${description === null ? '' : `<meta name="description" content="${description}">`}
${robots === null ? '' : `<meta name="robots" content="${robots}">`}
${canonical === null ? '' : [].concat(canonical).map((c) => `<link rel="canonical" href="${c}">`).join('')}
${ld ? `<script type="application/ld+json" class="yoast-schema-graph">${ld}</script>` : ''}${extraHead}</head>
<body><a class="skip-link" href="#main">Bỏ qua</a><header><nav aria-label="Menu chính"><a href="/blog/">Blog</a></nav></header>
<nav aria-label="Breadcrumb"><ol><li><a href="/">Trang chủ</a></li></ol></nav>${mains}
<footer><address>112 Nguyễn Văn Cừ, Bồ Đề, Long Biên, Hà Nội</address><a href="tel:0942467674">0942 467 674</a></footer></body></html>`;
}
