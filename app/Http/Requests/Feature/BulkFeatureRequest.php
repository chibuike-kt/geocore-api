<?php

// app/Http/Requests/Feature/BulkFeatureRequest.php

namespace App\Http\Requests\Feature;

use Illuminate\Foundation\Http\FormRequest;

class BulkFeatureRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'type'          => ['required', 'string', 'in:FeatureCollection'],
      'features'      => ['required', 'array', 'min:1'],
      'features.*.geometry'            => ['required', 'array'],
      'features.*.geometry.type'       => ['required', 'string'],
      'features.*.geometry.coordinates' => ['required', 'array'],
      'features.*.properties'          => ['nullable', 'array'],
    ];
  }

  public function messages(): array
  {
    return [
      'type.in'              => 'Root type must be FeatureCollection.',
      'features.required'    => 'FeatureCollection must contain a features array.',
      'features.min'         => 'FeatureCollection must contain at least 1 feature.',
    ];
  }
}
