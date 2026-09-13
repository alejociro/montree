<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Sales = 'sales';
    case Operator = 'operator';
    case Guide = 'guide';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => __('Super Admin'),
            self::Admin => __('Administrador'),
            self::Sales => __('Vendedor'),
            self::Operator => __('Operador'),
            self::Guide => __('Guía'),
            self::Customer => __('Viajero'),
        };
    }

    /**
     * Para qué sirve el rol, en una línea. Presentación pura, como `label()`:
     * los roles base son filas globales compartidas por todas las agencias, así
     * que su descripción no se guarda en la tabla (esa columna es de los roles
     * propios).
     */
    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => __('Administra la plataforma completa, por encima de las agencias.'),
            self::Admin => __('Control total del panel y de la configuración de la agencia.'),
            self::Sales => __('Vende y gestiona reservas, sin tocar la operación ni la configuración.'),
            self::Operator => __('Arma la operación: salidas, guías y fichas de logística.'),
            self::Guide => __('Consulta lo que necesita el día del viaje. Sin permisos de edición.'),
            self::Customer => __('Cliente de la agencia: reserva y consulta sus propios viajes.'),
        };
    }

    public function isGlobal(): bool
    {
        return $this === self::SuperAdmin;
    }
}
