<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlackMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'user',
        'text',
        'timestamp',
        'slack_ts', // Slack のメッセージ ID
        'analyzed_at',
        'is_analyzed'
    ];

    protected $casts = [
        'timestamp' => 'datetime:Y-m-d H:i:s',
        'analyzed_at' => 'datetime',
        'is_analyzed' => 'boolean',
    ];

    /**
     * 分析結果との関連
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(EventAnalysis::class);
    }
}