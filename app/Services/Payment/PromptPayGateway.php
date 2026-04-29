<?php

namespace App\Services\Payment;

use App\Models\AppSetting;

class PromptPayGateway implements PaymentGatewayInterface
{
    // ----------------------------------------------------------------
    // Identity
    // ----------------------------------------------------------------

    public function getName(): string
    {
        return 'PromptPay';
    }

    public function getMethodType(): string
    {
        return 'promptpay';
    }

    // ----------------------------------------------------------------
    // Availability
    // ----------------------------------------------------------------

    public function isAvailable(): bool
    {
        $number = AppSetting::get('promptpay_number', '');
        return !empty(trim((string) $number));
    }

    // ----------------------------------------------------------------
    // Core operations
    // ----------------------------------------------------------------

    public function initiate(array $orderData): array
    {
        $promptpayNumber = AppSetting::get('promptpay_number', '');

        if (empty($promptpayNumber)) {
            return [
                'success'      => false,
                'redirect_url' => null,
                'qr_code'      => null,
                'data'         => ['error' => 'PromptPay number not configured'],
            ];
        }

        $amount  = (float) ($orderData['amount'] ?? 0);
        $payload = $this->generateEMVPayload($promptpayNumber, $amount);
        $qrUrl   = $this->buildQrImageUrl($payload);

        return [
            'success'      => true,
            'redirect_url' => null,
            'qr_code'      => $qrUrl,
            'data'         => [
                'promptpay_number' => $promptpayNumber,
                'amount'           => $amount,
                'qr_payload'       => $payload,
                'qr_url'           => $qrUrl,
            ],
        ];
    }

    public function verify(array $data): array
    {
        // PromptPay requires manual slip verification by admin.
        return ['success' => false, 'transaction_id' => null];
    }

    public function refund(string $transactionId, float $amount): array
    {
        return ['success' => false, 'message' => 'PromptPay refunds must be processed manually'];
    }

    // ----------------------------------------------------------------
    // Legacy shims
    // ----------------------------------------------------------------

    public function createPayment(array $data): array
    {
        return $this->initiate($data);
    }

    public function verifyPayment(string $transactionId): array
    {
        return ['success' => false, 'status' => 'pending', 'message' => 'Requires slip verification'];
    }

    public function handleWebhook(array $payload): array
    {
        return ['success' => false, 'message' => 'PromptPay does not support webhooks'];
    }

    // ----------------------------------------------------------------
    // EMVCo QR payload generation (Thai PromptPay standard)
    // ----------------------------------------------------------------

    /**
     * Generate an EMVCo-compliant PromptPay QR payload string.
     *
     * Reference: Bank of Thailand / PromptPay spec
     * Tag layout:
     *   00 – Payload Format Indicator (01)
     *   01 – Point of Initiation (12 = Dynamic, 11 = Static)
     *   29 – Merchant Account Information (AID + phone/ID number)
     *   52 – Merchant Category Code (0000)
     *   53 – Transaction Currency (764 = THB)
     *   54 – Transaction Amount (if non-zero)
     *   58 – Country Code (TH)
     *   59 – Merchant Name (NA)
     *   60 – Merchant City (Bangkok)
     *   63 – CRC-16/CCITT checksum (4 hex chars)
     */
    public function generateEMVPayload(string $target, float $amount = 0): string
    {
        // Strip non-digits
        $target = preg_replace('/[^0-9]/', '', $target);

        // Phone number (10 digits starting with 0) → convert to +66 format
        if (strlen($target) === 10 && $target[0] === '0') {
            $target = '0066' . substr($target, 1);
        }
        // 13-digit national ID – use as-is

        // Merchant account info sub-payload
        $guid    = 'A000000677010111';
        $aidTlv  = self::tlv('00', $guid);
        $numTlv  = self::tlv('01', $target);
        $maidVal = $aidTlv . $numTlv;

        $data  = '';
        $data .= self::tlv('00', '01');        // Payload Format Indicator
        $data .= self::tlv('01', $amount > 0 ? '12' : '11'); // Dynamic if amount set
        $data .= self::tlv('29', $maidVal);    // PromptPay Merchant Account Info
        $data .= self::tlv('52', '0000');      // MCC
        $data .= self::tlv('53', '764');       // THB
        if ($amount > 0) {
            $data .= self::tlv('54', number_format($amount, 2, '.', ''));
        }
        $data .= self::tlv('58', 'TH');        // Country
        $data .= self::tlv('59', 'NA');        // Merchant Name
        $data .= self::tlv('60', 'Bangkok');   // City
        $data .= '6304';                       // CRC tag + length placeholder

        $crc   = self::crc16Ccitt($data);
        return $data . strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Build a URL to render the QR code via qrserver.com (no local library needed).
     */
    private function buildQrImageUrl(string $payload): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
            'data'   => $payload,
            'size'   => '250x250',
            'ecc'    => 'M',
            'margin' => '10',
            'format' => 'png',
        ]);
    }

    /**
     * EMVCo TLV encoder: tag (2 chars) + length (2-digit zero-padded) + value
     */
    private static function tlv(string $tag, string $value): string
    {
        return $tag . sprintf('%02d', strlen($value)) . $value;
    }

    /**
     * CRC-16/CCITT (poly 0x1021, init 0xFFFF) — same as used by Thai PromptPay.
     */
    private static function crc16Ccitt(string $data): int
    {
        $crc = 0xFFFF;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
            }
        }
        return $crc & 0xFFFF;
    }
}
