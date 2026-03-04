<?php

// app/Http/Requests/Mission/CreateMissionRequest.php

namespace App\Http\Requests\Mission;

use App\Models\Mission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateMissionRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'name'                            => ['required', 'string', 'min:2', 'max:255'],
      'operator'                        => ['required', 'string', 'max:255'],
      'status'                          => ['nullable', Rule::in(Mission::STATUSES)],
      'start_time'                      => ['nullable', 'date'],
      'end_time'                        => ['nullable', 'date', 'after:start_time'],
      'planned_area'                    => ['nullable', 'array'],
      'planned_area.type'               => ['required_with:planned_area', 'string', 'in:Polygon'],
      'planned_area.coordinates'        => ['required_with:planned_area', 'array'],
      'metadata'                        => ['nullable', 'array'],
    ];
  }
}
