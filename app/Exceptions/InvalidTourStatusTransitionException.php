<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\TourStatus;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class InvalidTourStatusTransitionException extends RuntimeException implements HttpExceptionInterface
{
    private string $errorCode = 'INVALID_STATUS_TRANSITION';

    public function __construct(TourStatus $from, TourStatus $to)
    {
        parent::__construct(sprintf(
            'Cannot transition tour from %s to %s.',
            $from->value,
            $to->value,
        ));
    }

    public static function needsImage(): self
    {
        $exception = new self(TourStatus::Draft, TourStatus::Active);
        $exception->message = 'Tour needs at least one image before activating.';
        $exception->errorCode = 'TOUR_NEEDS_IMAGE_TO_ACTIVATE';

        return $exception;
    }

    public static function needsFutureDate(): self
    {
        $exception = new self(TourStatus::Draft, TourStatus::Active);
        $exception->message = 'Tour needs at least one open future date before activating.';
        $exception->errorCode = 'TOUR_NEEDS_FUTURE_DATE_TO_ACTIVATE';

        return $exception;
    }

    public function getStatusCode(): int
    {
        return 422;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function toResponse(): JsonResponse
    {
        return new JsonResponse([
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ], 422);
    }
}
