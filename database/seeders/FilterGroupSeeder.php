<?php

namespace Database\Seeders;

use App\Models\FilterGroup;
use App\Models\FilterValue;
use Illuminate\Database\Seeder;

class FilterGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'name'    => 'Giao thức',
                'name_en' => 'Protocol',
                'sort_order' => 1,
                'values' => [
                    ['name' => 'KNX',       'name_en' => 'KNX',       'sort_order' => 1],
                    ['name' => 'DALI-2',    'name_en' => 'DALI-2',    'sort_order' => 2],
                    ['name' => 'DMX512',    'name_en' => 'DMX512',    'sort_order' => 3],
                    ['name' => 'Modbus',    'name_en' => 'Modbus',    'sort_order' => 4],
                    ['name' => 'BACnet',    'name_en' => 'BACnet',    'sort_order' => 5],
                    ['name' => 'Casambi',   'name_en' => 'Casambi',   'sort_order' => 6],
                    ['name' => 'Matter',    'name_en' => 'Matter',    'sort_order' => 7],
                    ['name' => 'Zigbee',    'name_en' => 'Zigbee',    'sort_order' => 8],
                    ['name' => 'Z-Wave',    'name_en' => 'Z-Wave',    'sort_order' => 9],
                ],
            ],
            [
                'name'    => 'Điện áp',
                'name_en' => 'Voltage',
                'sort_order' => 2,
                'values' => [
                    ['name' => '12V DC',    'name_en' => '12V DC',    'sort_order' => 1],
                    ['name' => '24V DC',    'name_en' => '24V DC',    'sort_order' => 2],
                    ['name' => '48V DC',    'name_en' => '48V DC',    'sort_order' => 3],
                    ['name' => '110V AC',   'name_en' => '110V AC',   'sort_order' => 4],
                    ['name' => '220V AC',   'name_en' => '220V AC',   'sort_order' => 5],
                    ['name' => '230V AC',   'name_en' => '230V AC',   'sort_order' => 6],
                ],
            ],
            [
                'name'    => 'Kiểu lắp đặt',
                'name_en' => 'Mounting Type',
                'sort_order' => 3,
                'values' => [
                    ['name' => 'Gắn ray DIN',    'name_en' => 'DIN Rail',       'sort_order' => 1],
                    ['name' => 'Gắn tường',       'name_en' => 'Wall Mount',     'sort_order' => 2],
                    ['name' => 'Âm trần',          'name_en' => 'Ceiling Flush',  'sort_order' => 3],
                    ['name' => 'Nổi trần',         'name_en' => 'Ceiling Surface','sort_order' => 4],
                    ['name' => 'Gắn bảng điện',   'name_en' => 'Panel Mount',    'sort_order' => 5],
                    ['name' => 'Gắn thanh ray 35mm','name_en' => 'Top Hat Rail', 'sort_order' => 6],
                ],
            ],
            [
                'name'    => 'Chuẩn bảo vệ',
                'name_en' => 'IP Rating',
                'sort_order' => 4,
                'values' => [
                    ['name' => 'IP20', 'name_en' => 'IP20', 'sort_order' => 1],
                    ['name' => 'IP44', 'name_en' => 'IP44', 'sort_order' => 2],
                    ['name' => 'IP54', 'name_en' => 'IP54', 'sort_order' => 3],
                    ['name' => 'IP65', 'name_en' => 'IP65', 'sort_order' => 4],
                    ['name' => 'IP67', 'name_en' => 'IP67', 'sort_order' => 5],
                    ['name' => 'IP68', 'name_en' => 'IP68', 'sort_order' => 6],
                ],
            ],
            [
                'name'    => 'Chứng nhận',
                'name_en' => 'Certification',
                'sort_order' => 5,
                'values' => [
                    ['name' => 'KNX Certified',  'name_en' => 'KNX Certified',  'sort_order' => 1],
                    ['name' => 'CE',             'name_en' => 'CE',             'sort_order' => 2],
                    ['name' => 'UL',             'name_en' => 'UL',             'sort_order' => 3],
                    ['name' => 'FCC',            'name_en' => 'FCC',            'sort_order' => 4],
                    ['name' => 'RoHS',           'name_en' => 'RoHS',           'sort_order' => 5],
                    ['name' => 'DALI Certified', 'name_en' => 'DALI Certified', 'sort_order' => 6],
                ],
            ],
        ];

        foreach ($groups as $groupData) {
            $values = $groupData['values'];
            unset($groupData['values']);

            $group = FilterGroup::firstOrCreate(
                ['name' => $groupData['name']],
                array_merge($groupData, ['is_active' => true])
            );

            foreach ($values as $valueData) {
                FilterValue::firstOrCreate(
                    ['filter_group_id' => $group->id, 'name' => $valueData['name']],
                    array_merge($valueData, ['filter_group_id' => $group->id, 'is_active' => true])
                );
            }
        }
    }
}
