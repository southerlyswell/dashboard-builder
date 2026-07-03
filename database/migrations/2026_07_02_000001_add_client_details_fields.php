<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('contact_name')->nullable()->after('name');
            $table->string('contact_email')->nullable()->after('contact_name');
            $table->string('contact_phone')->nullable()->after('contact_email');
            $table->string('address_line1')->nullable()->after('contact_phone');
            $table->string('address_line2')->nullable()->after('address_line1');
            $table->string('city')->nullable()->after('address_line2');
            $table->string('province')->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('province');
            $table->string('industry')->nullable()->after('postal_code');
            $table->string('logo')->nullable()->after('industry');
            $table->boolean('is_active')->default(true)->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'contact_name', 'contact_email', 'contact_phone',
                'address_line1', 'address_line2', 'city', 'province',
                'postal_code', 'industry', 'logo', 'is_active'
            ]);
        });
    }
};
