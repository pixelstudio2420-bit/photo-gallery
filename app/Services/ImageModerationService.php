<?php

namespace App\Services;

use App\Models\AppSetting;
use Aws\Rekognition\Exception\RekognitionException;
use Aws\Rekognition\RekognitionClient;
use Illuminate\Support\Facades\Log;

/**
 * Image moderation via AWS Rekognition DetectModerationLabels.
 *
 * ──────────────────────────────────────────────────────────────────────────
 *  How it decides
 * ──────────────────────────────────────────────────────────────────────────
 *   Rekognition returns a tree of moderation labels with a 0-100 confidence
 *   score each. We keep the max score across labels that fall in any enabled
 *   category, then compare to two thresholds:
 *
 *     score ≥ auto_reject_threshold    → REJECTED   (hide from public, notify uploader)
 *     score ≥ flag_threshold           → FLAGGED    (stays visible to admin, queued for review)
 *     score <  flag_threshold          → APPROVED   (no action needed)
 *
 *   Both thresholds and the list of "enabled categories" are admin-configurable
 *   — a wedding site might block everything; a beach-photography site might
 *   allow "Suggestive > Female Swimwear Or Underwear" through.
 *
 * ──────────────────────────────────────────────────────────────────────────
 *  Fail-safe design
 * ──────────────────────────────────────────────────────────────────────────
 *   • Unconfigured AWS → returns a "skipped" decision, never throws.
 *   • AWS API error / network outage → logs warning, returns "skipped".
 *     Never rejects a photo on infrastructure failure — a caller treats the
 *     decision as neutral and can retry later via the remoderate command.
 *   • Empty image bytes → returns "skipped" with a reason code.
 */
class ImageModerationService
{
    private ?RekognitionClient $client = null;

    /* ────────── Decision constants ────────── */
    public const DECISION_APPROVED = 'approved';
    public const DECISION_FLAGGED  = 'flagged';
    public const DECISION_REJECTED = 'rejected';
    public const DECISION_SKIPPED  = 'skipped';

    /* Default thresholds (admin can override via AppSetting) */
    public const DEFAULT_FLAG_THRESHOLD   = 50.0;
    public const DEFAULT_REJECT_THRESHOLD = 90.0;

    /**
     * Top-level Rekognition categories we monitor by default. The admin can
     * whitelist / blacklist via `moderation_categories` app-setting JSON.
     *
     * The names match Rekognition's top-level ParentName values.
     */
    public const DEFAULT_CATEGORIES = [
        'Explicit Nudity',
        'Suggestive',
        'Violence',
        'Visually Disturbing',
        'Drugs',
        'Tobacco',
        'Alcohol',
        'Gambling',
        'Hate Symbols',
        'Rude Gestures',
    ];

    /* ────────── Public API ────────── */

    public function isConfigured(): bool
    {
        $key    = AppSetting::get('aws_key', config('services.aws.key', ''));
        $secret = AppSetting::get('aws_secret', config('services.aws.secret', ''));
        return !empty($key) && !empty($secret);
    }

    public function isEnabled(): bool
    {
        return AppSetting::get('moderation_enabled', '1') === '1';
    }

    /**
     * Core entry point — scan raw image bytes and return a structured decision.
     *
     * @return array{
     *   decision: string,              // approved|flagged|rejected|skipped
     *   score: float,                  // max confidence of matched labels (0-100)
     *   labels: array<int,array>,      // raw AWS label rows
     *   matched_categories: string[],  // top-level categories triggered
     *   reason: ?string,               // short machine-readable code
     *   skipped_reason: ?string        // why skipped (if decision=skipped)
     * }
     */
    public function moderate(string $imageBytes): array
    {
        if (!$this->isEnabled()) {
            return $this->skipped('moderation_disabled');
        }

        if (empty($imageBytes)) {
            return $this->skipped('empty_image');
        }

        if (!$this->isConfigured()) {
            Log::info('ImageModerationService: AWS not configured, skipping');
            return $this->skipped('aws_not_configured');
        }

        try {
            $minConfidence = $this->floatSetting('moderation_min_confidence', 40.0);

            $result = $this->getClient()->detectModerationLabels([
                'Image'         => ['Bytes' => $imageBytes],
                'MinConfidence' => $minConfidence,
            ]);

            $labels = $result->get('ModerationLabels') ?? [];

            return $this->decide($labels);
        } catch (RekognitionException $e) {
            // Throttling / invalid image / service unavailable — log and skip.
            Log::warning('ImageModerationService Rekognition error: ' . $e->getAwsErrorMessage(), [
                'aws_error_code' => $e->getAwsErrorCode(),
            ]);
            return $this->skipped('aws_error:' . $e->getAwsErrorCode());
        } catch (\Throwable $e) {
            Log::warning('ImageModerationService unexpected error: ' . $e->getMessage());
            return $this->skipped('exception');
        }
    }

