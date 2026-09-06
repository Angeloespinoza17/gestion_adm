<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'messages' => [
            'msg_conversation_updated_id_idx' => ['conversation_id', 'updated_at', 'id'],
        ],
        'message_recipients' => [
            'msg_recipient_unread_message_idx' => ['user_id', 'read_at', 'message_id'],
            'msg_recipient_user_ack_state_idx' => ['user_id', 'acknowledgement_required', 'acknowledged_at', 'waived_at', 'message_id'],
            'msg_recipient_reminder_state_idx' => ['message_id', 'acknowledgement_required', 'acknowledged_at', 'waived_at', 'last_reminded_at'],
        ],
        'conversations' => [
            'conversation_last_message_id_idx' => ['last_message_at', 'id'],
        ],
    ];

    public function up(): void
    {
        $pretending = DB::connection()->pretending();

        foreach (self::INDEXES as $table => $indexes) {
            if (! $pretending && ! Schema::hasTable($table)) {
                continue;
            }

            $missing = [];
            foreach ($indexes as $name => $columns) {
                if ($pretending || ! Schema::hasIndex($table, $name)) {
                    $missing[$name] = $columns;
                }
            }

            if ($missing !== []) {
                Schema::table($table, function (Blueprint $blueprint) use ($missing): void {
                    foreach ($missing as $name => $columns) {
                        $blueprint->index($columns, $name);
                    }
                });
            }
        }
    }

    public function down(): void
    {
        $pretending = DB::connection()->pretending();

        foreach (array_reverse(self::INDEXES, true) as $table => $indexes) {
            if (! $pretending && ! Schema::hasTable($table)) {
                continue;
            }

            $existing = [];
            foreach (array_reverse($indexes, true) as $name => $columns) {
                if ($pretending || Schema::hasIndex($table, $name)) {
                    $existing[] = $name;
                }
            }

            if ($existing !== []) {
                Schema::table($table, function (Blueprint $blueprint) use ($existing): void {
                    foreach ($existing as $name) {
                        $blueprint->dropIndex($name);
                    }
                });
            }
        }
    }
};
