<?php

namespace App\Http\Controllers;

use App\Models\StampCorrectionRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StampCorrectionRequestController extends Controller
{
    /**
     * 申請一覧画面（一般ユーザー用）
     */
    public function index(): View
    {
        $user = Auth::user();

        $rawApplications = StampCorrectionRequest::with('attendanceRecord')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedApplications = $rawApplications->map(function (StampCorrectionRequest $app): array {
            $statusStr = ($app->status === 'approved' || $app->status === '承認済み')
                ? '承認済み'
                : '承認待ち';

            return [
                'id' => $app->attendance_record_id,
                'approval_status' => $statusStr,
                'date' => $app->attendanceRecord ? Carbon::parse($app->attendanceRecord->date)->format('Y/m/d') : '',
                'comment' => $app->comment,
                'application_date' => $app->created_at ? $app->created_at->format('Y/m/d') : '',
            ];
        });

        return view('user.user-application-list', compact('user', 'formattedApplications'));
    }
}
