<?php

// app/Http/Requests/Feature/CreateFeatureRequest.php

namespace App\Http\Requests\Feature;

use Illuminate\Foundation\Http\FormRequest;

class CreateFeatureRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'name'                => ['nullable', 'string', 'max:255'],
      'source_id'           => ['nullable', 'string', 'max:255'],
      'geometry'            => ['required', 'array'],
      'geometry.type'       => ['required', 'string'],
      'geometry.coordinates' => ['required_unless:geometry.type,GeometryCollection'],
      'properties'          => ['nullable', 'array'],
    ];
  }

  public function messages(): array
  {
    return [
      'geometry.required'      => 'A geometry object is required.',
      'geometry.type.required' => 'Geometry must have a type field.',
    ];
  }
}
