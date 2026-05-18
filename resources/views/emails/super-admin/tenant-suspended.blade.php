@php
    /** @var string $tenantName */
    /** @var string|null $reason */
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Tu agencia ":tenant" fue suspendida', ['tenant' => $tenantName]) }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f4f5;padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;max-width:600px;width:100%;">
                    <tr>
                        <td style="padding:32px 32px 16px 32px;text-align:center;background-color:#dc2626;color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;font-weight:600;color:#ffffff;">{{ __('Acceso temporalmente suspendido') }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px 0;font-size:16px;">
                                {{ __('Tu agencia :tenant fue suspendida por el equipo de la plataforma.', ['tenant' => $tenantName]) }}
                            </p>
                            @if ($reason !== null && $reason !== '')
                                <p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;background-color:#fef2f2;padding:12px;border-left:4px solid #dc2626;">
                                    <strong>{{ __('Motivo:') }}</strong><br>
                                    {{ $reason }}
                                </p>
                            @endif
                            <p style="margin:24px 0 0 0;font-size:13px;color:#6b7280;">
                                {{ __('Si creés que se trata de un error, contactanos respondiendo a este correo.') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
