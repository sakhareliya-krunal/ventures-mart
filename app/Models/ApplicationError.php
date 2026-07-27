<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApplicationError extends Model
{
    public const STATUSES = ['new', 'investigating', 'resolved', 'ignored'];

    public const CATEGORIES = ['exception', 'http', 'job', 'payment', 'api', 'system'];

    protected $fillable = [
        'uuid',
        'fingerprint',
        'occurrence_count',
        'category',
        'status',
        'level',
        'message',
        'exception_class',
        'file',
        'line',
        'trace',
        'context',
        'url',
        'route',
        'method',
        'ip',
        'user_id',
        'user_agent',
        'request',
        'resolved_at',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'request' => 'array',
            'resolved_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'line' => 'integer',
            'occurrence_count' => 'integer',
            'user_id' => 'integer',
        ];
    }

    protected static function booting(): void
    {
        static::creating(function (ApplicationError $error): void {
            if (! $error->uuid) {
                $error->uuid = (string) Str::uuid();
            }

            if (! $error->first_seen_at) {
                $error->first_seen_at = now();
            }

            if (! $error->last_seen_at) {
                $error->last_seen_at = now();
            }

            if (! $error->status) {
                $error->status = 'new';
            }

            if (! $error->occurrence_count) {
                $error->occurrence_count = 1;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['new', 'investigating']);
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['resolved', 'ignored']);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', 'resolved');
    }

    public function markStatus(string $status): void
    {
        $this->status = $status;
        $this->resolved_at = $status === 'resolved' ? ($this->resolved_at ?: now()) : null;
        $this->save();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
