DELIMITER //

CREATE PROCEDURE SaveUserSchedule_P(
    IN target_date DATE,
    IN json_data TEXT
)
BEGIN
    DECLARE i INT DEFAULT 0;
    DECLARE user_count INT;
    DECLARE current_user_id INT;
    DECLARE schedule_count INT;
    DECLARE current_schedule_id VARCHAR(255);
    DECLARE current_schedule_name VARCHAR(255);
    DECLARE current_scheduled_time DATE;
    
    -- JSONデータをテンポラリテーブルに格納
    DROP TEMPORARY TABLE IF EXISTS temp_schedules;
    CREATE TEMPORARY TABLE temp_schedules (
        user_id INT,
        schedule_id VARCHAR(255),
        schedule_name VARCHAR(255),
        scheduled_time DATE
    );
    
    -- JSONデータを解析してテンポラリテーブルに挿入
    SET @users_json = json_data;
    SET @user_count = JSON_LENGTH(@users_json);
    
    WHILE i < @user_count DO
        SET @current_user = JSON_EXTRACT(@users_json, CONCAT('$[', i, ']'));
        SET @user_id = JSON_EXTRACT(@current_user, '$.user_id');
        SET @schedules = JSON_EXTRACT(@current_user, '$.schedules');
        SET @schedule_count = JSON_LENGTH(@schedules);
        
        SET @j = 0;
        WHILE @j < @schedule_count DO
            SET @current_schedule = JSON_EXTRACT(@schedules, CONCAT('$[', @j, ']'));
            SET @schedule_id = JSON_EXTRACT(@current_schedule, '$.schedule_id');
            SET @schedule_name = JSON_EXTRACT(@current_schedule, '$.schedule_name');
            SET @scheduled_time = target_date;
            
            -- ダブルクォートを削除
            SET @user_id = REPLACE(@user_id, '"', '');
            SET @schedule_id = REPLACE(@schedule_id, '"', '');
            SET @schedule_name = REPLACE(@schedule_name, '"', '');
            
            INSERT INTO temp_schedules (user_id, schedule_id, schedule_name, scheduled_time)
            VALUES (@user_id, @schedule_id, @schedule_name, @scheduled_time);
            
            SET @j = @j + 1;
        END WHILE;
        
        SET i = i + 1;
    END WHILE;
    
    -- スケジュールを保存
    INSERT INTO schedules (user_id, schedule_name, scheduled_time)
    SELECT user_id, schedule_name, scheduled_time
    FROM temp_schedules
    WHERE schedule_id = '' OR schedule_id IS NULL
    ON DUPLICATE KEY UPDATE
        schedule_name = VALUES(schedule_name),
        scheduled_time = VALUES(scheduled_time);
    
    -- 既存のスケジュールを更新
    UPDATE schedules s
    JOIN temp_schedules t ON s.id = t.schedule_id
    SET s.schedule_name = t.schedule_name,
        s.scheduled_time = t.scheduled_time
    WHERE t.schedule_id != '' AND t.schedule_id IS NOT NULL;
    
    -- 結果を返す
    SELECT 'スケジュールが正常に保存されました' AS message;
END //

DELIMITER ; 