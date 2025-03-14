<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestMongoDb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-mongo-db';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $d = DB::connection('mongodb')->getMongoDB();
        foreach ($d->listCollections() as $collectionInfo) {
            echo "Collection Name: " . $collectionInfo->getName() . "\n";
            // Validation Rules (if they exist)
            $options = $collectionInfo->getOptions();

            if (isset($options['validator'])) {
                echo "Validation Schema:\n";
                print_r($options['validator']);
            } else {
                echo "No validation schema defined.\n";
            }

            echo "-----------------------\n";
        }
        dd($d->listCollections());
        $startTime = microtime(true);
        // $a =  User::factory(1000000)->create();
        // dd($a->count());
        $a =   User::query()->count();
        $executionTime = microtime(true) - $startTime;
        dd([
            'data' => $a,
            'execution_time_ms' => round($executionTime * 1000, 2) . ' ms'
        ]);
    }
}
