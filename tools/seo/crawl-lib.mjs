/**
 * Crawl helpers for tools/seo/internal-link-graph.mjs: sitemap parsing, robots.txt rules, retry/backoff,
 * redirect chains, request ordering and checkpoint files. GET requests only; there is no WordPress or
 * admin access. Network access is injected (fetch functions are parameters), so every helper is covered
 * offline by tests/node/internal-link-graph-crawl.test.mjs.
 */
import { mkdir, readFile, writeFile, rename, appendFile } from 'node:fs/promises';
import { join } from 'node:path';

const decodeXml = value => value
  .replace(/^<!\[CDATA\[([\s\S]*?)\]\]>$/, '$1')
  .replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&apos;/g, "'").replace(/&amp;/g, '&');

/**
 * Entries (loc + lastmod) of a sitemap index or URL set.
 *
 * @param {string} xml Sitemap XML.
 * @returns {{isIndex:boolean, entries:{loc:string,lastmod:string}[]}} Parsed entries.
 */
export function parseSitemapEntries(xml) {
  const isIndex = /<sitemapindex[\s>]/i.test(xml);
  const tag = isIndex ? 'sitemap' : 'url';
  const entries = [];
  for (const block of xml.matchAll(new RegExp(`<${tag}>([\\s\\S]*?)</${tag}>`, 'gi'))) {
    const loc = block[1].match(/<loc>\s*([\s\S]*?)\s*<\/loc>/i)?.[1];
    if (!loc) continue;
    entries.push({ loc: decodeXml(loc.trim()), lastmod: block[1].match(/<lastmod>\s*([^<]*?)\s*<\/lastmod>/i)?.[1] ?? '' });
  }
  return { isIndex, entries };
}

/** Taxonomy archives (valid to index, but only meaningful when they are indexable) and other archive kinds. */
export function kindOf(type) {
  if (/^(?:category|post_tag|product_cat|product_tag|product_brand|.+_category|.+_tag|.+_cat)$/i.test(type)) return 'taxonomy';
  if (/^(?:author|post_format)$/i.test(type)) return 'archive';
  return 'content';
}

/**
 * Content type of a Yoast/WordPress child sitemap URL, e.g. post-sitemap2.xml → post.
 *
 * @param {string} url Child sitemap URL.
 * @returns {{type:string, kind:string}} Type name and kind (content, taxonomy, archive).
 */
export function sitemapType(url) {
  const file = new URL(url).pathname.split('/').pop() || '';
  const match = file.match(/^(.*?)-sitemap\d*\.xml(?:\.gz)?$/i);
  const type = match ? match[1] : file.replace(/\.xml(?:\.gz)?$/i, '') || 'unknown';
  return { type, kind: kindOf(type) };
}

/**
 * Walk the sitemap tree (nested indexes included, depth and size bounded) and collect page URLs.
 *
 * @param {object} options Options.
 * @param {string} options.base Site origin.
 * @param {string[]} options.roots Candidate root sitemap URLs, tried in order until one loads.
 * @param {(url:string)=>Promise<{status:number,body:string}>} options.fetchSitemap Fetch function (retry applied by the caller).
 * @param {(url:string, base:string)=>string|null} options.toPath Same-origin path normalizer; null skips the URL.
 * @param {number} [options.maxDepth] Maximum index nesting.
 * @param {number} [options.maxSitemaps] Maximum sitemap files fetched.
 * @param {()=>Promise<void>} [options.pause] Polite delay between sitemap requests.
 * @returns {Promise<{ok:boolean, root:string, sitemaps:object[], urls:object[], failures:object[]}>} Discovery result.
 */
