<?php

namespace App\Http\Requests\Internal;

use App\Http\Resources\MessageResource;
use App\Services\Auth\InternalServiceAuthenticator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ConfirmTicketReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(InternalServiceAuthenticator::class)->isAuthorized($this);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function userId(): int
    {
        return (int) $this->validated('user_id');
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            (new MessageResource([
                'message' => 'Only trusted internal services can confirm reservations.',
            ]))
                ->response()
                ->setStatusCode(401)
        );
    }
}
