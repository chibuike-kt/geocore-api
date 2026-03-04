<?php

// app/Http/Requests/Geofence/CreateGeofenceRequest.php

namespace App\Http\Requests\Geofence;

use App\Models\Geofence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateGeofenceRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'name'                    => ['required', 'string', 'min:2', 'max:255'],
      'type'                    => ['nullable', Rule::in([
        Geofence::TYPE_RESTRICTION,
        Geofence::TYPE_ADVISORY,
        Geofence::TYPE_SURVEY,
        Geofence::TYPE_CUSTOM,
      ])],
      'geometry'                => ['required', 'array'],
      'geometry.type'           => ['required', 'string', 'in:Polygon'],
      'geometry.coordinates'    => ['required', 'array'],
      'altitude_min'            => ['nullable', 'numeric'],
      'altitude_max'            => ['nullable', 'numeric', 'gte:altitude_min'],
      'active'                  => ['nullable', 'boolean'],
      'metadata'                => ['nullable', 'array'],
    ];
  }

  public function messages(): array
  {
    return [
      'geometry.type.in'     => 'Geofence geometry must be a Polygon.',
      'altitude_max.gte'     => 'altitude_max must be greater than or equal to altitude_min.',
    ];
  }
}
