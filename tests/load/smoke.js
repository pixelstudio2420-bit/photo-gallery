import http from 'k6/http';
import { check, sleep } from 'k6';

// ─── Configuration ───
const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

export const options = {
    scenarios: {
        smoke: {
            executor: 'constant-vus',
            vus: 5,
            duration: '30s',
        },
        load: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 20 },
                { duration: '1m',  target: 20 },
                { duration: '30s', target: 0 },
            ],
            startTime: '35s',
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<500', 'p(99)<1500'],
        http_req_failed:   ['rate<0.01'],
    },
};

// ─── Test Scenarios ───
export default function () {
    // 1. Homepage
    const home = http.get(`${BASE_URL}/`);
    check(home, {
        'homepage status 200': (r) => r.status === 200,
        'homepage has content': (r) => r.body.length > 0,
    });

    sleep(1);

    // 2. Events listing
    const events = http.get(`${BASE_URL}/events`);
    check(events, {
        'events status 200': (r) => r.status === 200,
    });

    sleep(1);

    // 3. Login page
    const login = http.get(`${BASE_URL}/login`);
    check(login, {
        'login page status 200': (r) => r.status === 200,
    });

    sleep(1);

    // 4. Help page
    const help = http.get(`${BASE_URL}/help`);
    check(help, {
        'help page status 200': (r) => r.status === 200,
    });

    sleep(0.5);
}
