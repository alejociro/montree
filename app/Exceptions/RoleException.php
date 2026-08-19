<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class RoleException extends \Exception implements HttpExceptionInterface
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        private readonly int $statusCode = 422,
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }

    public function toResponse(): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ], $this->statusCode);
    }

    /**
     * WHY: no se usa `INSUFFICIENT_PERMISSION` (F018 contracts.md §4) porque a quien edita
     * no le falta un permiso — tiene `team.role.update`; lo que pasa es que el rol base es
     * de solo lectura para todas las agencias. Un 403 con otro motivo necesita otro código.
     */
    public static function baseRoleIsReadOnly(): self
    {
        return new self(
            'BASE_ROLE_READ_ONLY',
            __('Los roles base son de solo lectura. Creá un rol propio de tu agencia para personalizar permisos.'),
            403,
        );
    }

    public static function inUse(int $users): self
    {
        return new self(
            'ROLE_IN_USE',
            trans_choice(
                '{1}No puedes eliminar este rol: :count persona del equipo lo tiene asignado.|[2,*]No puedes eliminar este rol: :count personas del equipo lo tienen asignado.',
                $users,
                ['count' => $users],
            ),
            409,
        );
    }
}
