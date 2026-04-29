@if($products->hasPages())
<div class="flex justify-center pagination-tw">
  {{ $products->withQueryString()->links() }}
</div>
@endif
