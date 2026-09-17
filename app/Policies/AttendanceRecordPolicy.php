<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

class AttendanceRecordPolicy
{
    /**
     * 前処理（管理者権限チェック）
     * 管理者の場合は全てのチェックをスキップして許可
     */
    public function before(User $user, string $ability): ?bool
    {
        // 管理者判定
        if ((bool) $user->admin_status) {
            return true;
        }

        return null; // 通常の認可チェックへ進行
    }

    /**
     * 更新権限の判定（本人か）
     */
    public function update(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->id === $attendanceRecord->user_id;
    }

    /**
     * 削除権限の判定（本人か）
     */
    public function delete(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->id === $attendanceRecord->user_id;
    }
}
