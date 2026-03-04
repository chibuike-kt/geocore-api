<?php

// app/Http/Requests/Analytics/DensityRequest.php

namespace App\Http\Requests\Analytics;

use Illuminate\Foundation\Http\FormRequest;

class DensityRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'dataset_id' => ['required', 'uuid'],
      'cell_size'  => ['required', 'numeric', 'min:10', 'max:100000'],
    ];
  }

  public function messages(): array
  {
    return [
      'cell_size.min' => 'Cell size must be at least 10 metres.',
      'cell_size.max' => 'Cell size cannot exceed 100,000 metres.',
    ];
  }
}