export async function discoverSitemaps({ base, roots, fetchSitemap, toPath, maxDepth = 3, maxSitemaps = 200, pause = async () => {} }) {
  const failures = [];
  const sitemaps = [];
  const found = new Map();
  const seen = new Set();
  let listed = 0;
  const sameOrigin = url => { try { return new URL(url).origin === new URL(base).origin; } catch { return false; } };
  let root = '';
  for (const candidate of roots) {
    if (seen.has(candidate)) continue;
    seen.add(candidate);
    let response;
    try { response = await fetchSitemap(candidate); } catch (error) { failures.push({ url: candidate, reason: String(error?.message ?? error) }); continue; }
    if (response.status !== 200 || !/<(?:sitemapindex|urlset)[\s>]/i.test(response.body)) {
      failures.push({ url: candidate, status: response.status, reason: response.status === 200 ? 'not a sitemap' : '' });
      continue;
    }
    root = candidate;
    const queue = [{ url: candidate, depth: 0, body: response.body }];
    while (queue.length && sitemaps.length < maxSitemaps) {
      const item = queue.shift();
      let body = item.body;
      if (body === undefined) {
        await pause();
        try {
          const res = await fetchSitemap(item.url);
          if (res.status !== 200) { failures.push({ url: item.url, status: res.status }); continue; }
          body = res.body;
        } catch (error) { failures.push({ url: item.url, reason: String(error?.message ?? error) }); continue; }
      }
      const { isIndex, entries } = parseSitemapEntries(body);
      if (isIndex) {
        if (item.depth >= maxDepth) { failures.push({ url: item.url, reason: 'sitemap index nesting too deep' }); continue; }
        for (const entry of entries) {
          if (!sameOrigin(entry.loc) || seen.has(entry.loc)) continue;
          seen.add(entry.loc);
          queue.push({ url: entry.loc, depth: item.depth + 1 });
        }
        continue;
      }
      const { type, kind } = sitemapType(item.url);
      let count = 0;
      for (const entry of entries) {
        if (!sameOrigin(entry.loc)) continue;
        const path = toPath(entry.loc, base);
        if (!path) continue;
        count += 1;
        listed += 1;
        if (!found.has(path)) found.set(path, { path, url: new URL(path, base).href, type, kind, sitemap: item.url, lastmod: entry.lastmod });
      }
      sitemaps.push({ url: item.url, type, kind, urls: count });
    }
    if (queue.length) failures.push({ url: root, reason: `stopped after ${maxSitemaps} sitemap files` });
    break;
  }
  return { ok: Boolean(root) && found.size > 0, root, sitemaps, urls: [...found.values()], duplicates: listed - found.size, failures };
}

/**
 * Parse robots.txt into user-agent groups.
 *
 * @param {string} body robots.txt content.
 * @returns {{agents:string[], rules:{allow:boolean, pattern:string}[]}[]} Groups.
 */
