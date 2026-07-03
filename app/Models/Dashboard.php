<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Dashboard extends Model
{
    protected $fillable = [
        'client_id', 'name', 'slug', 'public_id',
        'layout', 'theme', 'is_published',
    ];

    protected $casts = [
        'layout' => 'array',
        'is_published' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($dashboard) {
            $dashboard->public_id = $dashboard->public_id ?? Str::uuid();
            $dashboard->slug = $dashboard->slug ?? Str::slug($dashboard->name);
        });
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function embedHtml(): string
    {
        $url = route('embed.public', $this->public_id);
        return <<<HTML
&lt;iframe src="{$url}"
  width="100%" height="900" frameborder="0"
  style="border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.1);"&gt;
&lt;/iframe&gt;
HTML;
    }
}
