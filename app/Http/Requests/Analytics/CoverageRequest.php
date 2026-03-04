<?php

// app/Http/Requests/Analytics/CoverageRequest.php

namespace App\Http\Requests\Analytics;

use Illuminate\Foundation\Http\FormRequest;

class CoverageRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'dataset_id'              => ['required', 'uuid'],
      'boundary'                => ['nullable', 'array'],
      'boundary.type'           => ['required_with:boundary', 'string', 'in:Polygon,MultiPolygon'],
      'boundary.coordinates'    => ['required_with:boundary', 'array'],
    ];
  }
}