export function parseRobotsRules(body) {
  const groups = [];
  let current = null;
  let lastWasAgent = false;
  for (const raw of body.split(/\r?\n/)) {
    const line = raw.replace(/#.*$/, '').trim();
    const match = line.match(/^([a-z-]+)\s*:\s*(.*)$/i);
    if (!match) continue;
    const field = match[1].toLowerCase();
    const value = match[2].trim();
    if (field === 'user-agent') {
      if (!current || !lastWasAgent) { current = { agents: [], rules: [] }; groups.push(current); }
      current.agents.push(value.toLowerCase());
      lastWasAgent = true;
      continue;
    }
    lastWasAgent = false;
    if ((field === 'allow' || field === 'disallow') && current && value !== '') current.rules.push({ allow: field === 'allow', pattern: value });
  }
  return groups;
}

function robotsPatternMatches(pattern, path) {
  const anchored = pattern.endsWith('$');
  const source = (anchored ? pattern.slice(0, -1) : pattern).split('*').map(part => part.replace(/[.+?^${}()|[\]\\]/g, '\\$&')).join('.*');
  return new RegExp('^' + source + (anchored ? '$' : '')).test(path);
}

/**
 * Whether a crawler may fetch a path (RFC 9309: longest matching rule wins, Allow wins a tie).
 *
 * @param {ReturnType<typeof parseRobotsRules>} groups Parsed groups.
 * @param {string} path Path plus query.
 * @param {string} [agent] Crawler name; the wildcard group is used when no named group matches.
 * @returns {boolean} True when allowed.
 */
export function robotsAllows(groups, path, agent = '*') {
  const name = agent.toLowerCase();
  let applicable = groups.filter(g => g.agents.some(a => a !== '*' && name.includes(a)));
  if (!applicable.length) applicable = groups.filter(g => g.agents.includes('*'));
  let best = null;
  for (const rule of applicable.flatMap(g => g.rules)) {
    if (!robotsPatternMatches(rule.pattern, path)) continue;
    const length = rule.pattern.length;
    if (!best || length > best.length || (length === best.length && rule.allow && !best.allow)) best = { length, allow: rule.allow };
  }
  return best ? best.allow : true;
}

/** Statuses worth another attempt: the server may recover or is asking us to slow down. */
export const RETRYABLE_STATUS = new Set([408, 425, 429, 500, 502, 503, 504]);

/** Retry-After header → milliseconds (delta seconds or HTTP date). */
export function parseRetryAfter(value, now = Date.now()) {
  if (!value) return 0;
  const seconds = Number(value);
  if (Number.isFinite(seconds)) return Math.max(0, seconds * 1000);
  const date = Date.parse(value);
  return Number.isNaN(date) ? 0 : Math.max(0, date - now);
}

/**
 * Exponential backoff with up to 25% jitter. A server's Retry-After wins when it asks for longer.
 *
 * @param {number} retry Zero-based retry index (0 = first retry).
 * @param {{baseMs?:number, maxMs?:number, retryAfterMs?:number, random?:()=>number}} [options] Options.
 * @returns {number} Delay in milliseconds, never above 60 s.
 */
export function backoffDelay(retry, { baseMs = 1000, maxMs = 30000, retryAfterMs = 0, random = Math.random } = {}) {
  const exponential = Math.min(maxMs, baseMs * 2 ** retry);
  return Math.min(60000, Math.max(retryAfterMs, exponential + Math.floor(exponential * 0.25 * random())));
}

/**
 * Run a request with retry/backoff. A network failure or a retryable status is retried; any other answer
 * (200, 3xx, 404, 410…) is final. When every attempt fails the result says so (`exhausted`) so the caller
 * can leave the URL unchecked instead of recording a transient 503 as a finding.
 *
 * @param {(attempt:number)=>Promise<{status:number, retryAfterMs?:number}>} operation One request.
 * @param {object} [options] Options.
 * @param {number} [options.retries] Retries after the first attempt.
 * @param {number} [options.baseMs] Backoff base.
 * @param {number} [options.maxMs] Backoff cap.
 * @param {(ms:number)=>Promise<void>} [options.sleep] Sleep function (injected in tests).
 * @param {()=>number} [options.random] Jitter source.
 * @param {(info:object)=>void} [options.onRetry] Called before each wait.
 * @returns {Promise<object>} Last result plus `attempts` and `exhausted`; network failures give status 0 and `error`.
 */
export async function withRetry(operation, { retries = 3, baseMs = 1000, maxMs = 30000, sleep = ms => new Promise(r => setTimeout(r, ms)), random = Math.random, onRetry = () => {} } = {}) {
  let attempts = 0;
  for (;;) {
    attempts += 1;
    let result;
    try {
      result = await operation(attempts);
    } catch (error) {
      const message = String(error?.message ?? error);
      if (attempts > retries) return { status: 0, error: message, attempts, exhausted: true };
      const delayMs = backoffDelay(attempts - 1, { baseMs, maxMs, random });
      onRetry({ attempt: attempts, error: message, delayMs });
      await sleep(delayMs);
      continue;
    }
    if (!RETRYABLE_STATUS.has(result.status)) return { ...result, attempts, exhausted: false };
    if (attempts > retries) return { ...result, attempts, exhausted: true };
    const delayMs = backoffDelay(attempts - 1, { baseMs, maxMs, retryAfterMs: result.retryAfterMs ?? 0, random });
    onRetry({ attempt: attempts, status: result.status, delayMs });
    await sleep(delayMs);
  }
}

/**
 * Follow a redirect chain hop by hop, never leaving the site and never looping.
 *
 * @param {(url:string)=>Promise<{status:number, location?:string, error?:string, exhausted?:boolean}>} fetchHop Fetch one hop (no body needed).
 * @param {string} startUrl URL whose first hop already redirected.
 * @param {object} options Options.
 * @param {string} options.base Site origin.
 * @param {number} [options.maxHops] Maximum hops followed.
 * @param {Map<string,Promise<object>>} [options.cache] Hop cache shared across calls so a destination is requested once.
 * @param {string[]} [options.visited] URLs already passed (the redirecting source) so a loop back to it is detected.
 * @returns {Promise<{chain:object[], final:object, loop:boolean, offsite:boolean, tooMany:boolean}>} Chain description.
 */
export async function followRedirects(fetchHop, startUrl, { base, maxHops = 5, cache = new Map(), visited = [] }) {
  const origin = new URL(base).origin;
  const chain = [];
  const seen = new Set(visited);
  let url = startUrl;
  let result = { status: 0 };
  for (let hop = 0; hop <= maxHops; hop++) {
    if (seen.has(url)) return { chain, final: result, loop: true, offsite: false, tooMany: false };
    seen.add(url);
    if (!cache.has(url)) cache.set(url, fetchHop(url));
    result = await cache.get(url);
    chain.push({ url, status: result.status, location: result.location ?? '' });
    if (result.error || !(result.status >= 300 && result.status < 400 && result.location)) return { chain, final: result, loop: false, offsite: false, tooMany: false };
    const next = new URL(result.location, url);
    next.hash = '';
    if (next.origin !== origin) return { chain, final: result, loop: false, offsite: true, tooMany: false };
    url = next.href;
  }
  return { chain, final: result, loop: false, offsite: false, tooMany: true };
}

/**
 * Interleave items by type so a partial run still samples every content type instead of the alphabet's start.
 *
 * @template T
 * @param {T[]} items Items.
 * @param {(item:T)=>string} keyOf Bucket key.
 * @returns {T[]} Same items, round-robin across buckets.
 */
export function stratify(items, keyOf = item => item.type) {
  const buckets = new Map();
  for (const item of items) {
    const key = keyOf(item);
    if (!buckets.has(key)) buckets.set(key, []);
    buckets.get(key).push(item);
  }
  const lists = [...buckets.values()];
  const out = [];
  for (let round = 0; out.length < items.length; round++) for (const list of lists) if (round < list.length) out.push(list[round]);
  return out;
}

/** Write JSON through a temporary file so an interrupted run never leaves a half-written checkpoint. */
export async function writeJsonAtomic(path, data) {
  const temporary = `${path}.${process.pid}.tmp`;
  await writeFile(temporary, JSON.stringify(data, null, 2) + '\n');
  await rename(temporary, path);
}

/** Records of a JSON Lines file; a truncated last line (interrupted write) is ignored. */
export async function readJsonLines(path) {
  let text;
  try { text = await readFile(path, 'utf8'); } catch { return []; }
  const rows = [];
  for (const line of text.split('\n')) {
    if (!line.trim()) continue;
    try { rows.push(JSON.parse(line)); } catch { /* truncated trailing line */ }
  }
  return rows;
}

/** A recorded result is final when the server gave a definitive answer; errors and exhausted retries are re-queued on resume. */
export const isFinalRecord = record => Boolean(record) && record.status > 0 && !record.error;

/**
 * Load a checkpoint directory. Later lines win, so a successful retry replaces an earlier error.
 *
 * @param {string} dir Checkpoint directory.
 * @returns {Promise<{meta:object|null, results:Map<string,object>}>} Checkpoint content.
 */
export async function loadCheckpoint(dir) {
  let meta = null;
  try { meta = JSON.parse(await readFile(join(dir, 'meta.json'), 'utf8')); } catch { /* none yet */ }
  const results = new Map();
  for (const record of await readJsonLines(join(dir, 'results.jsonl'))) if (record?.path) results.set(record.path, record);
  return { meta, results };
}

/** Prepare an empty checkpoint directory (fresh run) or keep the existing one (resume). */
export async function prepareCheckpoint(dir, { fresh }) {
  await mkdir(dir, { recursive: true });
  if (fresh) await writeFile(join(dir, 'results.jsonl'), '');
}

/** Append finished records, one JSON object per line. */
export async function appendResults(dir, records) {
  if (records.length) await appendFile(join(dir, 'results.jsonl'), records.map(r => JSON.stringify(r)).join('\n') + '\n');
}

/**
 * Overall audit status, based only on how many URLs were really checked.
 *
 *  - FAILED: no usable sitemap, or not one URL has a definitive answer.
 *  - COMPLETE: every discovered URL has a definitive answer and every sitemap file loaded.
 *  - PARTIAL: anything in between (budget stop, unchecked URLs, errors after retries, sitemap files that failed).
 *
 * @param {{sitemapOk:boolean, discovered:number, checked:number, sitemapFailures:number}} counts Counts.
 * @returns {'COMPLETE'|'PARTIAL'|'FAILED'} Status.
 */
export function auditStatus({ sitemapOk, discovered, checked, sitemapFailures }) {
  if (!sitemapOk || discovered === 0 || checked === 0) return 'FAILED';
  return checked === discovered && sitemapFailures === 0 ? 'COMPLETE' : 'PARTIAL';
}
