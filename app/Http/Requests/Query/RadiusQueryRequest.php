<?php
namespace App\Http\Requests\Query;

use Illuminate\Foundation\Http\FormRequest;

class RadiusQueryRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'dataset_id' => ['required', 'uuid'],
      'lat'        => ['required', 'numeric', 'between:-90,90'],
      'lng'        => ['required', 'numeric', 'between:-180,180'],
      'radius'     => ['required', 'numeric', 'min:1'],
    ];
  }

  public function messages(): array
  {
    return [
      'lat.between' => 'Latitude must be between -90 and 90.',
      'lng.between' => 'Longitude must be between -180 and 180.',
      'radius.min'  => 'Radius must be at least 1 metre.',
    ];
  }
}
