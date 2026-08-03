<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleWorkspace\Service;

use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleWorkspace\ModuleWorkspace;
use RuntimeException;

use function date;
use function is_array;

/**
 * HR: Sprema naslovnice aplikacije i osobne korisničke odabire u tablice
 * Workspace modula. Auth modul ne poznaje niti čita ove podatke.
 * EN: Stores application homepages and personal user selections in Workspace
 * module tables. The Auth module neither knows nor reads these data.
 */
final readonly class WorkspaceHomepageRepository
{
    private const SETTINGS_ROW_ID = 1;

    /**
     * HR: Prima ORM bazu za prenosiv rad na SQLiteu, PostgreSQL-u i MySQL-u.
     * EN: Receives the ORM database for portable SQLite, PostgreSQL, and MySQL operation.
     */
    public function __construct(private Database $database)
    {
    }

    /**
     * HR: Provjerava jesu li primijenjene tablice nadogradnje naslovnice.
     * EN: Checks whether the homepage upgrade tables have been applied.
     */
    public function tablesReady(): bool
    {
        $schema = $this->database->schema();

        return $schema->hasTable(ModuleWorkspace::TABLE_WORKSPACE_HOMEPAGE_SETTINGS)
        && $schema->hasTable(ModuleWorkspace::TABLE_WORKSPACE_USER_HOMEPAGES);
    }

    /**
     * HR: Vraća jedine globalne postavke ili sigurne zadane vrijednosti.
     * EN: Returns the single global settings row or safe defaults.
     *
     * @return array{public_node_id:int,authenticated_node_id:int,allow_user_selection:bool}
     */
    public function settings(): array
    {
        if (!$this->tablesReady()) {
            return $this->defaultSettings();
        }

        $row = $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_HOMEPAGE_SETTINGS)
            ->where('id', '=', self::SETTINGS_ROW_ID)
            ->first();
        if (!is_array($row)) {
            return $this->defaultSettings();
        }

        return [
            'public_node_id' => WorkspaceValue::int($row['public_node_id'] ?? 0),
            'authenticated_node_id' => WorkspaceValue::int($row['authenticated_node_id'] ?? 0),
            'allow_user_selection' => (bool)($row['allow_user_selection'] ?? true),
        ];
    }

    /**
     * HR: Sprema javnu, prijavljenu i korisničku politiku u jednom retku.
     * EN: Stores the public, authenticated, and user-selection policy in one row.
     */
    public function saveSettings(
        int $publicNodeId,
        int $authenticatedNodeId,
        bool $allowUserSelection,
        int $actorUserId,
    ): void {
        $this->assertTablesReady();
        $now = date('Y-m-d H:i:s');
        $payload = [
            'public_node_id' => $publicNodeId > 0 ? $publicNodeId : null,
            'authenticated_node_id' => $authenticatedNodeId > 0 ? $authenticatedNodeId : null,
            'allow_user_selection' => $allowUserSelection,
            'updated_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'updated_at' => $now,
        ];

        $existing = $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_HOMEPAGE_SETTINGS)
            ->where('id', '=', self::SETTINGS_ROW_ID)
            ->first();
        if (is_array($existing)) {
            $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_HOMEPAGE_SETTINGS)
                ->where('id', '=', self::SETTINGS_ROW_ID)
                ->update($payload);
            return;
        }

        $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_HOMEPAGE_SETTINGS)->insert([
            'id' => self::SETTINGS_ROW_ID,
            'created_at' => $now,
            ...$payload,
        ]);
    }

    /**
     * HR: Vraća ID osobne naslovnice ili nulu kada korisnik slijedi zadanu politiku.
     * EN: Returns the personal homepage ID or zero when the user follows the default policy.
     */
    public function userNodeId(int $userId): int
    {
        if (!$this->tablesReady() || $userId <= 0) {
            return 0;
        }

        $row = $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_USER_HOMEPAGES)
            ->where('user_id', '=', $userId)
            ->first();

        return is_array($row) ? WorkspaceValue::int($row['node_id'] ?? 0) : 0;
    }

    /**
     * HR: Sprema osobnu stranicu ili briše odabir kako bi korisnik naslijedio zadanu.
     * EN: Stores a personal page or removes the selection so the user inherits the default.
     */
    public function saveUserNodeId(int $userId, int $nodeId): void
    {
        $this->assertTablesReady();
        if ($userId <= 0) {
            throw new RuntimeException(__('Za osobnu naslovnicu potrebna je prijava.'));
        }

        $query = $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_USER_HOMEPAGES)
            ->where('user_id', '=', $userId);
        if ($nodeId <= 0) {
            $query->delete();
            return;
        }

        $now = date('Y-m-d H:i:s');
        $existing = $query->first();
        if (is_array($existing)) {
            $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_USER_HOMEPAGES)
                ->where('user_id', '=', $userId)
                ->update([
                    'node_id' => $nodeId,
                    'updated_at' => $now,
                ]);
            return;
        }

        $this->database->table(ModuleWorkspace::TABLE_WORKSPACE_USER_HOMEPAGES)->insert([
            'user_id' => $userId,
            'node_id' => $nodeId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * HR: Vraća sigurne vrijednosti prije prvog administratorskog spremanja.
     * EN: Returns safe values before the first administrator save.
     *
     * @return array{public_node_id:int,authenticated_node_id:int,allow_user_selection:bool}
     */
    private function defaultSettings(): array
    {
        return [
            'public_node_id' => 0,
            'authenticated_node_id' => 0,
            'allow_user_selection' => true,
        ];
    }

    /**
     * HR: Zaustavlja spremanje s jasnom porukom kada migracija nedostaje.
     * EN: Stops persistence with a clear message when the migration is missing.
     */
    private function assertTablesReady(): void
    {
        if (!$this->tablesReady()) {
            throw new RuntimeException(__('Migracija naslovnice područja nije primijenjena.'));
        }
    }
}
