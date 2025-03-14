<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use Illuminate\Support\Str;

class MigrateMongoDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-mongo-d-b';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $mysql;
    protected $mongo;
    protected $tableIdMaps = []; // { table_name: { old_id: new ObjectId } }
    protected $foreignKeys = []; // Store foreign key relationships

    public function __construct()
    {
        parent::__construct();
        $this->mongo = (new Client())->selectDatabase('dev_v1');
        $this->mysql = DB::connection('mysql');
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting MySQL to MongoDB migration with FK remap...');

        $tables = $this->getAllTableNames();

        // Step 1: Get Foreign Keys
        $this->loadForeignKeys();

        // Step 2: Migrate all tables and create ObjectId maps
        foreach ($tables as $table) {
            $this->migrateTable($table);
        }

        // Convert arrays to JSON (pretty-printed for readability)
        $jsonData = json_encode([
            'tableIdMaps' => $this->tableIdMaps,
            'foreignKeys' => $this->foreignKeys
        ], JSON_PRETTY_PRINT);

        // Save it to storage/app/migrations/mongo-migration-debug.json
        Storage::put('migrations/mongo-migration-debug.json', $jsonData);

        // Step 3: Remap foreign keys after migration
        foreach ($tables as $table) {
            $this->remapForeignKeys($table);
        }

        $this->info('✅ Migration completed successfully with FK remap!');
    }

    protected function getAllTableNames()
    {
        $result = $this->mysql->select('SHOW TABLES');
        $tables = [];

        foreach ($result as $row) {
            $tables[] = array_values((array) $row)[0];
        }

        $this->info('📂 Found tables: ' . implode(', ', $tables));

        return $tables;
    }

    protected function getPrimaryKey($table)
    {
        $primary = $this->mysql->select("
            SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND COLUMN_KEY = 'PRI'
        ", [$table]);

        return count($primary) ? $primary[0]->COLUMN_NAME : null;
    }

    protected function loadForeignKeys()
    {
        $this->info("🔎 Loading foreign keys...");

        // Step 1: Load actual foreign keys from MySQL schema FIRST
        $result = $this->mysql->select("
        SELECT
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE
            TABLE_SCHEMA = DATABASE()
            AND REFERENCED_TABLE_NAME IS NOT NULL
    ");

        // Build base foreign key map with actual FKs
        foreach ($result as $fk) {
            $this->foreignKeys[$fk->TABLE_NAME][$fk->COLUMN_NAME] = [
                'referenced_table' => $fk->REFERENCED_TABLE_NAME,
                'referenced_column' => $fk->REFERENCED_COLUMN_NAME
            ];
        }

        $this->info('✅ Actual foreign keys loaded from MySQL schema.');

        // Step 2: Get all tables for manual FK detection
        $tables = $this->getAllTableNames();

        foreach ($tables as $table) {
            $columns = $this->mysql->select("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
        ", [$table]);

            foreach ($columns as $columnObj) {
                $column = $columnObj->COLUMN_NAME;

                // --- Prioritize actual foreign keys ---
                // Skip if column already has a real FK loaded above
                if (isset($this->foreignKeys[$table][$column])) {
                    continue;
                }

                // Step 3: Add custom FK for created_by, updated_by, deleted_by → users.id
                if (in_array($column, ['created_by', 'updated_by', 'deleted_by'])) {
                    $this->foreignKeys[$table][$column] = [
                        'referenced_table' => 'users',
                        'referenced_column' => 'id'
                    ];
                    continue;
                }

                // Step 3: Add custom FK for brand_id → restaurants.id
                if (in_array($column, ['brand_id'])) {
                    $this->foreignKeys[$table][$column] = [
                        'referenced_table' => 'restaurants',
                        'referenced_column' => 'id'
                    ];
                    continue;
                }

                // Step 4: Add FK based on *_id naming convention if not already assigned
                if (preg_match('/^(.*)_id$/', $column, $matches)) {
                    $baseName = $matches[1]; // assumes the table name is singular

                    $referencedTable = Str::plural($baseName);

                    $this->foreignKeys[$table][$column] = [
                        'referenced_table' => $referencedTable,
                        'referenced_column' => 'id'
                    ];
                }
            }
        }

        $this->info('✅ Custom foreign keys (created_by, updated_by, *_id) loaded where applicable.');

        // Optional: Debug output
        // $this->info('📌 Foreign key map: ' . json_encode($this->foreignKeys, JSON_PRETTY_PRINT));
    }


    protected function migrateTable($table)
    {
        $this->info("➡️ Migrating table: $table");

        $primaryKey = $this->getPrimaryKey($table);
        $hasPrimaryKey = !empty($primaryKey);

        $rows = $this->mysql->table($table)->get();
        $docs = [];
        $idMap = [];

        foreach ($rows as $row) {
            $rowArray = (array) $row;

            // Always generate a new ObjectId
            $newId = new ObjectId();

            // If table has PK, create ID mapping for FK remap
            if ($hasPrimaryKey) {
                $oldId = $rowArray[$primaryKey];
                $idMap[$oldId] = $newId;
                $rowArray['mysql_id'] = $oldId; // Optional: keep MySQL PK
                unset($rowArray[$primaryKey]);  // Optional: remove original PK
            }

            // Assign MongoDB _id
            $rowArray['_id'] = $newId;

            $docs[] = $rowArray;
        }

        if ($hasPrimaryKey) {
            $this->tableIdMaps[$table] = $idMap;
        }

        if (count($docs)) {
            $this->mongo->selectCollection($table)->insertMany($docs);
            $this->info("✅ Inserted " . count($docs) . " documents into MongoDB collection [$table]");
        } else {
            $this->warn("⚠️ No records found in $table.");
        }
    }


    protected function remapForeignKeys($table)
    {
        if (!isset($this->foreignKeys[$table])) {
            $this->info("ℹ️ No foreign keys to remap for table [$table]");
            return;
        }

        $this->info("🔧 Remapping foreign keys in [$table]...");

        $collection = $this->mongo->selectCollection($table);

        $cursor = $collection->find();
        foreach ($cursor as $doc) {
            $updates = [];

            foreach ($this->foreignKeys[$table] as $column => $fk) {
                $referencedTable = $fk['referenced_table'];
                $referencedColumn = $fk['referenced_column']; // usually "id"

                // Get old FK value from document
                $foreignKey = $doc[$column] ?? null;

                if ($foreignKey === null) {
                    continue; // FK field is missing or null
                }

                // Get the new ObjectId from map
                $newObjectId = $this->tableIdMaps[$referencedTable][$foreignKey] ?? null;

                if (!$newObjectId) {
                    $this->warn("❗ Could not find ObjectId for FK $foreignKey in [$referencedTable]");
                    continue;
                }

                // Set update operations
                $updates[$column] = $newObjectId;
            }

            if (!empty($updates)) {
                // Remove null keys (optional)
                $updates = array_filter($updates, function ($v) {
                    return $v !== null;
                });

                // Update document
                $collection->updateOne(
                    ['_id' => $doc['_id']],
                    ['$set' => $updates]
                );
            }
        }

        $this->info("✅ Finished remapping foreign keys for [$table]");
    }
}
