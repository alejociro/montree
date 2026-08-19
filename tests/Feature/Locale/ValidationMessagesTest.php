<?php

declare(strict_types=1);

namespace Tests\Feature\Locale;

use App\Exceptions\BookingException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidationMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_messages_are_spanish_when_locale_is_es(): void
    {
        app()->setLocale('es');

        $validator = Validator::make(['email' => 'no-es-un-correo'], [
            'name' => ['required'],
            'email' => ['email'],
        ]);

        $errors = $validator->errors();

        $this->assertSame('El campo nombre es obligatorio.', $errors->first('name'));
        $this->assertSame('El campo correo electrónico debe ser un correo electrónico válido.', $errors->first('email'));
    }

    public function test_validation_messages_are_english_when_locale_is_en(): void
    {
        app()->setLocale('en');

        $validator = Validator::make([], ['name' => ['required']]);

        $this->assertStringContainsString('required', $validator->errors()->first('name'));
        $this->assertStringNotContainsString('obligatorio', $validator->errors()->first('name'));
    }

    public function test_domain_exception_message_is_translated(): void
    {
        app()->setLocale('en');

        $this->assertSame(
            'The selected date is no longer available.',
            BookingException::dateNotAvailable()->getMessage(),
        );
    }
}
