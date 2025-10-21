<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use App\Models\User;
use App\Models\CityObject;
use App\Models\Notification;
use App\Models\OccupiedWorker;

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
            if ($wasUpgrade) {
                // Upgrade completed
                Notification::create([
                    'user_id' => $object->user_id,
                    'title' => 'notifications.upgrade_completed_title',
                    'message' => __('notifications.upgrade_completed_message', [
                        'objectType' => __('city.' . $object->object_type),
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
                // Build completed
                Notification::create([
                    'user_id' => $object->user_id,
                    'title' => 'notifications.build_completed_title',
                    'message' => __('notifications.build_completed_message', [
                        'objectType' => __('city.' . $object->object_type)
                    ]),
                    'type' => 'success',
                    'is_read' => false,
                    'data' => json_encode([
                        'objectType' => $object->object_type
                    ])
                ]);
            }

            // Finalize production: transfer inventories.temp_count -> count for produced tool types attached to this object
            $tools = \App\Models\Tool::where('object_id', $object->id)
                ->join('tool_types', 'tools.tool_type_id', '=', 'tool_types.id')
                ->select('tool_types.units_per_hour', 'tool_types.produces_tool_type_id')
                ->get();

            foreach ($tools as $t) {
                if (!$t->units_per_hour || !$t->produces_tool_type_id) continue;
                $inventory = \App\Models\Inventory::where('user_id', $object->user_id)
                    ->where('tool_type_id', $t->produces_tool_type_id)
                    ->first();
                if ($inventory && intval($inventory->temp_count) > 0) {
                    $inventory->count = intval($inventory->count) + intval($inventory->temp_count);
                    $inventory->temp_count = 0;
                    $inventory->save();
                }
            }

            // Clear ready_at
            $object->ready_at = null;
            $object->save();

            // Free workers if they were occupied
            OccupiedWorker::where('user_id', $object->user_id)
                ->where('city_object_id', $object->id)
                ->delete();

            $count++;
        }

        $this->info("Processed {$count} completed builds/upgrades");
        return 0;
    }
}
