<?php

namespace Tests\Unit;

use App\Services\Panel\MariadbPanelImporter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MariadbPanelImporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.legacy_panel' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        DB::purge('legacy_panel');

        Schema::connection('legacy_panel')->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::connection('legacy_panel')->create('settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::connection('legacy_panel')->table('users')->insert([
            'id' => 1,
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'hash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('legacy_panel')->table('settings')->insert([
            'key' => 'spaces.region',
            'value' => 'fra1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_copies_shared_tables_into_sqlite_target(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $importer = new MariadbPanelImporter();
        $result = $importer->import(
            DB::connection('legacy_panel'),
            DB::connection(),
            dryRun: false,
        );

        $this->assertSame(1, $result['tables']['users']);
        $this->assertSame(1, $result['tables']['settings']);
        $this->assertDatabaseHas('users', ['email' => 'admin@example.test']);
        $this->assertDatabaseHas('settings', ['key' => 'spaces.region', 'value' => 'fra1']);
    }

    public function test_dry_run_does_not_copy_rows(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        $importer = new MariadbPanelImporter();
        $result = $importer->import(
            DB::connection('legacy_panel'),
            DB::connection(),
            dryRun: true,
        );

        $this->assertSame(1, $result['tables']['users']);
        $this->assertDatabaseCount('users', 0);
    }
}
