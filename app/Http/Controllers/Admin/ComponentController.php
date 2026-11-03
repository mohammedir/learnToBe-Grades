<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentComponent;
use App\Models\Mark;
use Illuminate\Http\Request;

class ComponentController extends Controller
{
    public function index()
    {
        $components = AssessmentComponent::orderBy('sort_order')->orderBy('id')->get();
        $used = Mark::selectRaw('assessment_component_id, COUNT(*) n')->groupBy('assessment_component_id')->pluck('n', 'assessment_component_id');

        return view('admin.components.index', compact('components', 'used'));
    }

    public function store(Request $request)
    {
        AssessmentComponent::create($this->validated($request) + ['sort_order' => (int) AssessmentComponent::max('sort_order') + 1]);

        return back()->with('status', 'تمت إضافة العنصر.');
    }

    public function update(Request $request, AssessmentComponent $component)
    {
        $data = $this->validated($request);
        $over = Mark::where('assessment_component_id', $component->id)->where('score', '>', $data['max_score'])->count();
        if ($over) {
            return back()->with('warning', "لا يمكن خفض الدرجة العظمى: يوجد {$over} درجة مرصودة أعلى من {$data['max_score']}.");
        }
        $component->update($data);

        return back()->with('status', 'تم حفظ العنصر.');
    }

    public function destroy(AssessmentComponent $component)
    {
        if (Mark::where('assessment_component_id', $component->id)->exists()) {
            $component->update(['is_active' => false]);

            return back()->with('status', 'العنصر عليه درجات مرصودة، فأوقفته بدل حذفه. يمكنك إعادة تفعيله لاحقاً.');
        }
        $component->delete();

        return back()->with('status', 'تم حذف العنصر.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'max_score' => ['required', 'numeric', 'min:0.5', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [], ['name' => 'اسم العنصر', 'max_score' => 'الدرجة العظمى']);
        $data['is_active'] = $request->boolean('is_active', true);

        return array_filter($data, fn ($v) => $v !== null);
    }
}
