import http from 'k6/http';
import { check, sleep } from 'k6';

// ─── Configuration ───
const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const USER_EMAIL    = __ENV.USER_EMAIL    || 'user@photogallery.com';
const USER_PASSWORD = __ENV.USER_PASSWORD || 'password123';

export const options = {
    scenarios: {
        authenticated_flow: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 10 },
                { duration: '2m',  target: 30 },
                { duration: '1m',  target: 50 },
                { duration: '30s', target: 0 },
            ],
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<1000', 'p(99)<3000'],
        http_req_failed:   ['rate<0.05'],
        'checks':          ['rate>0.90'],
    },
};

// ─── Authenticated User Flow ───
export default function () {
    // 1. Get login page (grab CSRF token)
    const loginPage = http.get(`${BASE_URL}/login`);
    const csrfMatch = loginPage.body.match(/name="_token"\s+value="([^"]+)"/);
    const token = csrfMatch ? csrfMatch[1] : '';

    // 2. Login
    const loginRes = http.post(`${BASE_URL}/login`, {
        _token:   token,
        email:    USER_EMAIL,
        password: USER_PASSWORD,
    }, {
        redirects: 5,
    });

    check(loginRes, {
        'login succeeded': (r) => r.status === 200 || r.status === 302,
    });

    sleep(1);

    // 3. Browse events
    const events = http.get(`${BASE_URL}/events`);
    check(events, {
        'events page loads': (r) => r.status === 200,
    });

    sleep(1);

    // 4. View cart
    const cart = http.get(`${BASE_URL}/cart`);
    check(cart, {
        'cart page loads': (r) => r.status === 200,
    });

    sleep(1);

    // 5. Profile page
    const profile = http.get(`${BASE_URL}/profile`);
    check(profile, {
        'profile page loads': (r) => r.status === 200,
    });

    sleep(1);

    // 6. View orders
    const orders = http.get(`${BASE_URL}/orders`);
    check(orders, {
        'orders page loads': (r) => r.status === 200,
    });

    sleep(0.5);
}
