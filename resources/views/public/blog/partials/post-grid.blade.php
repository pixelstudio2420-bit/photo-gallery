@forelse($posts ?? [] as $post)
  @include('public.blog._post-card', ['post' => $post])
@empty
<div class="col-span-full">
  <div class="text-center py-16">
    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-50 mb-4">
      <i class="bi bi-newspaper text-4xl text-gray-300"></i>
    </div>
    <p class="text-gray-400 font-medium mb-1">ไม่พบบทความที่ตรงกับการค้นหา</p>
    <p class="text-gray-300 text-sm">ลองเปลี่ยนคำค้นหาหรือตัวกรอง</p>
  </div>
</div>
@endforelse
