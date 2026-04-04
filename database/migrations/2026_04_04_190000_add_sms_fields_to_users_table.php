<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone_e164')) {
                $table->string('phone_e164', 20)->nullable()->after('phone');
            }

            if (! Schema::hasColumn('users', 'sms_notifications_enabled')) {
                $table->boolean('sms_notifications_enabled')->default(true)->after('phone_e164');
            }

            if (! Schema::hasColumn('users', 'sms_notification_preferences')) {
                $table->json('sms_notification_preferences')->nullable()->after('sms_notifications_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['sms_notification_preferences', 'sms_notifications_enabled', 'phone_e164'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
