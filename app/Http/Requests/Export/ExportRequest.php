<?php

// app/Http/Requests/Export/ExportRequest.php

namespace App\Http\Requests\Export;

use Illuminate\Foundation\Http\FormRequest;

class ExportRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'dataset_id'    => ['required', 'uuid'],
      'format'        => ['required', 'string', 'in:geojson,csv'],
      'geometry_type' => ['nullable', 'string', 'in:Point,LineString,Polygon,MultiPoint,MultiLineString,MultiPolygon'],
      'async'         => ['nullable', 'boolean'],
    ];
  }

  public function messages(): array
  {
    return [
      'format.in' => 'Format must be either geojson or csv.',
    ];
  }
}
