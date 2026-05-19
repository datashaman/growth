<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Server-side MCP session state (#314, ADR-0002). The MCP HTTP transport
     * is stateless, so to let a session adopt a project Role mid-session and
     * have the binding hold across calls, Growth keeps its own store keyed by
     * the transport session id and the authenticated user. A row is written
     * lazily on the first `adopt-role`; unbound sessions never touch the table.
     */
    public function up(): void
    {
        Schema::create('mcp_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('mcp_session_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->timestamps();

            $table->unique(['mcp_session_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_sessions');
    }
};
