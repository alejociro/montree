@php
    /** @var string $tenantName */
    /** @var string $primaryColor */
    /** @var string|null $logoUrl */
    /** @var string $resetUrl */
    /** @var string $recipientName */
    /** @var int $expiresInMinutes */
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Restablece tu contraseña en :tenant', ['tenant' => $tenantName]) }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f4f5;padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;max-width:600px;width:100%;">
                    <tr>
                        <td style="padding:32px 32px 16px 32px;text-align:center;background-color:{{ $primaryColor }};color:#ffffff;">
                            @if ($logoUrl !== null)
                                <img src="{{ $logoUrl }}" alt="{{ $tenantName }}" height="48" style="display:inline-block;max-height:48px;margin-bottom:12px;">
                            @endif
                            <h1 style="margin:0;font-size:22px;font-weight:600;color:#ffffff;">{{ $tenantName }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px 0;font-size:16px;">{{ __('Hola :name,', ['name' => $recipientName]) }}</p>
                            <p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;">
                                {{ __('Recibimos una solicitud para restablecer la contraseña de tu cuenta en :tenant. Hacé clic en el botón para crear una nueva contraseña.', ['tenant' => $tenantName]) }}
                            </p>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0;">
                                <tr>
                                    <td align="center" style="border-radius:6px;background-color:{{ $primaryColor }};">
                                        <a href="{{ $resetUrl }}" style="display:inline-block;padding:12px 24px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:6px;">
                                            {{ __('Restablecer contraseña') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 12px 0;font-size:13px;color:#6b7280;">
                                {{ __('Este enlace expira en :minutes minutos.', ['minutes' => $expiresInMinutes]) }}
                            </p>
                            <p style="margin:0 0 8px 0;font-size:13px;color:#6b7280;">
                                {{ __('Si no solicitaste un cambio de contraseña, ignorá este mensaje y tu cuenta seguirá segura.') }}
                            </p>
                            <p style="margin:24px 0 0 0;font-size:13px;color:#6b7280;word-break:break-all;">
                                {{ __('¿No funciona el botón? Copiá y pegá este enlace en tu navegador:') }}<br>
                                <a href="{{ $resetUrl }}" style="color:{{ $primaryColor }};">{{ $resetUrl }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px;text-align:center;font-size:12px;color:#9ca3af;background-color:#f9fafb;">
                            {{ $tenantName }} &middot; {{ __('Powered by MONTREE') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
