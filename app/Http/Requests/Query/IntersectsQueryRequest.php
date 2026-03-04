<?php
namespace App\Http\Requests\Query;

use Illuminate\Foundation\Http\FormRequest;

class IntersectsQueryRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'dataset_id'           => ['required', 'uuid'],
      'geometry'             => ['required', 'array'],
      'geometry.type'        => ['required', 'string'],
      'geometry.coordinates' => ['required', 'array'],
    ];
  }
}
