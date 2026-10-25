<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\ProductCompletionPayloadCast;

use App\Enums\ProductCompletionSubmissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

class ProductCompletionSubmission extends Model
{
    use HasFactory;

    private const IMMUTABLE_FIELDS = [
        'task_id',
        'submitted_by',
        'version_no',
        'draft_version',
        'payload_json',
        'diff_json',
        'media_manifest_json',
        'payload_hash',
        'base_product_version',
        'idempotency_key',
        'submitted_at',
        'submission_context_json',
    ];

    protected $fillable = [
        'task_id',
        'submitted_by',
        'version_no',
        'draft_version',
        'payload_json',
        'diff_json',
        'media_manifest_json',
        'payload_hash',
        'base_product_version',
        'idempotency_key',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'approval_selection_json',
        'submitted_at',
        'submission_context_json',
        'review_started_by',
        'review_started_at',
        'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'draft_version' => 'integer',
            'payload_json' => ProductCompletionPayloadCast::class,
            'diff_json' => ProductCompletionPayloadCast::class,
            'media_manifest_json' => ProductCompletionPayloadCast::class,
            'base_product_version' => 'integer',
            'status' => ProductCompletionSubmissionStatus::class,
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'submission_context_json' => 'array',
            'review_started_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'approval_selection_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $submission): void {
            $submission->public_id ??= (string) Str::ulid();
        });

        static::updating(function (self $submission): void {
            foreach (self::IMMUTABLE_FIELDS as $field) {
                if ($submission->isDirty($field)) {
                    throw new LogicException("Submission field [{$field}] is immutable.");
                }
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function publicationPayload(): array
    {
        return $this->reviewPayload('reviewed_payload');
    }

    public function pricingPayload(): array
    {
        return $this->reviewPayload('pricing_payload');
    }

    public static function signReviewSnapshot(array $snapshot): array
    {
        $key = config('app.key');
        if (! is_string($key) || trim($key) === '') {
            throw ValidationException::withMessages(['review' => 'کلید معتبر برنامه برای تأیید صحت بازبینی تنظیم نشده است.']);
        }
        unset($snapshot['signature']);
        $snapshot['signature'] = self::reviewSignature($snapshot, $key);

        return $snapshot;
    }

    private static function reviewSignature(array $snapshot, string $key): string
    {
        unset($snapshot['signature']);
        try {
            $canonical = json_encode(self::canonicalReviewValue($snapshot), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES, 64);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['review' => 'اطلاعات بازبینی برای امضای صحت معتبر نیست.']);
        }
        return hash_hmac('sha256', $canonical, $key);
    }

    private static function canonicalReviewValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 32) {
            throw ValidationException::withMessages(['review' => 'ساختار اطلاعات بازبینی بیش از حد تو در تو است.']);
        }
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $nested) {
            $value[$key] = self::canonicalReviewValue($nested, $depth + 1);
        }

        return $value;
    }

    private function reviewPayload(string $key): array
    {
        $snapshot = $this->approval_selection_json;
        if ($snapshot !== null && ! is_array($snapshot)) {
            throw ValidationException::withMessages(['review' => 'اطلاعات ذخیره‌شدهٔ بازبینی معتبر نیست.']);
        }
        if ($snapshot === null || (! array_key_exists('schema_version', $snapshot)
            && ! array_key_exists('reviewed_payload', $snapshot)
            && ! array_key_exists('editor_payload', $snapshot)
            && ! array_key_exists('pricing_payload', $snapshot)
            && ! array_key_exists('media_items', $snapshot)
            && ! array_key_exists('signature', $snapshot)
            && ($snapshot['mode'] ?? null) !== 'item_review')) {
            return $this->payload_json ?? [];
        }

        $invalid = static function (): never {
            throw ValidationException::withMessages(['review' => 'اطلاعات ذخیره‌شدهٔ بازبینی معتبر نیست. بازبینی باید دوباره انجام شود.']);
        };
        if (! is_string($snapshot['signature'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $snapshot['signature']) !== 1) {
            $invalid();
        }
        $previousKeys = config('app.previous_keys', []);
        $keys = [config('app.key'), ...(is_array($previousKeys) ? $previousKeys : [])];
        $verified = false;
        foreach ($keys as $signingKey) {
            if (is_string($signingKey) && trim($signingKey) !== '' && hash_equals(self::reviewSignature($snapshot, $signingKey), $snapshot['signature'])) {
                $verified = true;
                break;
            }
        }
        if (! $verified) {
            $invalid();
        }
        if (($snapshot['schema_version'] ?? null) !== 2 || ($snapshot['mode'] ?? null) !== 'item_review'
            || ! is_array($snapshot['decisions'] ?? null) || ! is_array($snapshot['media_items'] ?? null)
            || ! array_is_list($snapshot['media_items'])
            || ! is_int($snapshot['reviewer_id'] ?? null) || $snapshot['reviewer_id'] < 1
            || ! is_string($snapshot['source_payload_hash'] ?? null)
            || ! hash_equals((string) $this->payload_hash, $snapshot['source_payload_hash'])
            || ! is_int($snapshot['source_task_id'] ?? null) || $snapshot['source_task_id'] !== $this->task_id
            || ! is_int($snapshot['source_submission_id'] ?? null) || $snapshot['source_submission_id'] !== $this->getKey()
            || ! is_int($snapshot['product_version'] ?? null) || $snapshot['product_version'] < 0
            || ! is_int($snapshot['source_lock_version'] ?? null) || $snapshot['source_lock_version'] < 0
            || ! is_string($snapshot['reviewed_at'] ?? null)
            || Validator::make(['reviewed_at' => $snapshot['reviewed_at']], ['reviewed_at' => ['required', 'date']])->fails()) {
            $invalid();
        }

        foreach (['editor_payload', 'reviewed_payload', 'pricing_payload'] as $payloadKey) {
            $payload = $snapshot[$payloadKey] ?? null;
            if (! is_array($payload) || ($payload['schema_version'] ?? null) !== ($this->payload_json['schema_version'] ?? null)
                || ! is_int($payload['schema_version'] ?? null)) {
                $invalid();
            }
            if (Validator::make($payload, [
                'schema_version' => ['required', 'integer'],
                'title' => ['sometimes', 'nullable', 'string'],
                'short_description' => ['sometimes', 'nullable', 'string'],
                'full_description' => ['sometimes', 'nullable', 'string'],
                'attributes' => ['sometimes', 'array'],
                'attributes.*' => ['array:attribute_id,value_ids,proposed_values'],
                'attributes.*.attribute_id' => ['required', 'integer', 'min:1', 'distinct'],
                'attributes.*.value_ids' => ['present', 'array'],
                'attributes.*.value_ids.*' => ['integer', 'min:1'],
                'attributes.*.proposed_values' => ['sometimes', 'array'],
                'attributes.*.proposed_values.*' => ['string'],
                'gallery' => ['sometimes', 'array:items'],
                'gallery.items' => ['sometimes', 'array'],
                'gallery.items.*' => ['array:media_public_id,product_gallery_id,action,sort_order,is_primary,title,alt_text'],
                'gallery.items.*.action' => ['required', 'in:add,keep,remove'],
            ])->fails() || (array_key_exists('gallery', $payload) && ! is_array($payload['gallery']['items'] ?? null))
                || array_diff(array_keys($payload), ['schema_version', 'title', 'short_description', 'full_description', 'attributes', 'gallery']) !== []) {
                $invalid();
            }
        }

        foreach ($snapshot['decisions'] as $identity => $decision) {
            if (! is_string($identity) || preg_match('/^(text:(title|short_description|full_description)|(attribute|media):[1-9][0-9]*)$/D', $identity) !== 1
                || ! in_array($decision, ['approve', 'reject'], true)) {
                $invalid();
            }
        }
        $seen = [];
        $orders = [];
        $primaryCount = 0;
        foreach ($snapshot['media_items'] as $item) {
            if (! is_array($item) || array_diff(array_keys($item), ['id', 'action', 'sort_order', 'is_primary', 'is_seo_image', 'title', 'alt_text']) !== []
                || ! is_int($item['id'] ?? null) || $item['id'] < 1 || isset($seen[$item['id']])
                || ! in_array($item['action'] ?? null, ['add', 'keep', 'remove'], true)
                || ! is_bool($item['is_primary'] ?? null) || ! is_bool($item['is_seo_image'] ?? null)
                || ! array_key_exists('sort_order', $item) || ($item['sort_order'] !== null && ! is_int($item['sort_order']))
                || ! array_key_exists('title', $item) || ($item['title'] !== null && ! is_string($item['title']))
                || ! array_key_exists('alt_text', $item) || ($item['alt_text'] !== null && ! is_string($item['alt_text']))) {
                $invalid();
            }
            if ($item['action'] === 'remove') {
                if ($item['sort_order'] !== null || $item['is_primary']) {
                    $invalid();
                }
            } else {
                if (! is_int($item['sort_order']) || $item['sort_order'] < 1) {
                    $invalid();
                }
                $orders[] = $item['sort_order'];
                $primaryCount += (int) $item['is_primary'];
            }
            $seen[$item['id']] = true;
        }
        sort($orders);
        if (($orders !== [] && ($orders !== range(1, count($orders)) || $primaryCount !== 1))
            || ($snapshot['media_items'] !== [] && $orders === [])
            || count($snapshot['reviewed_payload']['gallery']['items'] ?? []) !== count($snapshot['media_items'])) {
            $invalid();
        }

        return $snapshot[$key];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProductCompletionTask::class, 'task_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function requestedRevisions(): HasMany
    {
        return $this->hasMany(ProductCompletionRevision::class, 'submission_id');
    }

    public function resolvedRevisions(): HasMany
    {
        return $this->hasMany(ProductCompletionRevision::class, 'resolved_by_submission_id');
    }

    public function mediaItems(): HasMany
    {
        return $this->hasMany(ProductCompletionSubmissionMedia::class, 'submission_id');
    }
}
