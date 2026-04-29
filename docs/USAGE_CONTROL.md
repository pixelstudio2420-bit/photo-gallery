# Usage Control — Operator Runbook

Owner: Platform / Cost Engineering
Last review: 2026-04-27
On-call escalation: see `Admin → Settings → Alert Rules`

This document is what an on-call engineer reads at 03:00 when AWS sends a billing alert. It explains how the platform's cost-control machinery works, what to do when it trips, and what's safe vs unsafe to touch.

## TL;DR — The 3 Layers

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. AntiAbuseGuard      — stops abusive Free signups before they │
│                          consume any resources                  │
│ 2. EnforceUsageQuota    — per-user / per-plan rate caps         │
│ 3. CircuitBreaker      — platform-wide kill-switch when monthly │
│                          THB spend exceeds threshold            │
└─────────────────────────────────────────────────────────────────┘
```

Every metered operation flows through all three. If a user makes it past all of them, the call is recorded in `usage_events` (immutable ledger) and `usage_counters` (hot rolling counter) by `UsageMeter::record()`.

---

## Tables

| Table              | Role                                  | Retention |
|--------------------|---------------------------------------|-----------|
| `usage_events`     | Append-only ledger of every charge   | 13 months |
| `usage_counters`   | Hot rolling counters per user/period | 2h–13mo by period |
| `circuit_breakers` | One row per declared feature          | Indefinite (auto-resets monthly) |
| `signup_signals`   | Anti-abuse signals from signup attempts | 90 days |

Ownership: `app/Models/UsageEvent.php`, `UsageCounter.php`, `CircuitBreaker.php`. **Never** mutate these tables manually outside the prune command.

---

## Daily / hourly cron jobs

| Schedule                         | Command                          | Purpose |
|----------------------------------|----------------------------------|---------|
| Hourly @ :05                     | `usage:detect-spikes`            | Flag users 10× over their 7d baseline |
| Daily @ 02:35                    | `usage:prune`                    | Delete old counter / event / signup rows |

If a job stops running, you'll see a slow drift:
- Spike detector silent → abuse is still capped by per-user quota, just not flagged proactively.
- Prune silent → tables grow ~few GB/month. Acceptable for weeks; fix when convenient.

---

## What to do when …

### "Cost alert from AWS — Rekognition is spiking"

1. **Don't panic.** The `ai.face_search` circuit breaker auto-trips at the configured monthly THB ceiling (config/usage.php → `breakers.ai.face_search.monthly_thb_ceiling`).
2. Check **Admin → Usage & Margin** dashboard. Look at the "Circuit breakers" panel.
3. If the breaker is already **OPEN**: the bleeding has already stopped. Investigate `usage_events` for the offending user(s):
   ```sql
   SELECT user_id, plan_code, COUNT(*), SUM(cost_microcents)
   FROM usage_events
   WHERE resource = 'ai.face_search' AND occurred_at > NOW() - INTERVAL '24 hours'
   GROUP BY user_id, plan_code ORDER BY 4 DESC LIMIT 20;
   ```
4. If the breaker is **CLOSED** but cost is rising fast: trip it manually via the dashboard's "Trip now" button, or:
   ```php
   app(\App\Services\Usage\CircuitBreakerService::class)->trip('ai.face_search', 'Manual: AWS billing alert at 03:42');
   ```
5. Once root cause found (usually one abusive user; sometimes a logic bug retrying), reset the breaker. State goes OPEN → HALF_OPEN; the next overspend re-trips it.

### "User is complaining they hit 'quota exceeded' on Pro plan"

1. Verify their plan: `photographer_profiles.subscription_plan_code` should match what they're paying for.
2. Check the response headers from their failed request — they include:
   - `X-Quota-Resource`
   - `X-Quota-Used` / `X-Quota-Limit`
   - `X-Quota-Period`
   - `X-Quota-Upgrade-To` (deeplink to upgrade page)
3. Look up their counter directly:
   ```php
   App\Services\Usage\UsageMeter::counter($userId, 'ai.face_search', 'month');
   ```
4. Compare to `config('usage.plan_caps.pro.ai.face_search')`.
5. If the cap genuinely is unfair, **don't** raise it for this one user — the path is:
   - Issue a one-month overage credit via the existing credit system, OR
   - Upgrade them to the next plan tier

### "I see a flagged spike but the user looks legit"

The spike detector is conservative — `min_baseline_calls=50` and `multiple_of_7d_avg=10`. Most flags are real. But if a paying customer just kicked off a bulk-tag job after months of light use, they'll trip this.

Action:
- Check `usage_events.metadata` for the spike row to see the multiplier.
- If legitimate (e.g. they uploaded a new event), no action — flag clears as their baseline rises in subsequent runs.
- If suspicious (rapid login changes, new IP), suspend the account and review.

### "Anti-abuse blocked a legit signup"

`signup_signals` rows have `flagged=true` and `risk_score`. Look up the row:

```sql
SELECT id, risk_score, metadata, created_at
FROM signup_signals
WHERE ip_hash = sha256_of_ip
ORDER BY created_at DESC LIMIT 5;
```

The `metadata.reasons` array tells you which heuristic fired.

If false-positive: there's no in-band override (intentional — gives attackers info). Ask the user to switch network or use a different email. The score decays after 24h once their IP/email-stem stops hitting the velocity limits.

To **temporarily disable** anti-abuse platform-wide (for an event registration drive or similar):
```env
ANTI_ABUSE_ENABLED=false
```
Then `php artisan config:cache`.

---

## Adding a new metered feature

1. **Declare the resource** in `config/usage.php`:
   ```php
   'pricing' => [
       'ai.my_new_feature' => 5_000,  // microcents per call
   ],
   'plan_caps' => [
       'free'    => ['ai.my_new_feature' => ['period' => 'month', 'hard' => 0]],
       'starter' => ['ai.my_new_feature' => ['period' => 'month', 'hard' => 100]],
       'pro'     => ['ai.my_new_feature' => ['period' => 'month', 'hard' => 1_000, 'soft' => 800]],
       // …
   ],
   'breakers' => [
       'ai.my_new_feature' => ['monthly_thb_ceiling' => 5_000, 'reset_period' => 'month'],
   ],
   ```

2. **Gate the route** with the middleware:
   ```php
   Route::post('/api/my-feature', ...)
       ->middleware(['auth', 'usage.quota:ai.my_new_feature']);
   ```

3. **Record after success** (in the controller / service, AFTER the vendor call returns OK):
   ```php
   UsageMeter::record(
       userId:   $userId,
       planCode: $planCode,
       resource: 'ai.my_new_feature',
       units:    1,
       metadata: ['request_id' => $requestId],
   );
   app(CircuitBreakerService::class)->charge('ai.my_new_feature', $costThb);
   ```

4. **Test** with a dry-run: hit the endpoint enough times to exceed `soft`, then `hard`, then trip the breaker. Verify each gives the right HTTP status.

That's it — no DB migrations needed, no admin UI changes. The dashboard auto-discovers new categories from the config.

---

## Disaster scenarios + recovery

### "Disk full — can't write to usage_events"

UsageMeter wraps every write in `try/catch` and logs without re-throwing. The user-facing operation completes; the counter MAY drift. Fix:
1. Free disk space.
2. Rebuild counters from the ledger:
   ```sql
   -- Recompute for one user
   INSERT INTO usage_counters (user_id, resource, period, period_key, units, cost_microcents, updated_at)
   SELECT user_id, resource, 'month',
          to_char(occurred_at, 'YYYY-MM'),
          SUM(units),
          SUM(cost_microcents),
          NOW()
   FROM usage_events
   WHERE user_id = ? AND occurred_at > NOW() - INTERVAL '13 months'
   GROUP BY user_id, resource, to_char(occurred_at, 'YYYY-MM')
   ON CONFLICT (user_id, resource, period, period_key) DO UPDATE SET
       units = EXCLUDED.units,
       cost_microcents = EXCLUDED.cost_microcents,
       updated_at = NOW();
   ```

### "Breaker is stuck OPEN; we need to ship a hot-fix"

The breaker is an emergency brake — never override it without first understanding *why* it's open. If you're confident:
```php
app(\App\Services\Usage\CircuitBreakerService::class)
    ->reset('ai.face_search', 'Hotfix deployed; root cause was XXX');
