<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class TeamException extends \Exception implements HttpExceptionInterface
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

    public static function alreadyMember(): self
    {
        return new self('TEAM_ALREADY_MEMBER', 'Este usuario ya es miembro del equipo.', 409);
    }

    public static function lastAdmin(): self
    {
        return new self('TEAM_LAST_ADMIN', 'No puedes remover al último admin del equipo.', 422);
    }

    public static function rolesRequired(): self
    {
        return new self('TEAM_ROLES_REQUIRED', 'Un miembro del equipo necesita al menos un rol.', 422);
    }

    public static function invitationAlreadyAccepted(): self
    {
        return new self('TEAM_INVITATION_ALREADY_ACCEPTED', 'Esta persona ya aceptó su invitación.', 422);
    }
}
