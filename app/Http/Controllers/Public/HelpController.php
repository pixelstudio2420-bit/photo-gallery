<?php
namespace App\Http\Controllers\Public;
use App\Http\Controllers\Controller;

class HelpController extends Controller
{
    public function index() {
        $seo = app(\App\Services\SeoService::class);
        $seo->title('ช่วยเหลือ')
            ->description('ศูนย์รวมคำถามและคำตอบ รวมถึงข้อมูลการใช้งาน Photo Gallery')
            ->setBreadcrumbs([
                ['name' => 'หน้าแรก', 'url' => url('/')],
                ['name' => 'ช่วยเหลือ'],
            ]);

        return view('public.help');
    }
}
