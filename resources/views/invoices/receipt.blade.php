<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>{{ $invoiceNo }}</title>
<style>
  @font-face { font-family:'THSarabun'; src: url({{ storage_path('fonts/THSarabunNew.ttf') }}) format('truetype'); }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1e293b; background: #fff; padding: 40px; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; border-bottom: 3px solid #6366f1; padding-bottom: 20px; }
  .logo-area h1 { font-size: 24px; font-weight: 800; color: #6366f1; letter-spacing: -0.5px; }
  .logo-area p { font-size: 11px; color: #64748b; margin-top: 2px; }
  .invoice-info { text-align: right; }
  .invoice-info h2 { font-size: 28px; font-weight: 800; color: #6366f1; letter-spacing: 1px; }
  .invoice-info p { font-size: 11px; color: #64748b; margin-top: 3px; }
  .details-grid { width: 100%; margin-bottom: 25px; }
  .details-grid td { vertical-align: top; padding: 8px 0; }
  .detail-box { background: #f8fafc; border-radius: 8px; padding: 14px 18px; }
  .detail-box h4 { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 6px; font-weight: 600; }
  .detail-box p { font-size: 12px; color: #334155; line-height: 1.5; }
  .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  .items-table thead th { background: #6366f1; color: #fff; padding: 10px 14px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
  .items-table thead th:first-child { border-radius: 8px 0 0 0; }
  .items-table thead th:last-child { border-radius: 0 8px 0 0; text-align: right; }
  .items-table tbody td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; font-size: 12px; }
  .items-table tbody tr:last-child td { border-bottom: none; }
  .items-table .text-right { text-align: right; }
  .items-table .text-center { text-align: center; }
  .totals { width: 280px; margin-left: auto; }
  .totals .row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 12px; }
  .totals .row.grand { border-top: 2px solid #6366f1; padding-top: 10px; margin-top: 6px; font-size: 16px; font-weight: 700; color: #6366f1; }
  .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center; }
  .footer p { font-size: 10px; color: #94a3b8; line-height: 1.6; }
  .status-badge { display: inline-block; padding: 4px 12px; border-radius: 50px; font-size: 11px; font-weight: 700; }
  .status-paid { background: #dcfce7; color: #166534; }
  .status-pending { background: #fef9c3; color: #854d0e; }
</style>
</head>
<body>
  <table width="100%" style="margin-bottom:30px;border-bottom:3px solid #6366f1;padding-bottom:20px;">
    <tr>
      <td style="vertical-align:top;">
        <div style="font-size:24px;font-weight:800;color:#6366f1;letter-spacing:-0.5px;">{{ $siteName }}</div>
        <div style="font-size:11px;color:#64748b;margin-top:2px;">{{ $siteUrl }}</div>
      </td>
      <td style="text-align:right;vertical-align:top;">
        <div style="font-size:28px;font-weight:800;color:#6366f1;letter-spacing:1px;">INVOICE</div>
        <div style="font-size:11px;color:#64748b;margin-top:3px;">{{ $invoiceNo }}</div>
        <div style="font-size:11px;color:#64748b;">{{ $date }}</div>
      </td>
    </tr>
  </table>

  <table width="100%" style="margin-bottom:25px;">
    <tr>
      <td width="50%" style="vertical-align:top;padding-right:10px;">
        <div style="background:#f8fafc;border-radius:8px;padding:14px 18px;">
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:6px;font-weight:600;">Bill To</div>
          <div style="font-size:12px;color:#334155;line-height:1.5;">
            <strong>{{ $order->user->name ?? 'Customer' }}</strong><br>
            {{ $order->user->email ?? '-' }}
          </div>
        </div>
      </td>
      <td width="50%" style="vertical-align:top;padding-left:10px;">
        <div style="background:#f8fafc;border-radius:8px;padding:14px 18px;">
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:6px;font-weight:600;">Order Details</div>
          <div style="font-size:12px;color:#334155;line-height:1.5;">
            Order #{{ $order->id }}<br>
            Event: {{ $order->event->name ?? '-' }}<br>
            Status: <span class="status-badge {{ $order->status === 'paid' ? 'status-paid' : 'status-pending' }}">{{ $order->status === 'paid' ? 'Paid' : ucfirst($order->status) }}</span>
          </div>
        </div>
      </td>
    </tr>
  </table>

  <table class="items-table">
    <thead>
      <tr>
        <th style="width:10%;">#</th>
        <th style="width:50%;">Description</th>
        <th style="width:15%;text-align:center;">Qty</th>
        <th style="width:25%;text-align:right;">Price (THB)</th>
      </tr>
    </thead>
    <tbody>
      @foreach($order->items as $i => $item)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $item->description ?? $item->file_name ?? 'Photo' }}</td>
        <td class="text-center">{{ $item->quantity ?? 1 }}</td>
        <td class="text-right">{{ number_format($item->price, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <table width="100%">
    <tr>
      <td width="60%"></td>
      <td width="40%">
        <table width="100%">
          <tr>
            <td style="padding:6px 0;font-size:12px;color:#64748b;">Subtotal</td>
            <td style="padding:6px 0;font-size:12px;text-align:right;">{{ number_format($order->total, 2) }} THB</td>
          </tr>
          <tr>
            <td style="padding:6px 0;font-size:12px;color:#64748b;">Tax (0%)</td>
            <td style="padding:6px 0;font-size:12px;text-align:right;">0.00 THB</td>
          </tr>
          <tr>
            <td style="border-top:2px solid #6366f1;padding-top:10px;margin-top:6px;font-size:16px;font-weight:700;color:#6366f1;">Total</td>
            <td style="border-top:2px solid #6366f1;padding-top:10px;font-size:16px;font-weight:700;color:#6366f1;text-align:right;">{{ number_format($order->total, 2) }} THB</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <div class="footer">
    <p>Thank you for your purchase!<br>{{ $siteName }} &mdash; {{ $siteUrl }}<br>This is a computer-generated document. No signature is required.</p>
  </div>
</body>
</html>
