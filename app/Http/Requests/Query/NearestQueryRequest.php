<?php
namespace App\Http\Requests\Query;

use Illuminate\Foundation\Http\FormRequest;

class NearestQueryRequest extends FormRequest
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
      'limit'      => ['nullable', 'integer', 'min:1', 'max:100'],
    ];
  }
}
