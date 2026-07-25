<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('orders.manage') ?? false;
    }

    /**
     * Phone cameras produce large files, so the limit is generous; the stored
     * copy is compressed to 1600px by the conversion, not by the uploader.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'collection' => ['nullable', Rule::in(['photos', 'designs'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photos.required' => 'অন্তত একটি ছবি বেছে নিন।',
            'photos.max' => 'একবারে সর্বোচ্চ ১০টি ছবি দেওয়া যাবে।',
            'photos.*.image' => 'শুধু ছবি দেওয়া যাবে।',
            'photos.*.mimes' => 'ছবি jpg, png বা webp হতে হবে।',
            'photos.*.max' => 'প্রতিটি ছবি সর্বোচ্চ ১০ এমবি হতে পারে।',
        ];
    }
}
