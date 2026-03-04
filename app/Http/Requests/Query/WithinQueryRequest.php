<?php
namespace App\Http\Requests\Query;

use Illuminate\Foundation\Http\FormRequest;

class WithinQueryRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'dataset_id'              => ['required', 'uuid'],
      'geometry'                => ['required', 'array'],
      'geometry.type'           => ['required', 'string', 'in:Polygon,MultiPolygon'],
      'geometry.coordinates'    => ['required', 'array'],
    ];
  }

  public function messages(): array
  {
    return [
      'geometry.type.in' => 'Within query requires a Polygon or MultiPolygon geometry.',
    ];
  }
}
