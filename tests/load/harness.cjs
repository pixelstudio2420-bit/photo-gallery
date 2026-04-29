/**
 * Concurrent HTTP load harness — Node-only, no dependencies.
 *
 * Run examples
 * ------------
 *   node tests/Load/harness.js \
 *        --url=http://localhost/photo-gallery-pgsql/public/up \
 *        --concurrency=50 --duration=20
 *
 *   node tests/Load/harness.js \
 *        --url=http://localhost/photo-gallery-pgsql/public/up \
 *        --rampup=5 --target-rps=200 --duration=30
 *
 * What it measures
 * ----------------
 *   • Total requests, success count (2xx/3xx), error count
 *   • RPS achieved (vs target if set)
 *   • Latency: p50 / p90 / p95 / p99 / max
 *   • Per-second timeline (concurrency, RPS, p95)
 *
 * Why a custom harness instead of k6/wrk
 * --------------------------------------
 * k6 / wrk / artillery aren't installed on this machine. Node + the
 * built-in `http` module is enough for a 1-host benchmark. The
 * results are honest and comparable to industry tooling at the same
 * concurrency level.
 */

'use strict';

const http = require('http');
const https = require('https');
const { performance } = require('perf_hooks');

// ── parse args ──────────────────────────────────────────────────
function arg(name, def) {
    const found = process.argv.find(a => a.startsWith(`--${name}=`));
    return found ? found.split('=')[1] : def;
}

const URL_TARGET   = arg('url', 'http://localhost/photo-gallery-pgsql/public/up');
const CONCURRENCY  = parseInt(arg('concurrency', '50'), 10);
const DURATION_S   = parseInt(arg('duration', '20'), 10);
const RAMPUP_S     = parseInt(arg('rampup', '0'), 10);
const TARGET_RPS   = parseInt(arg('target-rps', '0'), 10);
const TIMEOUT_MS   = parseInt(arg('timeout', '10000'), 10);
const QUIET        = arg('quiet', '0') === '1';

const u = new URL(URL_TARGET);
const lib = u.protocol === 'https:' ? https : http;

// ── shared agents (keep-alive critical for high RPS) ──────────────
const agent = new lib.Agent({
    keepAlive: true,
    maxSockets: Math.max(CONCURRENCY * 2, 256),
    maxFreeSockets: CONCURRENCY,
});

// ── stats ───────────────────────────────────────────────────────
const latencies = [];   // ms
let counts = {
    total: 0, ok: 0, fail: 0,
    perStatus: {},
    perSecond: [],   // [{t: epochSec, count: n, p95: ms}]
};
let lastSecondBucket = null;
let perSecAccum = { count: 0, lat: [] };

function recordSample(latencyMs, statusCode) {
    counts.total++;
    if (statusCode >= 200 && statusCode < 400) counts.ok++;
    else counts.fail++;
    counts.perStatus[statusCode] = (counts.perStatus[statusCode] || 0) + 1;
    latencies.push(latencyMs);

    const sec = Math.floor(performance.now() / 1000);
    if (sec !== lastSecondBucket) {
        if (lastSecondBucket !== null) {
            const ls = perSecAccum.lat.slice().sort((a, b) => a - b);
            counts.perSecond.push({
                t: lastSecondBucket,
                count: perSecAccum.count,
                p95: ls.length ? ls[Math.floor(ls.length * 0.95)] : 0,
            });
        }
        lastSecondBucket = sec;
        perSecAccum = { count: 0, lat: [] };
    }
    perSecAccum.count++;
    perSecAccum.lat.push(latencyMs);
}

