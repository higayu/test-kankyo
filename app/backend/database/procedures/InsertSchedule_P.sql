DELIMITER //

CREATE PROCEDURE InsertSchedule_P(
    IN p_user_id INT,
    IN p_schedule_name VARCHAR(255),
    IN p_scheduled_time DATE
)
BEGIN
    INSERT INTO schedules (user_id, schedule_name, scheduled_time)
    VALUES (p_user_id, p_schedule_name, p_scheduled_time);
    
    -- 挿入されたIDを返す
    SELECT LAST_INSERT_ID() AS inserted_id;
END //

DELIMITER ; 