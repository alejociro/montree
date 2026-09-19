<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Una categoría con productos no se borra: se nombra qué la retiene, igual que
 * las fichas de logística. Desactivarla es la salida, y el mensaje lo dice.
 */
final class CategoryInUseException extends RuntimeException implements HttpExceptionInterface
{
    private const MAX_LISTED_TOURS = 3;

    public function getStatusCode(): int
    {
        return 409;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }

    /**
     * @param  list<string>  $tours  nombres de los productos que la usan
     */
    public static function usedByTours(int $count, array $tours): self
    {
        return new self(trans_choice(
            '{1}No se puede eliminar: :count producto usa esta categoría (:tours). Desactívala para ocultarla del catálogo.|[2,*]No se puede eliminar: :count productos usan esta categoría (:tours). Desactívala para ocultarla del catálogo.',
            $count,
            ['count' => $count, 'tours' => self::summarize($count, $tours)],
        ));
    }

    /**
     * @param  list<string>  $tours
     */
    private static function summarize(int $count, array $tours): string
    {
        $listed = array_slice($tours, 0, self::MAX_LISTED_TOURS);
        $rest = $count - count($listed);

        if ($rest <= 0) {
            return implode(', ', $listed);
        }

        return __(':list y :count más', ['list' => implode(', ', $listed), 'count' => $rest]);
    }
}
