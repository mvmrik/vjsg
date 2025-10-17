<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\ObjectType;

class ToolSeeder extends Seeder
{
    public function run()
    {
        // Clear pivot table to avoid duplicates
        DB::table('object_type_tool_type')->truncate();
        // Ensure object types exist
        $objectTypes = [
            'house' => ObjectType::firstOrCreate(['type' => 'house'], ['name' => 'Къща', 'icon' => 'cilHome', 'build_time_minutes' => 5, 'meta' => json_encode(['width' => 3, 'height' => 3]), 'created_at' => now(), 'updated_at' => now()]),
            'school' => ObjectType::firstOrCreate(['type' => 'school'], ['name' => 'Училище', 'icon' => 'cilSchool', 'build_time_minutes' => 10, 'meta' => json_encode(['width' => 4, 'height' => 4]), 'created_at' => now(), 'updated_at' => now()]),
            'hospital' => ObjectType::firstOrCreate(['type' => 'hospital'], ['name' => 'Болница', 'icon' => 'cilHospital', 'build_time_minutes' => 15, 'meta' => json_encode(['width' => 5, 'height' => 5]), 'created_at' => now(), 'updated_at' => now()]),
            'factory' => ObjectType::firstOrCreate(['type' => 'factory'], ['name' => 'Фабрика', 'icon' => 'cilFactory', 'build_time_minutes' => 20, 'meta' => json_encode(['width' => 6, 'height' => 6]), 'created_at' => now(), 'updated_at' => now()]),
            'field' => ObjectType::firstOrCreate(['type' => 'field'], ['name' => 'Поле за добив', 'icon' => 'cilField', 'build_time_minutes' => 3, 'meta' => json_encode(['width' => 5, 'height' => 5]), 'created_at' => now(), 'updated_at' => now()]),
        ];

        // Tool types
        $toolTypes = [
            ['name' => 'Student Materials', 'description' => 'Materials for students', 'icon' => 'student_materials.png'],
            ['name' => 'Medical Equipment', 'description' => 'Equipment for medical use', 'icon' => 'medical_equipment.png'],
            ['name' => 'Food Production', 'description' => 'Production of food', 'icon' => 'food_production.png'],
            ['name' => 'Food Raw Materials', 'description' => 'Raw materials for food', 'icon' => 'food_raw_materials.png'],
            ['name' => 'Building Raw Materials', 'description' => 'Raw materials for building', 'icon' => 'building_raw_materials.png'],
            ['name' => 'Heating Device', 'description' => 'Device for heating', 'icon' => 'heating_device.png'],
            ['name' => 'Security System', 'description' => 'System for security', 'icon' => 'security_system.png'],
        ];

        $toolTypeIds = [];
        $toolTypeIds = [];
        foreach ($toolTypes as $tool) {
            $id = DB::table('tool_types')->updateOrInsert(
                ['name' => $tool['name']],
                [
                    'description' => $tool['description'],
                    'icon' => $tool['icon'],
                    'updated_at' => now(),
                ]
            );
            // updateOrInsert returns void, so get the id
            $existing = DB::table('tool_types')->where('name', $tool['name'])->first();
            $toolTypeIds[] = $existing->id;
        }

        // Pivot relationships (object_type_tool_type)
        $relations = [
            [$objectTypes['school']->id, $toolTypeIds[0]], // Student Materials -> School
            [$objectTypes['hospital']->id, $toolTypeIds[1]], // Medical Equipment -> Hospital
            [$objectTypes['factory']->id, $toolTypeIds[2]], // Food Production -> Factory
            [$objectTypes['field']->id, $toolTypeIds[3]], // Food Raw Materials -> Field
            [$objectTypes['field']->id, $toolTypeIds[4]], // Building Raw Materials -> Field
            [$objectTypes['school']->id, $toolTypeIds[5]], // Heating Device -> School
            [$objectTypes['hospital']->id, $toolTypeIds[5]], // Heating Device -> Hospital
            [$objectTypes['factory']->id, $toolTypeIds[5]], // Heating Device -> Factory
            [$objectTypes['school']->id, $toolTypeIds[6]], // Security System -> School
            [$objectTypes['hospital']->id, $toolTypeIds[6]], // Security System -> Hospital
            [$objectTypes['factory']->id, $toolTypeIds[6]], // Security System -> Factory
        ];

        foreach ($relations as $relation) {
            DB::table('object_type_tool_type')->insert([
                'object_type_id' => $relation[0],
                'tool_type_id' => $relation[1],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}