<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ThaiBankSeeder extends Seeder
{
    /**
     * Seed common Thai banks into the thai_banks table.
     * Uses updateOrCreate keyed on `code` so re-running is safe (idempotent).
     */
    public function run(): void
    {
        $banks = [
            ['code' => 'BBL',           'name_th' => 'กรุงเทพ',              'name_en' => 'Bangkok Bank',           'color' => '#1e22aa'],
            ['code' => 'KBANK',         'name_th' => 'กสิกรไทย',             'name_en' => 'Kasikornbank',           'color' => '#138f2d'],
            ['code' => 'KTB',           'name_th' => 'กรุงไทย',              'name_en' => 'Krungthai Bank',         'color' => '#1ba5e0'],
            ['code' => 'TTB',            'name_th' => 'ทีเอ็มบีธนชาต',       'name_en' => 'TMBThanachart Bank',     'color' => '#1279be'],
            ['code' => 'SCB',           'name_th' => 'ไทยพาณิชย์',           'name_en' => 'SCB',                    'color' => '#4e2a82'],
            ['code' => 'BAY',           'name_th' => 'กรุงศรีอยุธยา',        'name_en' => 'Bank of Ayudhya',        'color' => '#fec43b'],
            ['code' => 'KKP',           'name_th' => 'เกียรตินาคินภัทร',     'name_en' => 'Kiatnakin Phatra',       'color' => '#199078'],
            ['code' => 'CIMBT',         'name_th' => 'ซีไอเอ็มบีไทย',       'name_en' => 'CIMB Thai',              'color' => '#7e1f20'],
            ['code' => 'TISCO',         'name_th' => 'ทิสโก้',               'name_en' => 'TISCO Bank',             'color' => '#12549f'],
            ['code' => 'UOBT',          'name_th' => 'ยูโอบี',               'name_en' => 'UOB Thailand',           'color' => '#0b3979'],
            ['code' => 'GSB',           'name_th' => 'ออมสิน',               'name_en' => 'Government Savings',     'color' => '#eb198d'],
            ['code' => 'BAAC',          'name_th' => 'ธ.ก.ส.',               'name_en' => 'BAAC',                   'color' => '#4b9b1d'],
            ['code' => 'GHB',           'name_th' => 'อาคารสงเคราะห์',       'name_en' => 'Government Housing',     'color' => '#f6841f'],
            ['code' => 'LH',            'name_th' => 'แลนด์ แอนด์ เฮ้าส์',  'name_en' => 'Land and Houses',        'color' => '#6d6e71'],
        ];

        foreach ($banks as $bank) {
            DB::table('thai_banks')->updateOrInsert(
                ['code' => $bank['code']],
                [
                    'name_th' => $bank['name_th'],
                    'name_en' => $bank['name_en'],
                    'color'   => $bank['color'],
                ],
            );
        }
    }
}
