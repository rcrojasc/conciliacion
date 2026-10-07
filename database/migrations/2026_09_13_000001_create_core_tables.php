<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void {
Schema::create('organizations', function(Blueprint $t){$t->ulid('id')->primary();$t->string('name');$t->string('tax_id',32)->nullable()->unique();$t->string('status',24)->default('active');$t->char('base_currency',3)->default('CLP');$t->string('timezone')->default('America/Santiago');$t->timestamps();});
Schema::create('roles', function(Blueprint $t){$t->ulid('id')->primary();$t->string('code')->unique();$t->string('name');$t->timestamps();});
Schema::create('permissions', function(Blueprint $t){$t->ulid('id')->primary();$t->string('code')->unique();$t->string('name');$t->timestamps();});
Schema::create('organization_user', function(Blueprint $t){$t->ulid('id')->primary();$t->foreignUlid('organization_id')->constrained()->cascadeOnDelete();$t->foreignUlid('user_id')->constrained()->cascadeOnDelete();$t->foreignUlid('role_id')->nullable()->constrained()->nullOnDelete();$t->string('status',24)->default('active');$t->timestamps();$t->unique(['organization_id','user_id']);});
Schema::create('permission_role', function(Blueprint $t){$t->foreignUlid('role_id')->constrained()->cascadeOnDelete();$t->foreignUlid('permission_id')->constrained()->cascadeOnDelete();$t->primary(['role_id','permission_id']);});
}  };
