<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Client extends Model
{
    protected $fillable = [
        'name', 'contact_name', 'contact_email', 'contact_phone',
        'address_line1', 'address_line2', 'city', 'province', 'postal_code',
        'industry', 'logo', 'is_active',
        'db_host', 'db_port', 'db_database',
        'db_username', 'db_password', 'schema_snapshot',
    ];

    protected $casts = [
        'db_port' => 'integer',
        'schema_snapshot' => 'array',
        'is_active' => 'boolean',
    ];

    public function dashboards()
    {
        return $this->hasMany(Dashboard::class);
    }

    public function fullAddress(): string
    {
        $parts = array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->province,
            $this->postal_code,
        ]);
        return implode(', ', $parts);
    }

    public function dashboardCount(): int
    {
        return $this->dashboards()->count();
    }
}
