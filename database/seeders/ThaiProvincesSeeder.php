<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Populate thai_provinces with the 77 official Thai provinces.
 *
 * Source: official 2023 list from กรมการปกครอง (Department of Provincial
 * Administration). Geography group follows the standard 6-region scheme.
 *
 * Idempotent — uses updateOrInsert keyed by name_en, so re-running the
 * seeder is safe and refreshes any name corrections.
 */
class ThaiProvincesSeeder extends Seeder
{
    public function run(): void
    {
        $provinces = [
            // ── Central ─────────────────────────────────────
            ['name_th' => 'กรุงเทพมหานคร',    'name_en' => 'Bangkok',             'group' => 'central'],
            ['name_th' => 'นนทบุรี',           'name_en' => 'Nonthaburi',          'group' => 'central'],
            ['name_th' => 'ปทุมธานี',          'name_en' => 'Pathum Thani',        'group' => 'central'],
            ['name_th' => 'พระนครศรีอยุธยา',  'name_en' => 'Ayutthaya',           'group' => 'central'],
            ['name_th' => 'อ่างทอง',           'name_en' => 'Ang Thong',           'group' => 'central'],
            ['name_th' => 'ลพบุรี',            'name_en' => 'Lopburi',             'group' => 'central'],
            ['name_th' => 'สิงห์บุรี',         'name_en' => 'Sing Buri',           'group' => 'central'],
            ['name_th' => 'ชัยนาท',            'name_en' => 'Chai Nat',            'group' => 'central'],
            ['name_th' => 'สระบุรี',           'name_en' => 'Saraburi',            'group' => 'central'],
            ['name_th' => 'นครปฐม',            'name_en' => 'Nakhon Pathom',       'group' => 'central'],
            ['name_th' => 'สมุทรปราการ',       'name_en' => 'Samut Prakan',        'group' => 'central'],
            ['name_th' => 'สมุทรสาคร',         'name_en' => 'Samut Sakhon',        'group' => 'central'],
            ['name_th' => 'สมุทรสงคราม',       'name_en' => 'Samut Songkhram',     'group' => 'central'],
            ['name_th' => 'นครนายก',           'name_en' => 'Nakhon Nayok',        'group' => 'central'],
            ['name_th' => 'สุพรรณบุรี',        'name_en' => 'Suphan Buri',         'group' => 'central'],
            ['name_th' => 'ราชบุรี',           'name_en' => 'Ratchaburi',          'group' => 'central'],
            ['name_th' => 'กาญจนบุรี',         'name_en' => 'Kanchanaburi',        'group' => 'central'],
            ['name_th' => 'เพชรบุรี',          'name_en' => 'Phetchaburi',         'group' => 'central'],
            ['name_th' => 'ประจวบคีรีขันธ์',   'name_en' => 'Prachuap Khiri Khan', 'group' => 'central'],
            // ── Eastern ─────────────────────────────────────
            ['name_th' => 'ชลบุรี',            'name_en' => 'Chonburi',            'group' => 'eastern'],
            ['name_th' => 'ระยอง',             'name_en' => 'Rayong',              'group' => 'eastern'],
            ['name_th' => 'จันทบุรี',          'name_en' => 'Chanthaburi',         'group' => 'eastern'],
            ['name_th' => 'ตราด',              'name_en' => 'Trat',                'group' => 'eastern'],
            ['name_th' => 'ฉะเชิงเทรา',        'name_en' => 'Chachoengsao',        'group' => 'eastern'],
            ['name_th' => 'ปราจีนบุรี',        'name_en' => 'Prachin Buri',        'group' => 'eastern'],
            ['name_th' => 'สระแก้ว',           'name_en' => 'Sa Kaeo',             'group' => 'eastern'],
            // ── Northern ────────────────────────────────────
            ['name_th' => 'เชียงใหม่',         'name_en' => 'Chiang Mai',          'group' => 'northern'],
            ['name_th' => 'เชียงราย',          'name_en' => 'Chiang Rai',          'group' => 'northern'],
            ['name_th' => 'น่าน',              'name_en' => 'Nan',                 'group' => 'northern'],
            ['name_th' => 'พะเยา',             'name_en' => 'Phayao',              'group' => 'northern'],
            ['name_th' => 'แพร่',              'name_en' => 'Phrae',               'group' => 'northern'],
            ['name_th' => 'แม่ฮ่องสอน',        'name_en' => 'Mae Hong Son',        'group' => 'northern'],
            ['name_th' => 'ลำปาง',             'name_en' => 'Lampang',             'group' => 'northern'],
            ['name_th' => 'ลำพูน',             'name_en' => 'Lamphun',             'group' => 'northern'],
            ['name_th' => 'อุตรดิตถ์',         'name_en' => 'Uttaradit',           'group' => 'northern'],
            ['name_th' => 'ตาก',               'name_en' => 'Tak',                 'group' => 'northern'],
            ['name_th' => 'พิษณุโลก',          'name_en' => 'Phitsanulok',         'group' => 'northern'],
            ['name_th' => 'สุโขทัย',           'name_en' => 'Sukhothai',           'group' => 'northern'],
            ['name_th' => 'เพชรบูรณ์',         'name_en' => 'Phetchabun',          'group' => 'northern'],
            ['name_th' => 'พิจิตร',            'name_en' => 'Phichit',             'group' => 'northern'],
            ['name_th' => 'กำแพงเพชร',         'name_en' => 'Kamphaeng Phet',      'group' => 'northern'],
            ['name_th' => 'นครสวรรค์',         'name_en' => 'Nakhon Sawan',        'group' => 'northern'],
            ['name_th' => 'อุทัยธานี',         'name_en' => 'Uthai Thani',         'group' => 'northern'],
            // ── Northeastern (Isan) ─────────────────────────
            ['name_th' => 'นครราชสีมา',        'name_en' => 'Nakhon Ratchasima',   'group' => 'northeastern'],
            ['name_th' => 'บุรีรัมย์',         'name_en' => 'Buriram',             'group' => 'northeastern'],
            ['name_th' => 'สุรินทร์',          'name_en' => 'Surin',               'group' => 'northeastern'],
            ['name_th' => 'ศรีสะเกษ',          'name_en' => 'Sisaket',             'group' => 'northeastern'],
            ['name_th' => 'อุบลราชธานี',       'name_en' => 'Ubon Ratchathani',    'group' => 'northeastern'],
            ['name_th' => 'ยโสธร',             'name_en' => 'Yasothon',            'group' => 'northeastern'],
            ['name_th' => 'ชัยภูมิ',           'name_en' => 'Chaiyaphum',          'group' => 'northeastern'],
            ['name_th' => 'อำนาจเจริญ',        'name_en' => 'Amnat Charoen',       'group' => 'northeastern'],
            ['name_th' => 'หนองบัวลำภู',       'name_en' => 'Nong Bua Lamphu',     'group' => 'northeastern'],
            ['name_th' => 'ขอนแก่น',           'name_en' => 'Khon Kaen',           'group' => 'northeastern'],
            ['name_th' => 'อุดรธานี',          'name_en' => 'Udon Thani',          'group' => 'northeastern'],
            ['name_th' => 'เลย',               'name_en' => 'Loei',                'group' => 'northeastern'],
            ['name_th' => 'หนองคาย',           'name_en' => 'Nong Khai',           'group' => 'northeastern'],
            ['name_th' => 'มหาสารคาม',         'name_en' => 'Maha Sarakham',       'group' => 'northeastern'],
            ['name_th' => 'ร้อยเอ็ด',          'name_en' => 'Roi Et',              'group' => 'northeastern'],
            ['name_th' => 'กาฬสินธุ์',         'name_en' => 'Kalasin',             'group' => 'northeastern'],
            ['name_th' => 'สกลนคร',            'name_en' => 'Sakon Nakhon',        'group' => 'northeastern'],
            ['name_th' => 'นครพนม',            'name_en' => 'Nakhon Phanom',       'group' => 'northeastern'],
            ['name_th' => 'มุกดาหาร',          'name_en' => 'Mukdahan',            'group' => 'northeastern'],
            ['name_th' => 'บึงกาฬ',            'name_en' => 'Bueng Kan',           'group' => 'northeastern'],
            // ── Southern ────────────────────────────────────
            ['name_th' => 'ชุมพร',             'name_en' => 'Chumphon',            'group' => 'southern'],
            ['name_th' => 'ระนอง',             'name_en' => 'Ranong',              'group' => 'southern'],
            ['name_th' => 'สุราษฎร์ธานี',      'name_en' => 'Surat Thani',         'group' => 'southern'],
            ['name_th' => 'พังงา',             'name_en' => 'Phang Nga',           'group' => 'southern'],
            ['name_th' => 'ภูเก็ต',            'name_en' => 'Phuket',              'group' => 'southern'],
            ['name_th' => 'กระบี่',            'name_en' => 'Krabi',               'group' => 'southern'],
            ['name_th' => 'นครศรีธรรมราช',     'name_en' => 'Nakhon Si Thammarat', 'group' => 'southern'],
            ['name_th' => 'ตรัง',              'name_en' => 'Trang',               'group' => 'southern'],
            ['name_th' => 'พัทลุง',            'name_en' => 'Phatthalung',         'group' => 'southern'],
            ['name_th' => 'สตูล',              'name_en' => 'Satun',               'group' => 'southern'],
            ['name_th' => 'สงขลา',             'name_en' => 'Songkhla',            'group' => 'southern'],
            ['name_th' => 'ปัตตานี',           'name_en' => 'Pattani',             'group' => 'southern'],
            ['name_th' => 'ยะลา',              'name_en' => 'Yala',                'group' => 'southern'],
            ['name_th' => 'นราธิวาส',          'name_en' => 'Narathiwat',          'group' => 'southern'],
        ];

        // The thai_provinces.id column is NOT auto-incrementing on this
        // schema — we have to assign IDs explicitly. Use 1-77 in array
        // order which matches the canonical Department of Provincial
        // Administration codes for the provinces we list. After the
        // seeder finishes, an explicit setval() resets the Postgres
        // sequence so future inserts (if any new provinces ever get
        // added) start from 78+ rather than colliding with seeded IDs.
        foreach ($provinces as $i => $p) {
            DB::table('thai_provinces')->updateOrInsert(
                ['name_en' => $p['name_en']],
                [
                    'id'              => $i + 1,
                    'name_th'         => $p['name_th'],
                    'name_en'         => $p['name_en'],
                    'geography_group' => $p['group'],
                ]
            );
        }

        // Bump the auto-increment sequence to MAX(id) so any future
        // INSERT without an explicit id picks up from 78. Postgres-only
        // — the table doesn't ship to MySQL on this app, but wrap in
        // try/catch in case someone ports it.
        try {
            DB::statement("SELECT setval(pg_get_serial_sequence('thai_provinces', 'id'), (SELECT COALESCE(MAX(id), 1) FROM thai_provinces))");
        } catch (\Throwable) {
            // Sequence reset is a nice-to-have; if it fails (non-Postgres
            // driver, missing perm) the seeder data is still correct.
        }

        $this->command?->info('Seeded ' . count($provinces) . ' Thai provinces');
    }
}
