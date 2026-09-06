<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebAnalyticsPageView extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'visitor_hash',
        'session_hash',
        'viewed_on',
        'page_type',
        'content_type',
        'content_identifier',
        'content_slug',
        'path',
        'title',
        'referrer_host',
        'traffic_source',
        'traffic_channel',
        'campaign',
        'device_type',
        'browser_family',
        'viewport_width',
        'started_at',
        'last_seen_at',
        'engaged_seconds',
        'max_scroll_depth',
        'interaction_count',
        'is_engaged',
    ];

    protected $casts = [
        'viewed_on' => 'date',
        'viewport_width' => 'integer',
        'started_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'engaged_seconds' => 'integer',
        'max_scroll_depth' => 'integer',
        'interaction_count' => 'integer',
        'is_engaged' => 'boolean',
    ];
}
