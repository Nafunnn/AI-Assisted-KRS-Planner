<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ai_provider_configs')
            ->where('provider', 'openai')
            ->update(['provider' => '9router']);
    }

    public function down(): void
    {
        // Cannot reliably restore which configs were originally openai.
    }
};
