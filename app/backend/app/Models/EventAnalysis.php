<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'slack_message_id',
        'scheduled_event_id',
        'analysis_type',
        'extracted_data',
        'confidence_score',
        'analysis_status',
        'event_start_datetime',
        'event_end_datetime',
        'event_title',
        'event_type'
    ];

    protected $casts = [
        'extracted_data' => 'array',
        'confidence_score' => 'float',
        'event_start_datetime' => 'datetime',
        'event_end_datetime' => 'datetime'
    ];

    /**
     * Slackメッセージとの関連
     */
    public function slackMessage(): BelongsTo
    {
        return $this->belongsTo(SlackMessage::class, 'slack_message_id');
    }

    /**
     * 予定との関連
     */
    public function scheduledEvent(): BelongsTo
    {
        return $this->belongsTo(ScheduledEvent::class);
    }
}
