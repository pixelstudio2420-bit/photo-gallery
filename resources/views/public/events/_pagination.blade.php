@if(isset($events) && $events instanceof \Illuminate\Pagination\LengthAwarePaginator && $events->hasPages())
<div class="flex justify-center mt-8">
  {{ $events->withQueryString()->links() }}
</div>
@endif
