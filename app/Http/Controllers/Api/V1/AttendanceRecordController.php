<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AttendanceRecordController extends Controller
{
    // 勤怠一覧の取得
    public function index(IndexAttendanceRecordRequest $request): AnonymousResourceCollection
    {
        // 1ページあたりの件数（デフォルト: 20件）
        $perPage = $request->integer('per_page', 20);

        // クエリビルダの構築
        $attendanceRecords = AttendanceRecord::with(['user', 'breakrecords'])
            ->when($request->filled('user_id'), function ($query) use ($request) {
                $query->where('user_id', $request->input('user_id'));
            })
            ->when($request->filled('date'), function ($query) use ($request) {
                $query->whereDate('date', $request->input('date'));
            })
            ->when($request->filled('month'), function ($query) use ($request) {
                // YYYY-MM 形式での前方一致検索（例: "2026-05%"）
                $query->where('date', 'like', $request->input('month').'%');
            })
            ->latest('date')
            ->paginate($perPage);

        // ResourceCollectionを返却（Laravelが自動で data + links + meta を付与）
        return AttendanceRecordResource::collection($attendanceRecords);
    }

    // 勤怠詳細の取得
    public function show(AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        // モデルの定義に合わせて eager load
        $attendanceRecord->load(['user', 'breakRecords', 'stampCorrectionRequests']);

        return new AttendanceRecordResource($attendanceRecord);
    }

    // 勤怠の新規登録
    public function store(StoreAttendanceRecordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (! isset($validated['status'])) {
            $validated['status'] = isset($validated['clock_out']) ? '退勤' : '出勤';
        }

        // ログインユーザーのリレーション経由で作成（user_idを自動付与）
        $attendanceRecord = $request->user()->attendanceRecords()->create($validated);

        // 関連を eager load
        $attendanceRecord->load(['user', 'breakRecords']);

        // 201 Created ステータスでレスポンス返却
        return (new AttendanceRecordResource($attendanceRecord))
            ->response()
            ->setStatusCode(201);
    }

    // 勤怠レコードの更新
    public function update(UpdateAttendanceRecordRequest $request, AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        // 認可チェック（本人または管理者以外は 403 エラーが発生）
        $this->authorize('update', $attendanceRecord);

        // バリデーション済みデータの取得・更新
        $validated = $request->validated();
        $attendanceRecord->update($validated);

        // リレーションを再読み込みしてレスポンス返却
        $attendanceRecord->load(['user', 'breakRecords']);

        return new AttendanceRecordResource($attendanceRecord);
    }

    // 勤怠記録の削除
    public function destroy(AttendanceRecord $attendanceRecord): Response
    {
        // 認可チェック（本人または管理者以外は 403 エラー）
        $this->authorize('delete', $attendanceRecord);

        // レコード削除（関連する breaks / applications は DB の ON DELETE CASCADE により自動削除）
        $attendanceRecord->delete();

        // 204 No Content を返却 (ボディなし)
        return response()->noContent();
    }
}
