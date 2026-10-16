<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Old bright colour => calmer equivalent of the same hue (new "Harbor" look). */
    protected const PROJECTS = [
        '#f06a5a' => '#b4685c', '#fd9a00' => '#b07d3e', '#eec300' => '#9c8a3c', '#62d26f' => '#5e8c5f',
        '#37c5ab' => '#3f8a80', '#20aaea' => '#4a85a8', '#4573d2' => '#4e6a9e', '#8d84e8' => '#7570a8',
        '#aa62e3' => '#80659e', '#e362e3' => '#9a6390', '#ea4e9d' => '#a55e7b', '#6d6e6f' => '#6e7378',
        '#e8384f' => '#a9555e',
    ];

    protected const AVATARS = [
        '#f06a5a' => '#b4685c', '#4573d2' => '#4e6a9e', '#5da283' => '#5e8c6e', '#aa62e3' => '#80659e',
        '#e8384f' => '#a9555e', '#fd9a00' => '#b07d3e', '#37c5ab' => '#3f8a80', '#8d84e8' => '#7570a8',
        '#e362e3' => '#9a6390', '#62d26f' => '#5e8c5f',
    ];

    public function up(): void
    {
        foreach (self::PROJECTS as $old => $new) {
            DB::table('projects')->whereRaw('lower(color) = ?', [$old])->update(['color' => $new]);
        }
        foreach (self::AVATARS as $old => $new) {
            DB::table('users')->whereRaw('lower(avatar_color) = ?', [$old])->update(['avatar_color' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::PROJECTS as $old => $new) {
            DB::table('projects')->where('color', $new)->update(['color' => $old]);
        }
        foreach (self::AVATARS as $old => $new) {
            DB::table('users')->where('avatar_color', $new)->update(['avatar_color' => $old]);
        }
    }
};
