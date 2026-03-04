<?php

// app/Http/Requests/Dataset/CreateDatasetRequest.php

namespace App\Http\Requests\Dataset;

use App\Models\Dataset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDatasetRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'name'        => ['required', 'string', 'min:2', 'max:255'],
      'type'        => ['required', 'string', Rule::in(Dataset::TYPES)],
      'description' => ['nullable', 'string', 'max:2000'],
      'srid'        => ['nullable', 'integer'],
      'metadata'    => ['nullable', 'array'],
    ];
  }

  public function messages(): array
  {
    return [
      'type.in' => 'Type must be one of: ' . implode(', ', Dataset::TYPES),
    ];
  }

  /**
   * Trim string inputs before validation.
   */
  protected function prepareForValidation(): void
  {
    $this->merge([
      'name' => trim($this->name ?? ''),
    ]);
  }
}
