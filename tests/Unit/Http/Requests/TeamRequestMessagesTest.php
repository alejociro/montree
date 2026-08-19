<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\Admin\Team\InviteMemberRequest;
use App\Http\Requests\Admin\Team\UpdateMemberRoleRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * WHY: neither request is wired to a route yet, so their messages can only be
 * exercised by driving the validator with the request's own rules.
 */
class TeamRequestMessagesTest extends TestCase
{
    public function test_invite_member_reports_failures_in_spanish(): void
    {
        $request = new InviteMemberRequest;

        $validator = Validator::make(
            ['email' => 'no-es-un-correo', 'role' => 'pirata'],
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        );

        $this->assertSame('Correo inválido.', $validator->errors()->first('email'));
        $this->assertSame('Ese rol no es válido.', $validator->errors()->first('role'));
    }

    public function test_invite_member_reports_a_missing_email_in_spanish(): void
    {
        $request = new InviteMemberRequest;

        $validator = Validator::make([], $request->rules(), $request->messages(), $request->attributes());

        $this->assertSame(
            'Ingresa el correo de la persona a invitar.',
            $validator->errors()->first('email'),
        );
    }

    public function test_update_member_role_reports_failures_in_spanish(): void
    {
        $request = new UpdateMemberRoleRequest;

        $validator = Validator::make([], $request->rules(), $request->messages(), $request->attributes());

        $this->assertSame('Elige al menos un rol.', $validator->errors()->first('roles'));
    }
}
