# Load Testing with k6

## Install k6

**Windows (winget):**
```bash
winget install grafana.k6
```

**Windows (choco):**
```bash
choco install k6
```

**macOS:**
```bash
brew install k6
```

**Download:** https://grafana.com/docs/k6/latest/set-up/install-k6/

## Run Tests

Make sure the Laravel dev server is running first:
```bash
php artisan serve
```

### Smoke Test (Quick - 2 minutes)
Basic test with 5-20 virtual users hitting public pages:
```bash
k6 run tests/load/smoke.js
```

### Stress Test (Full - 4 minutes)
Authenticated user flow with up to 50 concurrent users:
```bash
k6 run tests/load/stress.js
```

### Custom Base URL
```bash
k6 run -e BASE_URL=http://your-server.com tests/load/smoke.js
```

### Custom Credentials
```bash
k6 run -e BASE_URL=http://localhost:8000 -e USER_EMAIL=test@test.com -e USER_PASSWORD=secret tests/load/stress.js
```

## Reading Results

Key metrics to watch:
- `http_req_duration` - Response time (p95 should be < 500ms for smoke, < 1000ms for stress)
- `http_req_failed` - Error rate (should be < 1% for smoke, < 5% for stress)
- `checks` - Assertion pass rate (should be > 90%)
- `vus` - Active virtual users at any point

## Thresholds

| Test   | p95 Response | p99 Response | Error Rate |
|--------|-------------|-------------|------------|
| Smoke  | < 500ms     | < 1500ms    | < 1%       |
| Stress | < 1000ms    | < 3000ms    | < 5%       |
