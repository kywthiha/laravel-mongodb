<?php

namespace App\Helper;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use MongoDB\Database;

abstract class MongoCollectionMigration extends Migration
{
    /**
     * The name of the MongoDB collection
     *
     * @var string
     */
    protected $collectionName;

    /**
     * The JSON schema validator for the collection
     *
     * @var array
     */
    protected $schemaValidator = [];

    /**
     * Run the migration to create or modify the collection
     */
    public function up(): void
    {
        $mongoDb = $this->getMongoDatabase();

        // Check if the collection exists
        $this->ensureCollectionExists($mongoDb);

        // Apply schema validation if provided
        if (! empty($this->schemaValidator)) {
            $this->applySchemaValidation($mongoDb);
        }
    }

    /**
     * Get the MongoDB database connection
     */
    protected function getMongoDatabase(): Database
    {
        return DB::connection('mongodb')->getMongoDB();
    }

    /**
     * Ensure the collection exists
     */
    protected function ensureCollectionExists(Database $mongoDb): void
    {
        $collections = iterator_to_array($mongoDb->listCollections());
        $collectionNames = array_map(fn ($c) => $c->getName(), $collections);

        if (! in_array($this->collectionName, $collectionNames)) {
            $mongoDb->createCollection($this->collectionName);
            info("Collection '{$this->collectionName}' created successfully.");
        } else {
            info("Collection '{$this->collectionName}' already exists.");
        }
    }

    /**
     * Apply schema validation to the collection
     */
    protected function applySchemaValidation(Database $mongoDb): void
    {
        // First, remove any existing validation
        $mongoDb->command([
            'collMod' => $this->collectionName,
            'validator' => (object) [],
        ]);

        // Then apply new validation
        $mongoDb->command([
            'collMod' => $this->collectionName,
            'validator' => $this->schemaValidator,
            'validationLevel' => 'strict',
        ]);
    }

    /**
     * Reverse the migration (no-op by default)
     */
    public function down(): void
    {
        // Optional: Implement collection removal or other cleanup
    }
}
