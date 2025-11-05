<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use App\Models\User;
use App\Models\CityObject;
use App\Models\Notification;
use App\Models\OccupiedWorker;
use App\Models\ObjectType;

class CheckCompletedBuilds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:completed-builds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for completed builds and upgrades, send notifications and free workers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $completedObjects = CityObject::whereNotNull('ready_at')
            ->where('ready_at', '<=', time())
            ->get();

        $count = 0;
        foreach ($completedObjects as $object) {
            // Get user and set locale for translation
            $user = User::find($object->user_id);
            if ($user && $user->locale) {
                App::setLocale($user->locale);
            }

            // Determine if this was a build or upgrade
            $properties = $object->properties ?? [];
            $wasUpgrade = isset($properties['workers']);

            // Create notification
            // Resolve object type label: prefer language key, fallback to ObjectType.name or prettified type
            $objectLabel = __('city.' . $object->object_type);
            if ($objectLabel === 'city.' . $object->object_type) {
                $ot = ObjectType::where('type', $object->object_type)->first();
                if ($ot && $ot->name) {
                    $objectLabel = $ot->name;
                } else {
                    $objectLabel = ucwords(str_replace('_', ' ', $object->object_type));
                }
            }

            if ($wasUpgrade) {
                // Upgrade completed - resolve translations now and store full text
                Notification::create([
                    'user_id' => $object->user_id,
                    'title' => __('notifications.upgrade_completed_title'),
                    'message' => __('notifications.upgrade_completed_message', [
                        'objectType' => $objectLabel,
                        'level' => $object->level
                    ]),
                    'type' => 'success',
                    'is_read' => false,
                    'data' => json_encode([
                        'objectType' => $object->object_type,
                        'level' => $object->level
                    ])
                ]);
            } else {
                // Build completed - resolve translations now and store full text
                Notification::create([
                    'user_id' => $object->user_id,
                    'title' => __('notifications.build_completed_title'),
                    'message' => __('notifications.build_completed_message', [
                        'objectType' => $objectLabel
                    ]),
                    'type' => 'success',
                    'is_read' => false,
                    'data' => json_encode([
                        'objectType' => $object->object_type
                    ])
                ]);
            }

            // Finalize production: transfer per-object production_outputs -> inventories.count and decrement inventories.temp_count accordingly
            $db = \Illuminate\Support\Facades\DB::connection();
            $db->beginTransaction();
            try {
                // Aggregate outputs for this object grouped by tool_type_id
                $outputs = \App\Models\ProductionOutput::where('city_object_id', $object->id)
                    ->where('user_id', $object->user_id)
                    ->select('tool_type_id', \Illuminate\Support\Facades\DB::raw('SUM(count) as total'))
                    ->groupBy('tool_type_id')
                    ->get();

                foreach ($outputs as $out) {
                    $toolTypeId = $out->tool_type_id;
                    $toTransfer = intval($out->total);
                    if ($toTransfer <= 0) continue;

                    // Lock inventory row and update
                    $inventory = \App\Models\Inventory::where('user_id', $object->user_id)
                        ->where('tool_type_id', $toolTypeId)
                        ->lockForUpdate()
                        ->first();

                    if (!$inventory) {
                        // create inventory if missing
                        $inventory = \App\Models\Inventory::create([
                            'user_id' => $object->user_id,
                            'tool_type_id' => $toolTypeId,
                            'count' => $toTransfer,
                            'temp_count' => 0
                        ]);
                    } else {
                        $inventory->count = intval($inventory->count) + $toTransfer;
                        // decrement temp_count but don't let it go negative
                        $inventory->temp_count = max(0, intval($inventory->temp_count) - $toTransfer);
                        $inventory->save();
                    }
                }

                // Delete production outputs for this object (we transferred them)
                \App\Models\ProductionOutput::where('city_object_id', $object->id)
                    ->where('user_id', $object->user_id)
                    ->delete();

                $db->commit();
            } catch (\Exception $e) {
                $db->rollBack();
                // log and continue so other objects aren't blocked
                \Log::error('Failed to finalize production for object ' . $object->id . ': ' . $e->getMessage());
            }

            // Clear ready_at and persist changes
            $object->ready_at = null;
            $object->save();

            // Free workers if they were occupied: restore them back to `people` counts
            try {
                $db2 = \Illuminate\Support\Facades\DB::connection();
                $db2->beginTransaction();

                $occupiedRows = OccupiedWorker::where('user_id', $object->user_id)
                    ->where('city_object_id', $object->id)
                    ->get();

                foreach ($occupiedRows as $occ) {
                    $lvl = intval($occ->level);
                    $cnt = intval($occ->count);

                    // Lock and increment or insert person row
                    $person = DB::table('people')
                        ->where('user_id', $object->user_id)
                        ->where('level', $lvl)
                        ->lockForUpdate()
                        ->first();

                    if ($person) {
                        DB::table('people')
                            ->where('user_id', $object->user_id)
                            ->where('level', $lvl)
                            ->update(['count' => intval($person->total ?? $person->count) + $cnt]);
                    } else {
                        DB::table('people')->insert([
                            'user_id' => $object->user_id,
                            'level' => $lvl,
                            'count' => $cnt
                        ]);
                    }
                }

                // Delete occupied rows for this object
                OccupiedWorker::where('user_id', $object->user_id)
                    ->where('city_object_id', $object->id)
                    ->delete();

                $db2->commit();
            } catch (\Exception $e) {
                try { $db2->rollBack(); } catch (\Exception $_) {}
                \Log::error('Failed to restore occupied workers for object ' . $object->id . ': ' . $e->getMessage());
            }

            $count++;
        }

        $this->info("Processed {$count} completed builds/upgrades");
        return 0;
    }
}
