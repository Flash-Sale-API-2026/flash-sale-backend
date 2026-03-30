<?php

namespace App\Http\Requests;

use App\Http\Resources\MessageResource;
use App\Services\Auth\GatewayUserResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(GatewayUserResolver::class)->rawUserId($this) !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'authenticated_user_id' => ['required', 'integer', 'min:1'],
            'ticket_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function validationData(): array
    {
        return array_merge($this->all(), [
            'authenticated_user_id' => app(GatewayUserResolver::class)->rawUserId($this),
        ]);
    }

    /**
     * @return array{user_id: int, ticket_id: int}
     */
    public function orderData(): array
    {
        return [
            'user_id' => (int) $this->validated('authenticated_user_id'),
            'ticket_id' => (int) $this->validated('ticket_id'),
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            (new MessageResource([
                'message' => 'Only authenticated users can place orders.',
            ]))
                ->response()
                ->setStatusCode(401)
        );
    }
}