```

State goes OPEN → HALF_OPEN. The next overspend re-trips immediately. If the hotfix is correct, spend stays under the ceiling and the period rollover (1st of next month) cleanly resets to CLOSED.

### "Anti-abuse signup_signals table is huge"

Should be < 500MB even with 90 days of attacker traffic. If it's bigger:
1. Check for a bot loop: `SELECT ip_hash, COUNT(*) FROM signup_signals GROUP BY ip_hash ORDER BY 2 DESC LIMIT 10;`
2. Add the offending IP to Cloudflare's blocklist directly (faster than the 3-per-day rule).
3. Manually prune:
   ```sql
   DELETE FROM signup_signals WHERE created_at < NOW() - INTERVAL '30 days';
   ```

---

## Configuration reference

All knobs live in `config/usage.php`:

| Key                                          | Default | What it controls |
|----------------------------------------------|---------|------------------|
| `enforcement_enabled`                        | true    | Master switch for the quota middleware |
| `usd_to_thb_rate`                            | 35.0    | FX for cost reporting |
| `pricing[$resource]`                         | various | Microcents per unit |
| `plan_caps[$plan][$resource]`                | various | hard / soft / period for one plan × resource |
| `overage[$resource][$plan]`                  | various | THB per unit beyond cap (for opted-in customers) |
| `breakers[$feature].monthly_thb_ceiling`     | various | Trip-open threshold |
| `anti_abuse.enabled`                         | true    | Anti-multi-account guard |
| `anti_abuse.max_per_ip_per_day`              | 3       | After N free signups from same hashed IP, score elevates |
| `anti_abuse.block_at_risk_score`             | 80      | Hard refuse signup |
| `anti_abuse.flag_at_risk_score`              | 50      | Require email verification + Turnstile |
| `spike_detection.multiple_of_7d_avg`         | 10      | Trip threshold for hourly detector |
| `spike_detection.min_baseline_calls`         | 50      | Ignore noisy users below this baseline |

---

## What's intentionally NOT built

| Feature                  | Reason |
|--------------------------|--------|
| Per-user breaker         | Per-user caps already exist as `plan_caps`. Adding a separate per-user breaker would duplicate that logic. |
| Real-time billing webhooks for overage | Out of scope — the platform records usage; overage billing flow is a separate billing-team feature. |
| HEIC → JPEG inline conversion | Storage cost saving; planned for next sprint. |

## Where the wiring lives (Session 6 + 7 wire-up summary)

| Resource           | Recorded by                                                                | Reversed by                              | Gated by                          |
|--------------------|----------------------------------------------------------------------------|------------------------------------------|-----------------------------------|
| `ai.face_search`   | `Public/FaceSearchController::search` (after Rekognition success)         | n/a (rate-style cap)                     | `usage.quota:ai.face_search` on `/api/face-search/{id}` |
| `photo.upload`     | `Photographer/PhotoController::store`                                     | n/a (rate-style cap)                     | `usage.quota:photo.upload` on `/photographer/.../events/{id}/photos` |
| `storage.bytes`    | `Photographer/PhotoController::store` + `FileManagerService::upload`      | `PhotoController::destroy/bulkDelete`, `FileManagerService::delete` | (lifetime cap, hardcoded check via `EnforceStorageQuota` for photographers and `UserStorageService::canUpload` for consumer storage) |
| `export.run`       | `Public/DataExportController::store`                                      | n/a                                      | `usage.quota:export.run` on `POST /profile/data-export` |

## Sentry integration (when DSN is set)

The breaker `reportTripped()` method emits both:

1. `Log::error("Circuit breaker tripped: {feature}", $context)` — picked up
   by the Laravel-Sentry integration in `bootstrap/app.php`.
2. An explicit `\Sentry\captureMessage()` with structured tags:
   - `tag.breaker.feature` = the feature key (e.g. `ai.face_search`)
   - `tag.breaker.state`   = `'open'`
   - `extra.breaker`       = full context (spent, threshold, period dates)

Filter in Sentry by `breaker.feature:*` to get all trips. Set up an alert
rule `level:error AND breaker.state:open` to wake on-call.

## Anti-abuse in the register flow

`AuthController::register()` calls `AntiAbuseService::evaluateSignup()` BEFORE
`User::create()`. The service checks (in order):

1. **Disposable email blacklist** (mailinator, guerrillamail, …) → instant
   block.
2. **IP velocity** — N prior signups from the same hashed IP within 24h.
3. **Email stem reuse** — Gmail's `+alias` collapsed to canonical form.
4. **Device fingerprint reuse** — accepted from a hidden form field
   populated by `resources/js/device-fingerprint.js` (sha256 of UA +
   screen + timezone + canvas signature). The frontend auto-fills any
   `<input data-device-fingerprint>` element.

Decision codes:
- `OK`              — proceed.
- `REQUIRE_VERIFY`  — flag the session; subsequent feature access blocked
                      until email verification + Turnstile.
- `BLOCK`           — refuse signup outright with a generic error.

After signup completes, `linkSignalToUser()` joins the pre-signup
`signup_signals` row to the new `user_id` for forensic correlation.

To **disable** anti-abuse temporarily (e.g. event registration drive):
```env
ANTI_ABUSE_ENABLED=false
```
…then `php artisan config:cache`.

---

## Final checklist before production deploy

- [ ] `php artisan migrate` ran the 4 new migrations (usage_events, usage_counters, circuit_breakers, signup_signals)
- [ ] `config/usage.php` reviewed and committed (no `null` hard caps except Studio)
- [ ] `SIGNUP_SIGNAL_SALT` is set in `.env` (random 64-char string — don't reuse across envs)
- [ ] `php artisan schedule:run` is on cron (`* * * * *`)
- [ ] `php artisan queue:work` is running (PurgeR2CdnCacheJob also drains here)
- [ ] Sentry DSN is set so circuit-breaker `Log::error` reaches an alert channel
- [ ] Admin role can reach `/admin/usage` (RBAC if applicable)
- [ ] Smoke tested: hit a `usage.quota`-gated endpoint until you see 402 with the expected headers
