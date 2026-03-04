<?php

// app/Http/Requests/Dataset/UpdateDatasetRequest.php

namespace App\Http\Requests\Dataset;

use App\Models\Dataset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDatasetRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'name'        => ['sometimes', 'string', 'min:2', 'max:255'],
      'type'        => ['sometimes', 'string', Rule::in(Dataset::TYPES)],
      'description' => ['nullable', 'string', 'max:2000'],
      'metadata'    => ['nullable', 'array'],
    ];
  }

  public function messages(): array
  {
    return [
      'type.in' => 'Type must be one of: ' . implode(', ', Dataset::TYPES),
    ];
  }
}
