<?php

return [
    'required' => ':attribute は必須項目です。',
    'date' => ':attribute には有効な日付を指定してください。',
    'date_format' => ':attribute のフォーマットは :format で指定してください。',
    'after' => ':attribute には :date より後の時間を指定してください。',
    'unique' => 'この :attribute は既に登録されています。',
    'string' => ':attribute には文字列を指定してください。',
    'nullable' => ':attribute には null を指定できます。',

    /*
    |--------------------------------------------------------------------------
    | 属性名（フィールド名）の日本語変換
    |--------------------------------------------------------------------------
    */
    'attributes' => [
        'date' => '日付',
        'clock_in' => '出勤時間',
        'clock_out' => '退勤時間',
        'status' => 'ステータス',
    ],
];