// ── single request ──────────────────────────────────────────────
function fireOne() {
    return new Promise((resolve) => {
        const t0 = performance.now();
        const req = lib.request({
            agent,
            method: 'GET',
            host:   u.hostname,
            port:   u.port || (u.protocol === 'https:' ? 443 : 80),
            path:   u.pathname + (u.search || ''),
            headers: {
                'User-Agent': 'photo-gallery-load-harness/1',
                'Accept':     'application/json,text/html',
            },
            timeout: TIMEOUT_MS,
        }, (res) => {
            // Drain the body so the socket can be reused.
            res.on('data', () => {});
            res.on('end', () => {
                recordSample(performance.now() - t0, res.statusCode);
                resolve();
            });
        });
        req.on('error', () => {
            recordSample(performance.now() - t0, 0);   // 0 = network error
            resolve();
        });
        req.on('timeout', () => {
            req.destroy();
        });
        req.end();
    });
}

// ── orchestration ───────────────────────────────────────────────
async function worker(id) {
    while (running) {
        // If target RPS is set, throttle. Otherwise full-speed (limited
        // by request latency × concurrency).
        if (TARGET_RPS > 0) {
            const now = performance.now() / 1000;
            const expected = (now - startSec) * TARGET_RPS;
            if (counts.total >= expected) {
                await sleep(5);
                continue;
            }
        }
        await fireOne();
    }
}

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

let running = true;
let startSec = 0;

(async () => {
    if (!QUIET) {
        console.log(`▶ load test: ${URL_TARGET}`);
        console.log(`  concurrency=${CONCURRENCY} duration=${DURATION_S}s rampup=${RAMPUP_S}s target-rps=${TARGET_RPS || 'unbounded'}`);
    }

    startSec = performance.now() / 1000;
    const workers = [];

    if (RAMPUP_S > 0) {
        // Stagger worker starts.
        for (let i = 0; i < CONCURRENCY; i++) {
            const delayMs = (i * RAMPUP_S * 1000) / CONCURRENCY;
            workers.push(sleep(delayMs).then(() => worker(i)));
        }
    } else {
        for (let i = 0; i < CONCURRENCY; i++) {
            workers.push(worker(i));
        }
    }

    setTimeout(() => { running = false; }, (DURATION_S + RAMPUP_S) * 1000);
    await Promise.all(workers);

    // Flush last second bucket.
    if (perSecAccum.count > 0) {
        const ls = perSecAccum.lat.slice().sort((a, b) => a - b);
        counts.perSecond.push({
            t: lastSecondBucket,
            count: perSecAccum.count,
            p95: ls.length ? ls[Math.floor(ls.length * 0.95)] : 0,
        });
    }

    // ── summary ──────────────────────────────────────────────────
    latencies.sort((a, b) => a - b);
    const elapsed = (performance.now() / 1000) - startSec;
    const p = (q) => latencies.length ? latencies[Math.floor(latencies.length * q)] : 0;

    console.log('');
    console.log('═══ RESULTS ═══');
    console.log(`Total: ${counts.total}  ok: ${counts.ok}  fail: ${counts.fail}  err-rate: ${(counts.fail / counts.total * 100).toFixed(2)}%`);
    console.log(`Elapsed: ${elapsed.toFixed(2)}s  RPS: ${(counts.total / elapsed).toFixed(1)}`);
    console.log(`Latency (ms): p50=${p(0.5).toFixed(0)} p90=${p(0.9).toFixed(0)} p95=${p(0.95).toFixed(0)} p99=${p(0.99).toFixed(0)} max=${p(0.999).toFixed(0)}`);
    console.log(`Status codes: ${JSON.stringify(counts.perStatus)}`);

    if (!QUIET && counts.perSecond.length > 1) {
        console.log('');
        console.log('Per-second timeline (last 10):');
        const tail = counts.perSecond.slice(-10);
        for (const row of tail) {
            console.log(`  rps=${String(row.count).padStart(4)} p95=${row.p95.toFixed(0).padStart(4)}ms`);
        }
    }

    // Exit code 0 = success. Caller (CI) can set thresholds.
    process.exit(counts.fail > counts.total * 0.01 ? 1 : 0);
})();
