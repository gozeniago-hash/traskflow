<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Key resources are now shared with the client portal by default. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('project_resources')->update(['client_visible' => true]);
        $ids = DB::table('project_resources')->whereNotNull('attachment_id')->pluck('attachment_id');
        if ($ids->isNotEmpty()) {
            DB::table('attachments')->whereIn('id', $ids)->update(['client_visible' => true]);
        }
    }

    public function down(): void
    {
    }
};
