<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>สรุปข่าว {{ $content['province_name'] }}</title>
<style>
    body { margin: 0; padding: 0; background: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Sarabun", sans-serif; color: #0f172a; }
    .wrap { max-width: 600px; margin: 0 auto; background: #fff; }
    .header { background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); padding: 32px 24px; color: #fff; text-align: center; }
    .header h1 { margin: 0 0 6px; font-size: 24px; font-weight: 800; }
    .header p { margin: 0; opacity: .9; font-size: 14px; }
    .section { padding: 24px; border-bottom: 1px solid #e2e8f0; }
    .section:last-child { border-bottom: 0; }
    .section h2 { margin: 0 0 16px; font-size: 18px; color: #1e293b; }
    .item { display: flex; gap: 12px; padding: 12px; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 10px; text-decoration: none; color: inherit; }
    .item:hover { background: #f8fafc; }
    .item-img { width: 64px; height: 64px; border-radius: 10px; background: #f1f5f9; flex-shrink: 0; }
    .item-body { flex: 1; min-width: 0; }
    .item-title { font-size: 14px; font-weight: 600; color: #0f172a; margin: 0 0 4px; }
    .item-meta { font-size: 12px; color: #64748b; margin: 0; }
    .festival-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; color: #fff; margin-bottom: 6px; }
    .cta { display: inline-block; padding: 6px 14px; background: #6366f1; color: #fff !important; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; margin-top: 6px; }
    .footer { padding: 20px; background: #f8fafc; text-align: center; font-size: 11px; color: #64748b; }
    .footer a { color: #6366f1; }
</style>
</head>
<body>
<div class="wrap">

    {{-- ─── HERO ─── --}}
    <div class="header">
        <h1>📬 สรุปข่าว {{ $content['province_name'] }}</h1>
        <p>สัปดาห์นี้มีอะไรใหม่ในพื้นที่ของคุณ คุณ{{ $name }}</p>
    </div>

    {{-- ─── ACTIVE FESTIVALS ─── --}}
    @if($content['festivals']->isNotEmpty())
    <div class="section">
        <h2>🎉 เทศกาลที่กำลังจะมา</h2>
        @foreach($content['festivals'] as $f)
            @php $theme = \App\Services\FestivalThemeService::theme($f->theme_variant); @endphp
            <a href="{{ url($f->cta_url ?? '/') }}" class="item">
                <div class="item-body">
                    <span class="festival-badge" style="background: {{ $theme['gradient_css'] }};">
                        {{ $f->emoji }} {{ $f->short_name ?? $f->name }}
                    </span>
                    <div class="item-title">{{ $f->headline }}</div>
                    <p class="item-meta">
                        📅 {{ $f->starts_at->format('d M Y') }}
                        @if(!$f->starts_at->isSameDay($f->ends_at))
                            — {{ $f->ends_at->format('d M Y') }}
                        @endif
                    </p>
                    @if($f->cta_label)
                        <span class="cta">{{ $f->cta_label }} →</span>
                    @endif
                </div>
            </a>
        @endforeach
    </div>
    @endif

    {{-- ─── NEW EVENTS IN PROVINCE ─── --}}
    @if($content['events']->isNotEmpty())
    <div class="section">
        <h2>📸 อีเวนต์ใหม่ในจังหวัดของคุณ</h2>
        @foreach($content['events'] as $e)
            <a href="{{ url('/events/' . ($e->slug ?: $e->id)) }}" class="item">
                @if($e->cover_image)
                    <img src="{{ asset('storage/' . $e->cover_image) }}" class="item-img" alt="">
                @else
                    <div class="item-img"></div>
                @endif
                <div class="item-body">
                    <div class="item-title">{{ $e->name }}</div>
                    @if($e->shoot_date)
                        <p class="item-meta">📅 ถ่าย: {{ \Carbon\Carbon::parse($e->shoot_date)->format('d M Y') }}</p>
                    @endif
                </div>
            </a>
        @endforeach
    </div>
    @endif

    {{-- ─── ANNOUNCEMENTS ─── --}}
    @if($content['announcements']->isNotEmpty())
    <div class="section">
        <h2>📢 ประกาศจากเรา</h2>
        @foreach($content['announcements'] as $a)
            <a href="{{ $a->cta_url ?? url('/announcements/' . $a->slug) }}" class="item">
                <div class="item-body">
                    <div class="item-title">{{ $a->title }}</div>
                    @if($a->excerpt)
                        <p class="item-meta">{{ $a->excerpt }}</p>
                    @endif
                    @if($a->cta_label)
                        <span class="cta">{{ $a->cta_label }} →</span>
                    @endif
                </div>
            </a>
        @endforeach
    </div>
    @endif

    {{-- ─── FOOTER ─── --}}
    <div class="footer">
        คุณได้รับอีเมลฉบับนี้เพราะตั้งจังหวัด {{ $content['province_name'] }} ในโปรไฟล์ของคุณ
        <br>
        <a href="{{ url('/profile/edit') }}">เปลี่ยนจังหวัด</a>
        ·
        <a href="{{ url('/') }}">เข้าสู่เว็บไซต์</a>
    </div>
</div>
</body>
</html>
