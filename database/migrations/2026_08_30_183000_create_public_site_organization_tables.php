<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_organization_roles')) {
            Schema::create('site_organization_roles', function (Blueprint $table): void {
                $table->id();
                $table->string('organization_type', 30);
                $table->string('name', 120);
                $table->string('section', 30)->default('leadership');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['organization_type', 'name'], 'site_org_roles_type_name_unique');
                $table->index(
                    ['organization_type', 'active', 'sort_order'],
                    'site_org_roles_catalog_idx',
                );
            });
        }

        if (! Schema::hasTable('site_organizations')) {
            Schema::create('site_organizations', function (Blueprint $table): void {
                $table->id();
                $table->string('type', 30);
                $table->unsignedInteger('year');
                $table->string('name', 180);
                $table->text('summary')->nullable();
                $table->string('status', 30)->default('draft');
                $table->boolean('active')->default(true);
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['type', 'year'], 'site_org_type_year_unique');
                $table->index(['type', 'status', 'published_at'], 'site_org_publication_idx');
                $table->index(['type', 'active', 'year'], 'site_org_active_year_idx');
            });
        }

        if (! Schema::hasTable('site_organization_members')) {
            Schema::create('site_organization_members', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_organization_id')
                    ->constrained('site_organizations')
                    ->cascadeOnDelete();
                $table->foreignId('role_id')
                    ->nullable()
                    ->constrained('site_organization_roles')
                    ->nullOnDelete();
                $table->string('member_kind', 30);
                $table->foreignId('student_profile_id')
                    ->nullable()
                    ->constrained('student_profiles')
                    ->restrictOnDelete();
                $table->foreignId('staff_id')->nullable()->constrained('staff')->restrictOnDelete();
                $table->string('display_name_snapshot', 180);
                $table->string('detail_snapshot', 180)->nullable();
                $table->string('section', 30)->default('leadership');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('public_name_authorized')->default(false);
                $table->timestamp('public_name_authorized_at')->nullable();
                $table->foreignId('public_name_authorized_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestamps();

                $table->unique(
                    ['site_organization_id', 'student_profile_id'],
                    'site_org_members_student_unique',
                );
                $table->unique(
                    ['site_organization_id', 'staff_id'],
                    'site_org_members_staff_unique',
                );
                $table->index(
                    ['site_organization_id', 'public_name_authorized', 'sort_order'],
                    'site_org_members_public_idx',
                );
            });
        }

        if (Schema::hasTable('prevent_joint_committees')) {
            Schema::table('prevent_joint_committees', function (Blueprint $table): void {
                if (! Schema::hasColumn('prevent_joint_committees', 'web_summary')) {
                    $table->text('web_summary')->nullable()->after('notes');
                }
                if (! Schema::hasColumn('prevent_joint_committees', 'web_status')) {
                    $table->string('web_status', 30)->default('draft')->after('web_summary');
                }
                if (! Schema::hasColumn('prevent_joint_committees', 'web_published_at')) {
                    $table->timestamp('web_published_at')->nullable()->after('web_status');
                }
            });

            if (! $this->indexExists('prevent_joint_committees', 'prevent_joint_committee_web_idx')) {
                Schema::table('prevent_joint_committees', function (Blueprint $table): void {
                    $table->index(
                        ['web_status', 'web_published_at', 'starts_on'],
                        'prevent_joint_committee_web_idx',
                    );
                });
            }
        }

        if (Schema::hasTable('prevent_joint_committee_staff')) {
            Schema::table('prevent_joint_committee_staff', function (Blueprint $table): void {
                if (! Schema::hasColumn('prevent_joint_committee_staff', 'section')) {
                    $table->string('section', 30)->default('leadership')->after('position_name');
                }
                if (! Schema::hasColumn('prevent_joint_committee_staff', 'sort_order')) {
                    $table->unsignedInteger('sort_order')->default(0)->after('section');
                }
                if (! Schema::hasColumn('prevent_joint_committee_staff', 'public_name_authorized')) {
                    $table->boolean('public_name_authorized')->default(false)->after('active');
                }
                if (! Schema::hasColumn('prevent_joint_committee_staff', 'public_name_authorized_at')) {
                    $table->timestamp('public_name_authorized_at')
                        ->nullable()
                        ->after('public_name_authorized');
                }
                if (! Schema::hasColumn('prevent_joint_committee_staff', 'public_name_authorized_by')) {
                    $table->foreignId('public_name_authorized_by')
                        ->nullable()
                        ->after('public_name_authorized_at')
                        ->constrained('users')
                        ->nullOnDelete();
                }
            });

            if (! $this->indexExists(
                'prevent_joint_committee_staff',
                'prevent_joint_committee_public_members_idx',
            )) {
                Schema::table('prevent_joint_committee_staff', function (Blueprint $table): void {
                    $table->index(
                        ['committee_id', 'public_name_authorized', 'sort_order'],
                        'prevent_joint_committee_public_members_idx',
                    );
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_organization_members');
        Schema::dropIfExists('site_organizations');
        Schema::dropIfExists('site_organization_roles');

        // The web metadata added to the official Prevention tables is retained.
        // A rollback must never remove pre-existing committee history or consent audit data.
    }

    private function indexExists(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }
};
