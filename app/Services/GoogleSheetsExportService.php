<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Append/update booking rows into a Google Sheet.
 *
 * Why Sheets export instead of just running off the DB
 * ----------------------------------------------------
 * Photographers + studio owners are often non-technical and live in
 * Sheets. They want to slice/dice the booking pipeline (revenue per
 * month, no-show rate, weekend vs weekday split) without learning SQL.
 * A live "Bookings" sheet that tracks the DB is a 5-minute power-up
 * for them.
 *
 * Auth model
 * ----------
 * Three options exist: per-photographer OAuth (re-using the GCal flow),
 * a single shared service account, or an admin-set API key. We use the
 * service account approach because it:
 *   • doesn't depend on each photographer linking Google,
 *   • keeps the spreadsheet ownership stable (admin's account),
 *   • is the standard pattern for backoffice exports.
 *
 * The service account JSON lives in app_settings (key
 * `google_service_account_json`). Admins paste it once, the service
 * exchanges it for an access_token using the JWT-bearer grant.
 *
 * Idempotency
 * -----------
 * booking_sheets_exports has one row per (booking, attempt). On
 * 'append' the service writes a new row at the end of the sheet; on
 * 'update' it locates the existing booking row by ID column and
 * replaces it. Re-running an append is safe in the sense that it
 * produces a new row + a new audit entry (caller should use 'update'
 * for re-syncs of an already-exported booking).
 */
class GoogleSheetsExportService
{
    private const SHEETS_API_BASE = 'https://sheets.googleapis.com/v4';
    private const TOKEN_ENDPOINT  = 'https://oauth2.googleapis.com/token';

    public function isEnabled(): bool
    {
        return AppSetting::get('google_sheets_export_enabled', '0') === '1'
            && AppSetting::get('google_service_account_json', '') !== ''
            && AppSetting::get('google_sheets_bookings_id', '') !== '';
    }

    public function spreadsheetId(): string
    {
        return (string) AppSetting::get('google_sheets_bookings_id', '');
    }

    /**
     * Returns the service-account's `client_email` for the admin UI.
     *
     * Why this matters
     * ----------------
     * Service-account access to a Sheet only works if the admin shares
     * the spreadsheet with the SA email. A common setup mistake is to
     * paste the JSON, set the spreadsheet id, and never go back to
     * Google Sheets to add the SA as a viewer/editor — every export
     * then 403s. The admin UI calls this to surface the email so the
     * admin sees "share spreadsheet with sheets-export@xxx.iam.gserviceaccount.com"
     * during setup, not 24 hours later when the audit table is full of
     * "permission denied" rows.
     *
     * Returns null when the SA JSON is missing or malformed; admin UI
     * should hide / disable the share-this-email widget in that case.
     */
    public function serviceAccountEmail(): ?string
    {
        $json = (string) AppSetting::get('google_service_account_json', '');
        if ($json === '') return null;
        $data = json_decode($json, true);
        if (!is_array($data)) return null;
        $email = (string) ($data['client_email'] ?? '');
        // Sanity: must look like an email; otherwise we'd render
        // garbage from a malformed paste.
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * Health-check helper for the admin UI: returns ['ok' => bool, 'email',
     * 'spreadsheet_id', 'reason']. The admin "Test connection" button
     * calls this to surface a single yes/no answer + the SA email and
     * any setup hint (e.g. "share sheet with this email").
     */
    public function healthCheck(): array
    {
        $email = $this->serviceAccountEmail();
        if (!$email) {
            return [
                'ok'             => false,
                'email'          => null,
                'spreadsheet_id' => $this->spreadsheetId(),
                'reason'         => 'service-account JSON is missing or malformed',
            ];
        }
        if (!$this->spreadsheetId()) {
            return [
                'ok'             => false,
                'email'          => $email,
                'spreadsheet_id' => null,
                'reason'         => 'google_sheets_bookings_id not set',
            ];
        }
        // Try a real auth + read of the sheet's metadata. If we 403,
        // the most likely cause is "SA not shared on the sheet" — we
        // surface that to the admin in plain language.
        $token = $this->getServiceAccountToken();
        if (!$token) {
            return [
                'ok'             => false,
                'email'          => $email,
                'spreadsheet_id' => $this->spreadsheetId(),
                'reason'         => 'failed to mint service-account token (private_key invalid?)',
            ];
        }

        try {
            $resp = \Illuminate\Support\Facades\Http::withToken($token)
                ->timeout(5)
                ->get(self::SHEETS_API_BASE . '/spreadsheets/' . $this->spreadsheetId() . '?fields=spreadsheetId,properties.title');
            if ($resp->successful()) {
                return [
                    'ok'             => true,
                    'email'          => $email,
                    'spreadsheet_id' => $this->spreadsheetId(),
                    'title'          => (string) $resp->json('properties.title', ''),
                    'reason'         => null,
                ];
            }
            return [
                'ok'             => false,
                'email'          => $email,
                'spreadsheet_id' => $this->spreadsheetId(),
                'reason'         => $resp->status() === 403
                    ? "share the spreadsheet with {$email}"
                    : "Sheets API error: " . substr($resp->body(), 0, 200),
            ];
        } catch (\Throwable $e) {
            return [
                'ok'             => false,
                'email'          => $email,
                'spreadsheet_id' => $this->spreadsheetId(),
                'reason'         => 'exception: ' . substr($e->getMessage(), 0, 200),
            ];
        }
    }

    /**
     * Append a booking as a new row at the end of the bookings sheet.
     * Returns true on success. Audit is always written, even on failure.
     */
    public function appendBooking(Booking $booking): bool
    {
        if (!$this->isEnabled()) return false;

        $sheetId = $this->spreadsheetId();
        $range   = (string) AppSetting::get('google_sheets_bookings_range', 'Bookings!A:M');

        $auditId = DB::table('booking_sheets_exports')->insertGetId([
            'booking_id'     => $booking->id,
            'spreadsheet_id' => $sheetId,
            'range'          => $range,
            'operation'      => 'append',
            'status'         => 'pending',
            'attempts'       => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        try {
            $token = $this->getServiceAccountToken();
            if (!$token) {
                $this->markAudit($auditId, 'failed', null, 'service account auth failed');
                return false;
            }

            $row = $this->bookingRow($booking);
            $url = self::SHEETS_API_BASE
                 . "/spreadsheets/{$sheetId}/values/" . rawurlencode($range)
                 . ':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS';

            $resp = Http::withToken($token)
                ->timeout(15)
                ->post($url, ['values' => [$row]]);

            if (!$resp->successful()) {
                $this->markAudit($auditId, 'failed', $resp->status(),
                    substr($resp->body(), 0, 400));
                return false;
            }

            // Sheets returns updates.updatedRange like "Bookings!A37:M37".
            $updatedRange = (string) $resp->json('updates.updatedRange', '');
            $this->markAudit($auditId, 'succeeded', $resp->status(), null, $updatedRange);
            return true;
        } catch (\Throwable $e) {
            $this->markAudit($auditId, 'failed', null,
                substr($e->getMessage(), 0, 400));
            return false;
        }
    }

    /**
     * Find the existing row by booking id (column A) and overwrite it.
     * Used when a booking's status / time / customer changes after the
     * initial export.
     */
    public function updateBooking(Booking $booking): bool
    {
        if (!$this->isEnabled()) return false;

        $sheetId = $this->spreadsheetId();
        $auditId = DB::table('booking_sheets_exports')->insertGetId([
            'booking_id'     => $booking->id,
            'spreadsheet_id' => $sheetId,
            'operation'      => 'update',
            'status'         => 'pending',
            'attempts'       => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        try {
            $token = $this->getServiceAccountToken();
            if (!$token) {
                $this->markAudit($auditId, 'failed', null, 'service account auth failed');
                return false;
            }

            // 1. Read the ID column to locate the row.
            $rangeRead = 'Bookings!A:A';
            $readResp = Http::withToken($token)
                ->timeout(10)
                ->get(self::SHEETS_API_BASE . "/spreadsheets/{$sheetId}/values/" . rawurlencode($rangeRead));
            if (!$readResp->successful()) {
                $this->markAudit($auditId, 'failed', $readResp->status(),
                    'lookup failed: ' . substr($readResp->body(), 0, 200));
                return false;
            }
            $values = $readResp->json('values', []);
            $rowIndex = null;
            foreach ((array) $values as $idx => $cell) {
                if ((string) ($cell[0] ?? '') === (string) $booking->id) {
                    // +1 because sheets are 1-indexed and we already
                    // skip the header row by virtue of having data here.
                    $rowIndex = $idx + 1;
                    break;
                }
            }
            if (!$rowIndex) {
                // No existing row → fall back to append.
                $this->markAudit($auditId, 'skipped', 200, 'row not found, falling back to append');
                return $this->appendBooking($booking);
            }

            $writeRange = "Bookings!A{$rowIndex}:M{$rowIndex}";
            $row        = $this->bookingRow($booking);
            $writeUrl   = self::SHEETS_API_BASE
                       . "/spreadsheets/{$sheetId}/values/" . rawurlencode($writeRange)
                       . '?valueInputOption=USER_ENTERED';

            $writeResp = Http::withToken($token)
                ->timeout(15)
                ->put($writeUrl, ['values' => [$row]]);

            if (!$writeResp->successful()) {
                $this->markAudit($auditId, 'failed', $writeResp->status(),
                    substr($writeResp->body(), 0, 400));
                return false;
            }

            $this->markAudit($auditId, 'succeeded', $writeResp->status(), null, $writeRange);
            return true;
        } catch (\Throwable $e) {
            $this->markAudit($auditId, 'failed', null,
                substr($e->getMessage(), 0, 400));
            return false;
        }
    }

    /**
     * Format a booking as a sheet row. The column ordering is fixed —
     * if it changes here, the lookup in updateBooking() (column A = ID)
     * must stay consistent.
     */
    private function bookingRow(Booking $booking): array
    {
        $booking->loadMissing(['customer:id,first_name,last_name,email', 'photographer:id,user_id']);
        return [
            (string) $booking->id,                                  // A
            (string) ($booking->scheduled_at?->toIso8601String() ?? ''),  // B
            (int) ($booking->duration_minutes ?? 0),                // C
            (string) $booking->title,                               // D
            trim(($booking->customer?->first_name ?? '') . ' ' . ($booking->customer?->last_name ?? '')), // E
            (string) ($booking->customer_phone ?? ''),              // F
            (string) ($booking->location ?? ''),                    // G
            (string) ($booking->status ?? ''),                      // H
            (float)  ($booking->agreed_price ?? 0),                 // I
            (float)  ($booking->deposit_paid ?? 0),                 // J
            (string) ($booking->cancelled_by ?? ''),                // K
            (string) ($booking->customer_notes ?? ''),              // L
            (string) ($booking->updated_at?->toIso8601String() ?? ''), // M
        ];
    }

    /**
     * JWT-bearer grant for the service account: sign a JWT with the
     * service account's private key, exchange it for an access token.
     * Token is cached for ~50 minutes (slightly under the 1h Google
     * issues, to leave headroom).
     */
    private function getServiceAccountToken(): ?string
    {
        $cacheKey = 'google_sheets_sa_token';
        $cached = \Cache::get($cacheKey);
        if ($cached) return (string) $cached;

        $json = (string) AppSetting::get('google_service_account_json', '');
        if ($json === '') return null;
        $sa = json_decode($json, true);
        if (!is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) {
            Log::warning('GoogleSheetsExport: malformed service account JSON');
            return null;
        }

        try {
            $now = time();
            $jwtClaim = [
                'iss'   => $sa['client_email'],
                'scope' => 'https://www.googleapis.com/auth/spreadsheets',
                'aud'   => self::TOKEN_ENDPOINT,
                'iat'   => $now,
                'exp'   => $now + 3600,
            ];
            $header = ['alg' => 'RS256', 'typ' => 'JWT'];

            $b64 = fn ($data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
            $headerB64 = $b64($header);
            $claimB64  = $b64($jwtClaim);
            $signing   = "{$headerB64}.{$claimB64}";

            $signature = '';
            $ok = openssl_sign($signing, $signature, $sa['private_key'], OPENSSL_ALGO_SHA256);
            if (!$ok) {
                Log::warning('GoogleSheetsExport: openssl_sign failed (private_key wrong format?)');
                return null;
            }
            $sigB64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
            $jwt    = "{$signing}.{$sigB64}";

            $resp = Http::asForm()
                ->timeout(10)
                ->post(self::TOKEN_ENDPOINT, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ]);
            if (!$resp->successful()) {
                Log::warning('GoogleSheetsExport: token exchange failed', [
                    'status' => $resp->status(),
                    'body'   => substr($resp->body(), 0, 200),
                ]);
                return null;
            }
            $token = (string) $resp->json('access_token');
            $expiresIn = (int) ($resp->json('expires_in') ?: 3600);
            \Cache::put($cacheKey, $token, max(60, $expiresIn - 600));
            return $token;
        } catch (\Throwable $e) {
            Log::warning('GoogleSheetsExport: token exception', ['err' => $e->getMessage()]);
            return null;
        }
    }

    private function markAudit(int $auditId, string $status, ?int $httpStatus, ?string $error, ?string $rowA1 = null): void
    {
        DB::table('booking_sheets_exports')->where('id', $auditId)->update([
            'status'      => $status,
            'http_status' => $httpStatus,
            'error'       => $error,
            'row_a1'      => $rowA1,
            'synced_at'   => $status === 'succeeded' ? now() : null,
            'updated_at'  => now(),
        ]);
    }
}
