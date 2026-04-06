<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'whatsapp_notifications_enabled')) {
                $table->boolean('whatsapp_notifications_enabled')->default(false)->after('sms_notification_preferences');
            }

            if (! Schema::hasColumn('users', 'whatsapp_notification_preferences')) {
                $table->json('whatsapp_notification_preferences')->nullable()->after('whatsapp_notifications_enabled');
            }

            if (! Schema::hasColumn('users', 'email_notifications_enabled')) {
                $table->boolean('email_notifications_enabled')->default(true)->after('whatsapp_notification_preferences');
            }

            if (! Schema::hasColumn('users', 'email_notification_preferences')) {
                $table->json('email_notification_preferences')->nullable()->after('email_notifications_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'email_notification_preferences',
                'email_notifications_enabled',
                'whatsapp_notification_preferences',
                'whatsapp_notifications_enabled',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
