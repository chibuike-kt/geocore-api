<?php

// app/Http/Requests/Analytics/BufferRequest.php

namespace App\Http\Requests\Analytics;

use Illuminate\Foundation\Http\FormRequest;

class BufferRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'geometry'             => ['required', 'array'],
      'geometry.type'        => ['required', 'string'],
      'geometry.coordinates' => ['required', 'array'],
      'radius'               => ['required', 'numeric', 'min:1', 'max:100000'],
      'dataset_id'           => ['nullable', 'uuid'],
    ];
  }

  public function messages(): array
  {
    return [
      'radius.min' => 'Buffer radius must be at least 1 metre.',
      'radius.max' => 'Buffer radius cannot exceed 100,000 metres.',
    ];
  }
}
