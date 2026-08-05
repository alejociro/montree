@php
    /** @var string $agencyName */
    /** @var string $primaryColor */
    /** @var string $recipientName */
    /** @var string $verificationUrl */
    /** @var int $expiresInMinutes */
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Activá tu agencia en MONTREE') }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f4f5;padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;max-width:600px;width:100%;">
                    <tr>
                        <td style="padding:32px 32px 16px 32px;text-align:center;background-color:{{ $primaryColor }};color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;font-weight:600;color:#ffffff;">{{ $agencyName }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px 0;font-size:16px;">{{ __('Hola :name,', ['name' => $recipientName]) }}</p>
                            <p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;">
                                {{ __('Tu agencia :agency ya está casi lista. Confirmá tu correo para activarla, empezar tu prueba gratuita y entrar directo a tu panel de administración.', ['agency' => $agencyName]) }}
                            </p>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0;">
                                <tr>
                                    <td align="center" style="border-radius:6px;background-color:{{ $primaryColor }};">
                                        <a href="{{ $verificationUrl }}" style="display:inline-block;padding:12px 24px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:6px;">
                                            {{ __('Activar mi agencia') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 8px 0;font-size:13px;color:#6b7280;line-height:1.6;">
                                {{ __('Este enlace expira en :minutes minutos. Si no creaste esta cuenta, podés ignorar este correo.', ['minutes' => $expiresInMinutes]) }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
