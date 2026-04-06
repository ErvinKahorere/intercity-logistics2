<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#1f1f1f;">
    <div style="max-width:640px;margin:0 auto;padding:32px 16px;">
        <div style="background:#ffffff;border-radius:20px;padding:32px;border:1px solid #ececec;box-shadow:0 10px 26px rgba(31,31,31,0.06);">
            <div style="display:inline-block;padding:8px 12px;border-radius:999px;background:#f2c9001f;color:#2F2E7C;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;">InterCity Logistics</div>
            <h1 style="font-size:28px;line-height:1.2;margin:18px 0 12px;font-weight:800;color:#1f1f1f;">{{ $title }}</h1>
            <p style="font-size:16px;line-height:1.7;margin:0 0 18px;color:#444;">{{ $messageBody }}</p>
            @if(!empty($meta['tracking_number']))
                <div style="margin:18px 0 0;padding:18px;border-radius:16px;background:#fafaf8;border:1px solid #ededed;">
                    <div style="font-size:12px;text-transform:uppercase;letter-spacing:0.12em;color:#777;font-weight:700;">Tracking number</div>
                    <div style="margin-top:8px;font-size:18px;font-weight:800;color:#2F2E7C;">{{ $meta['tracking_number'] }}</div>
                </div>
            @endif
            @if(!empty($meta['summary']))
                <p style="margin:18px 0 0;font-size:14px;line-height:1.6;color:#666;">{{ $meta['summary'] }}</p>
            @endif
        </div>
    </div>
</body>
</html>