    /**
     * Convert an AWS label set into a decision. Public for re-use by the
     * remoderate command on stored `moderation_labels` JSON (no re-scan).
     */
    public function decide(array $labels): array
    {
        $enabledCategories = $this->enabledCategories();
        $flagThreshold     = $this->floatSetting('moderation_flag_threshold',   self::DEFAULT_FLAG_THRESHOLD);
        $rejectThreshold   = $this->floatSetting('moderation_auto_reject_threshold', self::DEFAULT_REJECT_THRESHOLD);

        // Pair each label to its top-level category. Rekognition labels have:
        //   top-level: { Name: "Suggestive", ParentName: "" }
        //   nested:    { Name: "Female Swimwear Or Underwear", ParentName: "Suggestive" }
        // We normalise: a row's "effective category" is ParentName if present,
        // else Name (for top-level rows).
        $maxScore          = 0.0;
        $matchedCategories = [];

        foreach ($labels as $label) {
            $name        = $label['Name']        ?? '';
            $parentName  = $label['ParentName']  ?? '';
            $confidence  = (float) ($label['Confidence'] ?? 0);

            $effectiveCategory = $parentName !== '' ? $parentName : $name;

            // Skip labels in categories the admin has whitelisted.
            if (!in_array($effectiveCategory, $enabledCategories, true)) {
                continue;
            }

            if ($confidence > $maxScore) {
                $maxScore = $confidence;
            }

            if ($confidence >= $flagThreshold && !in_array($effectiveCategory, $matchedCategories, true)) {
                $matchedCategories[] = $effectiveCategory;
            }
        }

        if ($maxScore >= $rejectThreshold) {
            $decision = self::DECISION_REJECTED;
            $reason   = 'auto_reject';
        } elseif ($maxScore >= $flagThreshold) {
            $decision = self::DECISION_FLAGGED;
            $reason   = 'needs_review';
        } else {
            $decision = self::DECISION_APPROVED;
            $reason   = 'clean';
        }

        return [
            'decision'           => $decision,
            'score'              => round($maxScore, 2),
            'labels'             => $labels,
            'matched_categories' => $matchedCategories,
            'reason'             => $reason,
            'skipped_reason'     => null,
        ];
    }

    /* ────────── Configuration helpers ────────── */

    /**
     * List of enabled top-level categories the admin wants to scan for.
     * Stored as JSON in `moderation_categories`, defaults to ALL.
     */
    public function enabledCategories(): array
    {
        $raw = AppSetting::get('moderation_categories', null);
        if (empty($raw)) {
            return self::DEFAULT_CATEGORIES;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded)) {
            return self::DEFAULT_CATEGORIES;
        }
        return array_values(array_intersect(self::DEFAULT_CATEGORIES, $decoded));
    }

    /* ────────── Internals ────────── */

    private function getClient(): RekognitionClient
    {
        if ($this->client) {
            return $this->client;
        }

        $this->client = new RekognitionClient([
            'version'     => 'latest',
            'region'      => AppSetting::get('aws_region', config('services.aws.region', 'ap-southeast-1')),
            'credentials' => [
                'key'    => AppSetting::get('aws_key', config('services.aws.key', '')),
                'secret' => AppSetting::get('aws_secret', config('services.aws.secret', '')),
            ],
        ]);

        return $this->client;
    }

    private function floatSetting(string $key, float $default): float
    {
        $val = AppSetting::get($key, null);
        if ($val === null || $val === '') {
            return $default;
        }
        return (float) $val;
    }

    private function skipped(string $reason): array
    {
        return [
            'decision'           => self::DECISION_SKIPPED,
            'score'              => 0.0,
            'labels'             => [],
            'matched_categories' => [],
            'reason'             => null,
            'skipped_reason'     => $reason,
        ];
    }
}
