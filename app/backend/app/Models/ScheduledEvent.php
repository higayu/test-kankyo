<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduledEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'slack_message_id',
        'event_type',
        'title',
        'description',
        'start_datetime',
        'end_datetime',
        'location',
        'participants',
        'status',
        'priority',
        'last_notified_at',
        'notification_history',
        'is_notification_enabled'
    ];

    protected $attributes = [
        'participants' => '[]',
        'notification_history' => '[]',
        'is_notification_enabled' => true,
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'participants' => 'array',
        'notification_history' => 'array',
        'is_notification_enabled' => 'boolean',
        'last_notified_at' => 'datetime'
    ];

    /**
     * Slackメッセージとの関連
     */
    public function slackMessage(): BelongsTo
    {
        return $this->belongsTo(SlackMessage::class, 'slack_message_id');
    }

    /**
     * 分析結果との関連
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(EventAnalysis::class);
    }

    /**
     * 参加者リストが有効かどうかを確認
     */
    public function hasValidParticipants(): bool
    {
        return !empty($this->participants) && is_array($this->participants);
    }
}
