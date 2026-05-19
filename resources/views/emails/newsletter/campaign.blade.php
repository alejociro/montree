<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $subject }}</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f5f7f5; margin:0; padding:24px;">
    <table align="center" width="600" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:8px; padding:32px;">
        <tr>
            <td style="border-bottom: 2px solid #16a34a; padding-bottom:16px;">
                <h1 style="margin:0; color:#16a34a; font-size:22px;">{{ $tenantName }}</h1>
            </td>
        </tr>
        @if($previewText)
        <tr>
            <td style="padding-top:16px; color:#6b7280; font-size:14px;">{{ $previewText }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding-top:16px; color:#111827; line-height:1.6;">{!! $bodyHtml !!}</td>
        </tr>
        <tr>
            <td style="padding-top:32px; border-top:1px solid #e5e7eb; color:#9ca3af; font-size:12px; text-align:center;">
                <a href="{{ $unsubscribeUrl }}" style="color:#9ca3af;">Darme de baja</a>
            </td>
        </tr>
    </table>
</body>
</html>
