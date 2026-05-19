@php
    /** @var string $tenantName */
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Tu agencia ":tenant" fue restablecida', ['tenant' => $tenantName]) }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f4f5;padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;max-width:600px;width:100%;">
                    <tr>
                        <td style="padding:32px 32px 16px 32px;text-align:center;background-color:#16a34a;color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;font-weight:600;color:#ffffff;">{{ __('Acceso restablecido') }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px 0;font-size:16px;">
                                {{ __('Tu agencia :tenant ya está activa nuevamente.', ['tenant' => $tenantName]) }}
                            </p>
                            <p style="margin:0;font-size:15px;line-height:1.6;">
                                {{ __('Podés acceder normalmente al panel de administración.') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
