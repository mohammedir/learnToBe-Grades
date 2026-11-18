<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\GradeSheet;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        // المدرس يرى تكليفاته فقط
        $assignments = $request->user()->assignments()->with(['section.gradeLevel', 'subject'])->get()
            ->sortBy(fn ($a) => [$a->section->gradeLevel->sort_order, $a->section->name, $a->subject->sort_order]);

        return view('teacher.home', [
            'assignments' => $assignments,
            'progress' => GradeSheet::progressFor($assignments),
            // طلبات المطبعة التي سيستلمها هذا المدرس
            'pickups' => \App\Models\PurchaseOrder::with(['supplier', 'section.gradeLevel', 'gradeLevel'])
                ->where('recipient_id', auth()->id())
                ->where(fn ($q) => $q->whereIn('status', ['pending', 'preparing', 'ready'])->orWhere(fn ($w) => $w->where('status', 'received')->where('received_at', '>=', now()->subDays(7))))
                ->orderByRaw("CASE status WHEN 'ready' THEN 0 WHEN 'preparing' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END")->limit(10)->get(),
        ]);
    }
}
